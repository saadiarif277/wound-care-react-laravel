import React, { useState, useRef, useEffect } from 'react';
import { Send, X, Loader2, Mic, MicOff } from 'lucide-react';
import { Button } from '@/Components/GhostAiUi/ui/button';
import { useToast } from './hooks/use-toast';
import { useWhisperTranscription } from './hooks/useWhisperTranscription';
import ConversationHistory from './ConversationHistory';
import VoiceControls from './VoiceControls';
import ActionButtons from './ActionButtons';
import DocumentUploadZone from './DocumentUploadZone';
import MarkdownFormRenderer from './MarkdownFormRenderer';
import api from '@/lib/api';
import { MarkdownProvider } from '@superinterface/react';
import './client-tools'; // Initialize Superinterface client tools

interface AIOverlayProps {
  isVisible: boolean;
  onClose: () => void;
}

interface Message {
  role: 'user' | 'assistant';
  content: string;
}

const AIOverlay: React.FC<AIOverlayProps> = ({ isVisible, onClose }) => {
  const [message, setMessage] = useState('');
  const [isProcessing, setIsProcessing] = useState(false);
  const [conversation, setConversation] = useState<Message[]>([]);
  const [showDocumentUpload, setShowDocumentUpload] = useState(false);
  const [currentMarkdownForm, setCurrentMarkdownForm] = useState('');
  const [formValues, setFormValues] = useState<Record<string, any>>({});

  const inputRef = useRef<HTMLInputElement>(null);
  const { toast } = useToast();
  const { isRecording, isTranscribing, toggleRecording } = useWhisperTranscription();

  // Focus input when overlay becomes visible
  useEffect(() => {
    if (isVisible && inputRef.current) {
      setTimeout(() => inputRef.current?.focus(), 100);
    }
  }, [isVisible]);

  // Handle keyboard shortcuts
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (!isVisible) return;

      if (e.key === 'Escape') {
        onClose();
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [isVisible, onClose]);

  const handleVoiceToggle = async () => {
    await toggleRecording((transcript: string) => {
      setMessage(prev => prev + ' ' + transcript);
    });
  };

  const handleSendMessage = async () => {
    if (!message.trim() || isProcessing) return;

    const userMessage = message.trim();
    setMessage('');

      setIsProcessing(true);
      const newConversation = [...conversation, { role: 'user' as const, content: userMessage }];
      setConversation(newConversation);

      try {
        const response = await api.post('/api/v1/ai/chat', {
          message: userMessage,
          conversation_history: newConversation,
          current_form: currentMarkdownForm ? {
            markdown: currentMarkdownForm,
            values: formValues
          } : null
        });

        const data = response.data;
        const aiResponse = data.reply;

        // Check if AI wants to use a client tool
        if (data.tool_call) {
          await executeClientTool(data.tool_call, newConversation);
        } else {
          // Regular response
          const updatedConversation = [...newConversation, { role: 'assistant' as const, content: aiResponse }];
          setConversation(updatedConversation);

          if (data.markdown) {
            setCurrentMarkdownForm(data.markdown);
          }
        }

      } catch (error) {
        console.error('Error processing message:', error);
        toast({
          title: "Error",
          description: "Failed to process your message. Please try again.",
          variant: "destructive"
        });
      } finally {
        setIsProcessing(false);
    }
  };

  const handleKeyPress = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSendMessage();
    }
  };

  const handleProductRequest = async () => {
    const productRequestMessage = "I'd like to submit a new product request. Please walk me through the required fields.";
    setMessage(productRequestMessage);
    setTimeout(() => {
      handleSendMessage();
    }, 100);
  };

  const handleDocumentsUploaded = async (documents: any[]) => {
    for (const doc of documents) {
      if (doc.status === 'pending') {
        await processDocument(doc);
      }
    }
  };

  const processDocument = async (document: any) => {
    try {
      const formData = new FormData();
      formData.append('document', document.file);
      formData.append('type', document.type);

      const response = await api.post('/api/document/analyze', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });

      const data = response.data;
        
      const markdown = generateMarkdownForm(document.type, data);
      setCurrentMarkdownForm(markdown);
      
      if (data) {
        setFormValues(prev => ({ ...prev, ...data }));
      }

      toast({
        title: "Document Processed",
        description: `Successfully extracted data from ${document.file.name}`,
      });
    } catch (error) {
      console.error('Document processing error:', error);
      toast({
        title: "Processing Error",
        description: "Failed to process document. Please try again.",
        variant: "destructive"
      });
    }
  };

  const generateMarkdownForm = (documentType: string, extractedData: any): string => {
    switch (documentType) {
      case 'insurance_card':
        return `## ✓ Insurance Card Processed

We found the following information from your insurance card:

**Primary Insurance**
- Payer: [input:primary_insurance_name|${extractedData.payer_name || ''}]
- Member ID: [input:primary_member_id|${extractedData.member_id || ''}]
- Group Number: [input:primary_group_number|${extractedData.group_number || ''}]

**Patient Information**
- Name: [input:patient_name|${extractedData.patient_name || ''}]
- Date of Birth: [date:patient_dob|${extractedData.patient_dob || ''}]

[button:Confirm Information|confirm_insurance]`;

      case 'clinical_note':
        return `## Clinical Information Extracted

**Primary Diagnosis**
- ICD-10: [input:primary_diagnosis|${extractedData.primary_diagnosis || ''}]
- Description: [textarea:diagnosis_description|${extractedData.diagnosis_description || ''}]

**Wound Details**
- Location: [select:wound_location|Foot|Leg|Arm|Other|${extractedData.wound_location || ''}]
- Size (cm): [input:wound_size|${extractedData.wound_size || ''}]

[button:Continue|validate_clinical]`;

      default:
        return `## Document Uploaded

Document: ${extractedData.filename || 'Document'}

[button:Continue|continue]`;
    }
  };

  const handleFormFieldChange = (fieldId: string, value: any) => {
    setFormValues(prev => ({ ...prev, [fieldId]: value }));
  };

  const executeClientTool = async (toolCall: any, conversationHistory: Message[]) => {
    try {
      toast({
        title: "Executing Action",
        description: `Running ${toolCall.tool}...`,
      });

      const clientTools = window.SuperinterfaceClientTools;
      const tool = clientTools?.[toolCall.tool];

      if (!tool || !tool.handler) {
        throw new Error(`Unknown client tool: ${toolCall.tool}`);
      }

      const result = await tool.handler(toolCall.parameters);

      const toolResultResponse = await api.post('/api/v1/ai/tool-result', {
        tool_call_id: toolCall.id,
        result: result,
      });

      const toolResultData = toolResultResponse.data;
      
      const updatedConversation = [
        ...conversationHistory,
        { role: 'assistant' as const, content: toolResultData.reply }
      ];
      setConversation(updatedConversation);

      if (toolResultData.markdown || result.markdown) {
        setCurrentMarkdownForm(toolResultData.markdown || result.markdown);
      }

    } catch (error) {
      console.error('Tool execution error:', error);
      toast({
        title: "Error",
        description: "Failed to execute the requested action.",
        variant: "destructive"
      });
    }
  };

  const handleFormAction = async (action: string) => {
    switch (action) {
      case 'confirm_insurance':
        toast({
          title: "Insurance Confirmed",
          description: "Insurance information saved successfully.",
        });
        setShowDocumentUpload(false);
        break;
        
      case 'validate_clinical':
        toast({
          title: "Clinical Data Validated",
          description: "Clinical information saved successfully.",
        });
        break;
        
      default:
        console.log('Form action:', action);
    }
  };

  const toggleDocumentUpload = () => {
    setShowDocumentUpload(!showDocumentUpload);
  };

  if (!isVisible) return null;

  return (
    <MarkdownProvider>
      <div className="fixed inset-0 z-50 flex items-center justify-center animate-fade-in bg-black/50">
        <div className="absolute inset-0 cursor-pointer backdrop-blur-md" onClick={onClose} />

        <div className="relative w-full max-w-2xl mx-4 animate-scale-in">
          {/* Close button */}
          <Button
            onClick={onClose}
            className="absolute -top-14 right-0 bg-white/90 text-gray-700 hover:bg-white p-3 rounded-full transition-all duration-200 shadow-md border border-gray-200"
          >
            <X className="h-6 w-6" />
          </Button>

          <div className="bg-white/95 backdrop-blur-xl rounded-3xl shadow-2xl border border-gray-200 overflow-hidden">
            {/* Header */}
            <div className="bg-gradient-to-r from-blue-500 to-purple-600 text-white p-6">
              <h2 className="text-2xl font-bold">MSC AI Assistant</h2>
              <p className="text-white/90 mt-1">How can I help you today?</p>
            </div>

            {/* Main content area */}
            <div className="p-6 max-h-[70vh] overflow-y-auto">
              {/* Conversation or Form */}
              {currentMarkdownForm ? (
                <div className="bg-gray-50 rounded-xl p-6 mb-4">
              <MarkdownFormRenderer
                content={currentMarkdownForm}
                onFieldChange={handleFormFieldChange}
                onAction={handleFormAction}
                values={formValues}
              />
            </div>
              ) : (
                <div className="mb-4">
                  {conversation.length === 0 ? (
                    <div className="text-center py-8">
                      <p className="text-gray-600">Start by typing a message, recording your voice, or uploading a document.</p>
            </div>
                  ) : (
                    <ConversationHistory conversation={conversation} />
                  )}
                </div>
              )}

              {/* Document Upload */}
              {showDocumentUpload && (
                <div className="mb-4 bg-gray-50 rounded-xl p-4">
                  <div className="flex justify-between items-center mb-3">
                    <h3 className="font-semibold text-gray-800">Upload Documents</h3>
                    <Button onClick={toggleDocumentUpload} variant="ghost" size="sm">
                      <X className="h-4 w-4" />
                    </Button>
                  </div>
                  <DocumentUploadZone onDocumentsUploaded={handleDocumentsUploaded} />
                </div>
              )}
            </div>

            {/* Input area */}
            <div className="border-t border-gray-200 p-6 bg-gray-50">
              {/* Quick actions */}
              <div className="flex gap-2 mb-4">
                <Button
                  onClick={handleProductRequest}
                  variant="outline"
                  size="sm"
                  className="text-xs"
                >
                  New Product Request
                </Button>
                <Button
                  onClick={toggleDocumentUpload}
                  variant="outline"
                  size="sm"
                  className="text-xs"
                >
                  Upload Document
                </Button>
              </div>

              {/* Input row */}
              <div className="flex items-center gap-2">
                {/* Voice button */}
                <Button
                  onClick={handleVoiceToggle}
                  variant={isRecording ? "destructive" : "outline"}
                  size="icon"
                  disabled={isTranscribing}
                  className="shrink-0"
                >
                  {isRecording ? <MicOff className="h-4 w-4" /> : <Mic className="h-4 w-4" />}
                </Button>

              {/* Text input */}
                <input
                ref={inputRef}
                  type="text"
                value={message}
                  onChange={(e) => setMessage(e.target.value)}
                onKeyPress={handleKeyPress}
                  placeholder={isTranscribing ? "Transcribing..." : "Type your message..."}
                  disabled={isProcessing || isTranscribing}
                  className="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:opacity-50"
              />

              {/* Send button */}
              <Button
                onClick={handleSendMessage}
                  disabled={isProcessing || !message.trim()}
                  size="icon"
                  className="shrink-0"
                >
                  {isProcessing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
              </Button>
            </div>

              {/* Status */}
              {(isRecording || isTranscribing) && (
                <p className="text-xs text-gray-500 mt-2 text-center">
                  {isRecording ? "Recording... Click microphone to stop" : "Transcribing your speech..."}
                </p>
              )}
            </div>
          </div>
        </div>
      </div>
    </MarkdownProvider>
  );
};

export default AIOverlay;
