import React, { useState, useRef, useEffect } from 'react';
import { Send, X } from 'lucide-react';
import { Button } from '@/Components/GhostAiUi/ui/button';
import { Input } from '@/Components/GhostAiUi/ui/input';
import { useToast } from '@/Components/GhostAiUi/hooks/use-toast';
import { useSpeech } from '@/Components/GhostAiUi/hooks/useSpeech';
import VoiceVisualizer from './VoiceVisualizer';
import EscalationControls from './EscalationControls';
import ConversationHistory from './ConversationHistory';
import VoiceControls from './VoiceControls';
import TransparencyControls from './TransparencyControls';
import ActionButtons from './ActionButtons';
import StatusIndicators from './StatusIndicators';

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

    // Check if we're in escalation mode
    if (isEscalating) {
      setIsEscalating(false); // Reset escalation mode
      const success = await escalateToIntercom(userMessage);
      setIsProcessing(false);

      if (success) {
        // Add a system message to show escalation occurred
        const escalationMessage = {
          role: 'assistant' as const,
          content: `Your message "${userMessage}" has been sent to our support team. A live agent will contact you soon.`
        };
        setConversation(prev => [...prev, { role: 'user' as const, content: userMessage }, escalationMessage]);
      }
      return;
    }

    // Normal AI processing
    const newConversation = [...conversation, { role: 'user' as const, content: userMessage }];
    setConversation(newConversation);

    try {
      // Simulate AI response (replace with actual AI API call)
      await new Promise(resolve => setTimeout(resolve, 1000));

      const aiResponse = `I understand you said: "${userMessage}". This is a demo response. I can see the screen behind this overlay and I'm ready to help you with anything you need.`;

      const updatedConversation = [...newConversation, { role: 'assistant' as const, content: aiResponse }];
      setConversation(updatedConversation);

      // Speak the response
      speak(aiResponse);

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

  const handleProductRequest = () => {
    const productRequestMessage = "I'd like to submit a new product request. Please walk me through the required fields.";
    setMessage(productRequestMessage);
    // Auto-send the message
    handleSendMessage();
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

  if (!isVisible) return null;

  const backgroundOpacity = transparency[0] / 100;

  return (
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
              disabled={!message.trim() || isProcessing}
              className="p-4 rounded-2xl bg-msc-blue-500 text-white hover:bg-msc-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 shadow-md"
            >
              <Send className="h-5 w-5" />
            </Button>
          </div>

          {/* Action buttons */}
          <ActionButtons
            isRecordingClinicalNotes={isRecordingClinicalNotes}
            onProductRequest={handleProductRequest}
            onClinicalNotes={handleClinicalNotes}
          />

          {/* Status indicators */}
          <StatusIndicators
            isProcessing={isProcessing}
            isSpeaking={isSpeaking}
            isRecordingClinicalNotes={isRecordingClinicalNotes}
          />

          {/* Transparency controls - bottom left */}
          <div className="absolute bottom-6 left-6 z-10">
            <TransparencyControls
              showTransparencySlider={showTransparencySlider}
              transparency={transparency}
              onToggleSlider={() => setShowTransparencySlider(!showTransparencySlider)}
              onTransparencyChange={setTransparency}
            />
          </div>
        </div>
      </div>
    </div>
  );
};

export default AIOverlay;
