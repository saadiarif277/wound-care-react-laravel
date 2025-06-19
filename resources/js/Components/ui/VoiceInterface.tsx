import React, { useState, useCallback, useEffect } from 'react';
import { Mic, MicOff, Volume2, Loader2 } from 'lucide-react';
import { cn } from '@/theme/glass-theme';
import GlassCard from './GlassCard';

interface VoiceCommand {
  intent: string;
  entities: Record<string, any>;
  confidence: number;
}

interface VoiceInterfaceProps {
  onCommand: (command: VoiceCommand) => void;
  context?: 'episode' | 'order' | 'dashboard' | 'general';
  disabled?: boolean;
  className?: string;
}

/**
 * VoiceInterface - 2025 Healthcare Voice UX Component
 *
 * Features:
 * - Hands-free navigation for clinical environments
 * - Context-aware command recognition
 * - Healthcare-specific voice commands
 * - Accessibility compliance for voice-first interactions
 */
const VoiceInterface: React.FC<VoiceInterfaceProps> = ({
  onCommand,
  context = 'general',
  disabled = false,
  className
}) => {
  const [isListening, setIsListening] = useState(false);
  const [isProcessing, setIsProcessing] = useState(false);
  const [transcript, setTranscript] = useState('');
  const [confidence, setConfidence] = useState(0);

  // Healthcare-specific voice commands
  const healthcareCommands = {
    episode: [
      'show episode details',
      'update episode status',
      'generate IVR document',
      'send to manufacturer',
      'add tracking information'
    ],
    order: [
      'create new order',
      'check order status',
      'update patient information',
      'verify insurance coverage'
    ],
    dashboard: [
      'show today\'s episodes',
      'display urgent items',
      'open commission reports',
      'check pending approvals'
    ],
    general: [
      'navigate to orders',
      'search for patient',
      'open calendar',
      'show notifications'
    ]
  };

  const startListening = useCallback(async () => {
    if (disabled || !('webkitSpeechRecognition' in window)) return;

    setIsListening(true);
    const recognition = new (window as any).webkitSpeechRecognition();

    recognition.continuous = false;
    recognition.interimResults = true;
    recognition.lang = 'en-US';

    recognition.onstart = () => {
      setTranscript('');
      setConfidence(0);
    };

    recognition.onresult = (event: any) => {
      const result = event.results[event.results.length - 1];
      setTranscript(result[0].transcript);
      setConfidence(result[0].confidence);

      if (result.isFinal) {
        setIsProcessing(true);
        processVoiceCommand(result[0].transcript, result[0].confidence);
      }
    };

    recognition.onerror = (event: any) => {
      console.error('Voice recognition error:', event.error);
      setIsListening(false);
      setIsProcessing(false);
    };

    recognition.onend = () => {
      setIsListening(false);
      setIsProcessing(false);
    };

    recognition.start();
  }, [disabled, context]);

  const processVoiceCommand = async (transcript: string, confidence: number) => {
    try {
      // Simple intent recognition for healthcare commands
      const lowerTranscript = transcript.toLowerCase();
      let intent = 'unknown';
      let entities = {};

      // Episode management commands
      if (lowerTranscript.includes('episode')) {
        if (lowerTranscript.includes('show') || lowerTranscript.includes('display')) {
          intent = 'show_episode';
        } else if (lowerTranscript.includes('update') || lowerTranscript.includes('change')) {
          intent = 'update_episode';
        } else if (lowerTranscript.includes('generate ivr') || lowerTranscript.includes('create ivr')) {
          intent = 'generate_ivr';
        }
      }

      // Order management commands
      else if (lowerTranscript.includes('order')) {
        if (lowerTranscript.includes('create') || lowerTranscript.includes('new')) {
          intent = 'create_order';
        } else if (lowerTranscript.includes('status') || lowerTranscript.includes('check')) {
          intent = 'check_order_status';
        }
      }

      // Navigation commands
      else if (lowerTranscript.includes('navigate') || lowerTranscript.includes('go to')) {
        intent = 'navigate';
        const destination = extractDestination(lowerTranscript);
        entities = { destination };
      }

      const command: VoiceCommand = { intent, entities, confidence };
      onCommand(command);

    } catch (error) {
      console.error('Error processing voice command:', error);
    }
  };

  const extractDestination = (transcript: string): string => {
    const destinations = ['dashboard', 'orders', 'episodes', 'reports', 'settings'];
    return destinations.find(dest => transcript.includes(dest)) || 'dashboard';
  };

  const stopListening = () => {
    setIsListening(false);
    setIsProcessing(false);
  };

  const suggestedCommands = healthcareCommands[context] || healthcareCommands.general;

  return (
    <div className={cn('relative', className)}>
      {/* Voice Control Button */}
      <button
        onClick={isListening ? stopListening : startListening}
        disabled={disabled}
        className={cn(
          'flex items-center justify-center w-12 h-12 rounded-full transition-all duration-200',
          'border-2 backdrop-blur-md',
          isListening
            ? 'bg-red-500/20 border-red-400 text-red-600 animate-pulse'
            : 'bg-blue-500/20 border-blue-400 text-blue-600 hover:bg-blue-500/30',
          disabled && 'opacity-50 cursor-not-allowed'
        )}
        aria-label={isListening ? 'Stop voice command' : 'Start voice command'}
      >
        {isProcessing ? (
          <Loader2 className="w-5 h-5 animate-spin" />
        ) : isListening ? (
          <MicOff className="w-5 h-5" />
        ) : (
          <Mic className="w-5 h-5" />
        )}
      </button>

      {/* Voice Feedback Display */}
      {(isListening || transcript) && (
        <GlassCard className="absolute top-14 left-0 z-50 min-w-96 p-4">
          <div className="space-y-3">
            <div className="flex items-center space-x-2">
              <Volume2 className="w-4 h-4 text-blue-600" />
              <span className="text-sm font-medium text-gray-900">
                {isListening ? 'Listening...' : 'Processing...'}
              </span>
              {confidence > 0 && (
                <span className="text-xs text-gray-500">
                  ({Math.round(confidence * 100)}% confidence)
                </span>
              )}
            </div>

            {transcript && (
              <div className="p-3 bg-blue-50 rounded-lg">
                <p className="text-sm text-gray-800">{transcript}</p>
              </div>
            )}

            <div className="border-t pt-3">
              <p className="text-xs text-gray-600 mb-2">Try saying:</p>
              <ul className="text-xs text-gray-500 space-y-1">
                {suggestedCommands.slice(0, 3).map((command, index) => (
                  <li key={index}>• "{command}"</li>
                ))}
              </ul>
            </div>
          </div>
        </GlassCard>
      )}
    </div>
  );
};

export default VoiceInterface;
