import React, { useEffect, useState } from 'react';
import { Theme } from '@radix-ui/themes';
import '@radix-ui/themes/styles.css';
import {
  QueryClient,
  QueryClientProvider,
} from '@tanstack/react-query';
import { useTheme } from '@/contexts/ThemeContext';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 10000,
    },
  },
});

interface VoiceAssistantProps {
  isVisible: boolean;
  onClose: () => void;
}

const VoiceAssistantContent: React.FC<VoiceAssistantProps> = ({ isVisible, onClose }) => {
  const [isLoaded, setIsLoaded] = useState(false);

  useEffect(() => {
    if (isVisible) {
      // Give the providers time to initialize
      setTimeout(() => setIsLoaded(true), 100);
    } else {
      setIsLoaded(false);
    }
  }, [isVisible]);

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isVisible) {
        onClose();
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [onClose, isVisible]);

  if (!isVisible) return null;

  return (
    <>
      {/* Custom backdrop */}
      <div
        className="fixed inset-0 z-40 bg-black/60 backdrop-blur-md animate-fade-in"
        onClick={onClose}
      />

      {/* Custom positioning wrapper */}
      <div className="fixed inset-0 z-50 flex items-center justify-center">
        <div className="bg-gray-900 text-white rounded-lg p-8 shadow-xl min-w-[400px]">
          <h2 className="text-xl mb-4">Voice Assistant {isLoaded ? 'Ready' : 'Loading...'}</h2>
          <div className="mb-4">
            <p className="text-sm text-gray-400">Status: {isLoaded ? 'Initialized' : 'Initializing providers...'}</p>
          </div>
          <div className="border border-gray-700 rounded p-4 mb-4 min-h-[200px] flex items-center justify-center">
            {isLoaded ? (
              <>
                <AudioThreadDialog />
                <div className="mt-4 text-xs text-gray-500">
                  <p>If dialog doesn't appear, try:</p>
                  <ul className="list-disc list-inside">
                    <li>Check browser microphone permissions</li>
                    <li>Ensure HTTPS connection</li>
                    <li>Check console for errors</li>
                  </ul>
                </div>
              </>
            ) : (
              <div className="text-center">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-white mx-auto mb-2"></div>
                <p className="text-sm text-gray-400">Loading audio interface...</p>
              </div>
            )}
          </div>
          <button
            onClick={onClose}
            className="mt-4 px-4 py-2 bg-blue-600 rounded hover:bg-blue-700 w-full"
          >
            Close (ESC)
          </button>
        </div>
      </div>

      {/* Custom styles to integrate with glassmorphic theme */}
      <style>{`
        /* Override Radix UI Theme styles for glassmorphic look */
        .rt-Dialog-root {
          background: rgba(42, 45, 58, 0.95) !important;
          backdrop-filter: blur(20px) !important;
          border: 1px solid rgba(255, 255, 255, 0.1) !important;
          box-shadow: 0 8px 40px 0 rgba(0,0,0,.18) !important;
        }

        /* Style the audio dialog content */
        .rt-DialogContent {
          background: transparent !important;
        }

        /* Override button styles */
        .rt-Button {
          transition: all 0.2s !important;
        }

        .rt-Button:hover {
          transform: translateY(-1px) !important;
          box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
        }

        /* Animation classes */
        @keyframes fade-in {
          from { opacity: 0; }
          to { opacity: 1; }
        }

        @keyframes scale-in {
          from {
            opacity: 0;
            transform: scale(0.9);
          }
          to {
            opacity: 1;
            transform: scale(1);
          }
        }

        .animate-fade-in {
          animation: fade-in 0.2s ease-out;
        }

        .animate-scale-in {
          animation: scale-in 0.2s ease-out;
        }
      `}</style>
    </>
  );
};

const VoiceAssistant: React.FC<VoiceAssistantProps> = (props) => {
  const { theme } = useTheme();

  return (
    <QueryClientProvider client={queryClient}>
      <Theme
        accentColor="blue"
        grayColor="gray"
        appearance={theme}
        radius="medium"
        scaling="110%"
        panelBackground="solid"
      >
        <SuperinterfaceProvider
          variables={{
            publicApiKey: '070a42e4-90e4-4a5a-aaff-0dc1269c45dc',
            assistantId: 'e1ae3db4-1c08-43dd-a91a-aa40629f0785',
          }}
        >
          <AssistantProvider>
            <WebrtcAudioRuntimeProvider>
              <VoiceAssistantContent {...props} />
            </WebrtcAudioRuntimeProvider>
          </AssistantProvider>
        </SuperinterfaceProvider>
      </Theme>
    </QueryClientProvider>
  );
};

export default VoiceAssistant;