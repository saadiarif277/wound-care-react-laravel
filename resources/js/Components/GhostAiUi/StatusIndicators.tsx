import React from 'react';
import { Volume2 } from 'lucide-react';

interface StatusIndicatorsProps {
  isProcessing: boolean;
  isSpeaking: boolean;
  isRecordingClinicalNotes: boolean;
}

const StatusIndicators: React.FC<StatusIndicatorsProps> = ({
  isProcessing,
  isSpeaking,
  isRecordingClinicalNotes
}) => {
  return (
    <div className="mt-4 flex items-center justify-between text-xs">
      <div className="flex items-center space-x-4 text-gray-400">
        {isProcessing && (
          <span className="flex items-center space-x-1.5">
            <div className="w-2 h-2 bg-msc-blue-500 rounded-full animate-pulse" />
            <span>Processing...</span>
          </span>
        )}
        {isSpeaking && (
          <span className="flex items-center space-x-1.5">
            <Volume2 className="h-3 w-3 animate-pulse text-green-500" />
            <span>Speaking...</span>
          </span>
        )}
        {isRecordingClinicalNotes && (
          <span className="flex items-center space-x-1.5">
            <div className="w-2 h-2 bg-red-500 rounded-full animate-pulse" />
            <span>Recording Clinical Notes...</span>
          </span>
        )}
      </div>
      <span className="text-gray-500">Press Esc to close</span>
    </div>
  );
};

export default StatusIndicators;
