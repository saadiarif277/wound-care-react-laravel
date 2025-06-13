
import React from 'react';

interface VoiceVisualizerProps {
  isListening: boolean;
}

const VoiceVisualizer: React.FC<VoiceVisualizerProps> = ({ isListening }) => {
  if (!isListening) return null;

  return (
    <div className="flex justify-center mb-4">
      <div className="flex items-center space-x-1">
        {[...Array(5)].map((_, i) => (
          <div
            key={i}
            className="w-1 bg-blue-400 rounded-full animate-pulse"
            style={{
              height: Math.random() * 20 + 10,
              animationDelay: `${i * 0.1}s`,
              animationDuration: '0.6s'
            }}
          />
        ))}
      </div>
    </div>
  );
};

export default VoiceVisualizer;
