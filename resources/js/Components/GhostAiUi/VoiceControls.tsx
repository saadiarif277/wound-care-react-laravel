
import React from 'react';
import { Mic, MicOff, VolumeX } from 'lucide-react';
import { Button } from '@/Components/GhostAiUi/ui/button';

interface VoiceControlsProps {
  isListening: boolean;
  isSpeaking: boolean;
  onToggleVoiceRecording: () => void;
  onStopSpeaking: () => void;
}

const VoiceControls: React.FC<VoiceControlsProps> = ({
  isListening,
  isSpeaking,
  onToggleVoiceRecording,
  onStopSpeaking
}) => {
  const handleMicClick = () => {
    console.log('VoiceControls: Microphone button clicked, isListening:', isListening);
    onToggleVoiceRecording();
  };
  
  return (
    <>
      {/* Voice button */}
      <Button
        onClick={handleMicClick}
        variant="ghost"
        size="sm"
        className={`p-4 rounded-2xl transition-all duration-200 ${
          isListening
            ? 'bg-red-500 text-white hover:bg-red-600 animate-pulse shadow-md'
            : 'bg-white/90 text-gray-700 hover:bg-white border border-gray-200 shadow-sm'
        }`}
      >
        {isListening ? (
          <MicOff className="h-5 w-5 animate-pulse" />
        ) : (
          <Mic className="h-5 w-5" />
        )}
      </Button>

      {/* Speaking controls */}
      {isSpeaking && (
        <Button
          onClick={onStopSpeaking}
          variant="ghost"
          size="sm"
          className="p-4 rounded-2xl bg-green-500 text-white hover:bg-green-600 transition-all duration-200 shadow-md"
        >
          <VolumeX className="h-5 w-5" />
        </Button>
      )}
    </>
  );
};

export default VoiceControls;
