import React, { useState, useRef, useEffect } from 'react';
import { Send, X } from 'lucide-react';
import { Button } from '@/Components/GhostAiUi/ui/button';
import { Input } from '@/Components/GhostAiUi/ui/input';
import { useToast } from './hooks/use-toast';
import { useSpeech } from './hooks/useSpeech';
import VoiceVisualizer from './VoiceVisualizer';
import EscalationControls from './EscalationControls';
import ConversationHistory from './ConversationHistory';
import VoiceControls from './VoiceControls';
import TransparencyControls from './TransparencyControls';
import ActionButtons from './ActionButtons';
import StatusIndicators from './StatusIndicators';
import DocumentUploadZone from './DocumentUploadZone';
import MarkdownFormRenderer from './MarkdownFormRenderer';
import { fetchWithCSRF } from '@/utils/csrf';
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
  const [transparency, setTransparency] = useState([20]);
  const [showTransparencySlider, setShowTransparencySlider] = useState(false);
  const [isRecordingClinicalNotes, setIsRecordingClinicalNotes] = useState(false);
  const [isEscalating, setIsEscalating] = useState(false);
  const [showDocumentUpload, setShowDocumentUpload] = useState(false);
  const [currentMarkdownForm, setCurrentMarkdownForm] = useState('');
  const [formValues, setFormValues] = useState<Record<string, any>>({});

  const inputRef = useRef<HTMLInputElement>(null);
  const { toast } = useToast();
  const { isListening, isSpeaking, toggleVoiceRecording, speak, stopSpeaking, recognitionRef } = useSpeech();

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

  const handleVoiceToggle = () => {
    toggleVoiceRecording((transcript: string) => {
      setMessage(transcript);
    });
  };

  const handleEscalationClick = () => {
    setIsEscalating(true);
    toast({
      title: "Human Support",
      description: "Your next message will be sent to a live support agent.",
    });
  };

  const handleCancelEscalation = () => {
    setIsEscalating(false);
    toast({
      title: "Cancelled",
      description: "Escalation cancelled. Messages will go to AI again.",
    });
  };

  const escalateToIntercom = async (userMessage: string) => {
    try {
      // This would be your actual Intercom API call
      // Replace with your actual API endpoint
      const response = await fetch('/api/support/escalate', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          message: userMessage,
          metadata: {
            screen: 'AI Overlay',
            triggered_by: 'help_button',
            timestamp: new Date().toISOString(),
          },
        }),
      });

      if (response.ok) {
        toast({
          title: "Message Sent",
          description: "A support agent will reach out to you soon.",
        });
        return true;
      } else {
        throw new Error('Failed to escalate');
      }
    } catch (error) {
      console.error('Escalation failed:', error);
      toast({
        title: "Error",
        description: "Could not reach support. Please try again.",
        variant: "destructive"
      });
      return false;
    }
  };

  const handleSendMessage = async () => {
    if (!message.trim() || isProcessing) return;

    const userMessage = message.trim();
    setMessage('');
    setIsProcessing(true);

    if (isEscalating) {
      setIsEscalating(false);
      const success = await escalateToIntercom(userMessage);
      setIsProcessing(false);
      if (success) {
        const escalationMessage = {
          role: 'assistant' as const,
          content: `Your message "${userMessage}" has been sent to our support team. A live agent will contact you soon.`
        };
        setConversation(prev => [...prev, { role: 'user' as const, content: userMessage }, escalationMessage]);
      }
      return;
    }

    const newConversation = [...conversation, { role: 'user' as const, content: userMessage }];
    setConversation(newConversation);

    try {
      const response = await fetchWithCSRF('/api/v1/ai/chat', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
          message: userMessage,
          conversation_history: newConversation 
        }),
      });

      if (!response.ok) {
        throw new Error('AI response error');
      }

      const result = await response.json();
      const aiResponse = result.reply;

      // Check if AI wants to use a client tool
      if (result.tool_call) {
        // Execute the client tool
        await executeClientTool(result.tool_call, newConversation);
      } else {
        // Regular response
        const updatedConversation = [...newConversation, { role: 'assistant' as const, content: aiResponse }];
        setConversation(updatedConversation);

        if (result.markdown) {
          setCurrentMarkdownForm(result.markdown);
        }

        speak(aiResponse);
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
    // Wait a moment for the message to be set
    setTimeout(() => {
      handleSendMessage();
    }, 100);
    toast({
      title: "Product Request",
      description: "Starting product request process...",
    });
  };

  const handleClinicalNotes = () => {
    if (!isRecordingClinicalNotes) {
      setIsRecordingClinicalNotes(true);
      handleVoiceToggle();
      toast({
        title: "Clinical Notes",
        description: "Recording clinical notes... Click again to stop.",
      });
    } else {
      setIsRecordingClinicalNotes(false);
      if (isListening) {
        recognitionRef.current?.stop();
      }
      // Show attachment options
      toast({
        title: "Clinical Notes Complete",
        description: "Would you like to attach this to a current or new order?",
      });
    }
  };

  const handleDocumentsUploaded = async (documents: any[]) => {
    // Process each document with OCR
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

      const response = await fetchWithCSRF('/api/v1/document/analyze', {
        method: 'POST',
        body: formData,
      });

      if (response.ok) {
        const result = await response.json();
        
        // Generate markdown form for the extracted data
        const markdown = generateMarkdownForm(document.type, result.data);
        setCurrentMarkdownForm(markdown);
        
        // Pre-fill form values
        if (result.data) {
          setFormValues(prev => ({ ...prev, ...result.data }));
        }

        toast({
          title: "Document Processed",
          description: `Successfully extracted data from ${document.file.name}`,
        });
      } else {
        throw new Error('Failed to process document');
      }
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
- Plan Type: [select:primary_plan_type|FFS|HMO|PPO|Medicare Advantage|${extractedData.plan_type || ''}]

**Patient Information**
- First Name: [input:patient_first_name|${extractedData.patient_first_name || ''}]
- Last Name: [input:patient_last_name|${extractedData.patient_last_name || ''}]
- Date of Birth: [date:patient_dob|${extractedData.patient_dob || ''}]

Missing information? Fill in below:
- Phone: [input:patient_phone|${extractedData.patient_phone || ''}]
- Email: [input:patient_email|${extractedData.patient_email || ''}]

[button:Confirm Information|confirm_insurance]
[button:Upload Another Card|upload_more]`;

      case 'clinical_note':
        return `## Clinical Information Extracted

We found the following diagnoses:

**Primary Diagnosis**
- ICD-10: [input:primary_diagnosis|${extractedData.primary_diagnosis || ''}]
- Description: [textarea:diagnosis_description|${extractedData.diagnosis_description || ''}]

**Wound Details**
- Location: [select:wound_location|Foot|Leg|Arm|Other|${extractedData.wound_location || ''}]
- Size (cm): [input:wound_size|${extractedData.wound_size || ''}]
- Duration (weeks): [input:duration_weeks|${extractedData.duration_weeks || ''}]

[button:Add Another Wound|add_wound]
[button:Continue|validate_clinical]`;

      case 'wound_photo':
        return `## Wound Photo Analysis

**Wound Measurements Detected**
- Length: [input:wound_length|${extractedData.length || ''}] cm
- Width: [input:wound_width|${extractedData.width || ''}] cm
- Depth: [input:wound_depth|${extractedData.depth || ''}] cm

**Wound Characteristics**
- Type: [select:wound_type|Pressure Ulcer|Diabetic Foot Ulcer|Venous Ulcer|Arterial Ulcer|${extractedData.wound_type || ''}]
- Stage: [select:wound_stage|Stage 1|Stage 2|Stage 3|Stage 4|${extractedData.stage || ''}]

[button:Confirm Measurements|confirm_wound]
[button:Add Another Photo|upload_more]`;

      default:
        return `## Document Uploaded

Document: ${extractedData.filename || 'Document'}
Type: ${documentType}

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

      // Get the tool handler from window.SuperinterfaceClientTools
      const clientTools = window.SuperinterfaceClientTools;
      const tool = clientTools?.[toolCall.tool];

      if (!tool || !tool.handler) {
        throw new Error(`Unknown client tool: ${toolCall.tool}`);
      }

      // Execute the tool
      const result = await tool.handler(toolCall.parameters);

      // Send the result back to the AI
      const toolResultResponse = await fetchWithCSRF('/api/v1/ai/tool-result', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          tool: toolCall.tool,
          result: result,
          conversation_history: conversationHistory
        }),
      });

      if (!toolResultResponse.ok) {
        throw new Error('Failed to process tool result');
      }

      const toolResult = await toolResultResponse.json();
      
      // Add AI's response about the tool result
      const updatedConversation = [
        ...conversationHistory,
        { role: 'assistant' as const, content: toolResult.reply }
      ];
      setConversation(updatedConversation);

      // Update markdown form if provided
      if (toolResult.markdown || result.markdown) {
        setCurrentMarkdownForm(toolResult.markdown || result.markdown);
      }

      speak(toolResult.reply);

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
        // Save insurance data and move to next step
        toast({
          title: "Insurance Confirmed",
          description: "Insurance information saved successfully.",
        });
        setShowDocumentUpload(false);
        break;
        
      case 'upload_more':
        setCurrentMarkdownForm('');
        break;
        
      case 'validate_clinical':
        // Validate clinical data
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
    if (!showDocumentUpload) {
      toast({
        title: "Document Upload",
        description: "Upload insurance cards, clinical notes, or other documents",
      });
    }
  };

  if (!isVisible) return null;

  const backgroundOpacity = (transparency[60] ?? transparency[0] ?? 20) / 100;

  return (
    <MarkdownProvider>
      <div
        className="fixed inset-0 z-50 flex items-center justify-center animate-fade-in"
        style={{ backgroundColor: `rgba(0, 0, 0, ${Math.max(backgroundOpacity, 0.4)})` }}
      >
        {/* Background overlay */}
        <div
          className="absolute inset-0 cursor-pointer backdrop-blur-md"
          onClick={onClose}
        />

        {/* Main interface container */}
        <div className="relative w-full max-w-2xl mx-4 animate-scale-in">
          {/* Close button */}
          <Button
            onClick={onClose}
            className="absolute -top-14 right-0 bg-white/90 text-gray-700 hover:bg-white p-3 rounded-full transition-all duration-200 shadow-md border border-gray-200"
          >
            <X className="h-6 w-6" />
          </Button>

          {/* Escalation controls */}
          <EscalationControls
            isEscalating={isEscalating}
            onEscalate={handleEscalationClick}
            onCancelEscalation={handleCancelEscalation}
          />

          {/* Conversation history */}
          <ConversationHistory conversation={conversation} />

          {/* Document Upload Section */}
          {showDocumentUpload && (
            <div className="mb-4 bg-white/95 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-gray-200">
              <div className="flex justify-between items-center mb-4">
                <h3 className="text-lg font-semibold text-gray-800">Upload Documents</h3>
                <Button
                  onClick={toggleDocumentUpload}
                  variant="ghost"
                  size="sm"
                >
                  <X className="h-4 w-4" />
                </Button>
              </div>
              <DocumentUploadZone onDocumentsUploaded={handleDocumentsUploaded} />
            </div>
          )}

          {/* Markdown Form Display */}
          {currentMarkdownForm && (
            <div className="mb-4 bg-white/95 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-gray-200">
              <MarkdownFormRenderer
                content={currentMarkdownForm}
                onFieldChange={handleFormFieldChange}
                onAction={handleFormAction}
                values={formValues}
              />
            </div>
          )}

          {/* Main input container */}
          <div className="bg-white/5 backdrop-blur-2xl rounded-3xl p-8 shadow-2xl border border-white/10 relative overflow-hidden">
            {/* Subtle gradient overlay */}
            <div className="absolute inset-0 bg-gradient-to-br from-white/10 via-transparent to-white/5 rounded-3xl pointer-events-none" />
            {/* Voice visualizer */}
            <div className="relative z-10">
              <VoiceVisualizer isListening={isListening} />
            </div>

            {/* Escalation indicator */}
            {isEscalating && (
              <div className="mb-4 p-2 bg-orange-500/20 border border-orange-400/30 rounded-lg">
                <p className="text-xs text-orange-200 text-center">
                  🙋‍♀️ Next message will be sent to a live support agent
                </p>
              </div>
            )}

            {/* Input area */}
            <div className="flex items-center space-x-3">
              {/* Voice controls */}
              <VoiceControls
                isListening={isListening}
                isSpeaking={isSpeaking}
                onToggleVoiceRecording={handleVoiceToggle}
                onStopSpeaking={stopSpeaking}
              />

              {/* Text input */}
              <Input
                ref={inputRef}
                value={message}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) => setMessage(e.target.value)}
                onKeyPress={handleKeyPress}
                placeholder={isListening ? "Listening..." : "Ask me anything..."}
                className="flex-1 bg-white/90 border border-gray-200 text-gray-900 placeholder-gray-400 focus:border-msc-blue-500 focus:ring-msc-blue-500/20 focus:ring-2 rounded-2xl h-14 px-5 shadow-sm"
                disabled={isListening || isProcessing}
              />

              {/* Send button */}
              <Button
                onClick={handleSendMessage}
                className="bg-msc-blue-500 hover:bg-msc-blue-600 text-white rounded-full h-14 w-14 flex items-center justify-center shadow-lg transition-transform duration-200 active:scale-95"
                disabled={isProcessing}
              >
                <Send className="h-6 w-6" />
              </Button>
            </div>

            {/* Action buttons */}
            <ActionButtons
              onProductRequest={handleProductRequest}
              onClinicalNotes={handleClinicalNotes}
              onDocumentUpload={toggleDocumentUpload}
              isRecordingClinicalNotes={isRecordingClinicalNotes}
            />

            {/* Status indicators and transparency controls */}
            <div className="flex justify-between items-center mt-6">
              <StatusIndicators
                isProcessing={isProcessing}
                isSpeaking={isSpeaking}
                isRecordingClinicalNotes={isRecordingClinicalNotes}
              />
              <TransparencyControls
                showTransparencySlider={showTransparencySlider}
                onToggleSlider={() => setShowTransparencySlider(!showTransparencySlider)}
                transparency={transparency}
                onTransparencyChange={setTransparency}
              />
            </div>
          </div>
        </div>
      </div>
    </MarkdownProvider>
  );
};

export default AIOverlay;
