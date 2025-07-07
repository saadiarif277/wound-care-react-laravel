import React from 'react';
import { Mic, MessageSquare, Sparkles } from 'lucide-react';
import { Button } from '@/Components/GhostAiUi/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/Components/GhostAiUi/ui/tooltip';

interface ModeSwitcherProps {
  currentMode: 'voice' | 'text';
  onModeChange: (mode: 'voice' | 'text') => void;
  isVoiceAvailable: boolean;
}

const ModeSwitcher: React.FC<ModeSwitcherProps> = ({ 
  currentMode, 
  onModeChange, 
  isVoiceAvailable 
}) => {
  return (
    <TooltipProvider>
      <div className="flex items-center gap-2 p-2 bg-white/10 backdrop-blur-sm rounded-full border border-white/20">
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              variant={currentMode === 'voice' ? 'default' : 'ghost'}
              size="sm"
              onClick={() => onModeChange('voice')}
              disabled={!isVoiceAvailable}
              className={`
                relative transition-all duration-200
                ${currentMode === 'voice' 
                  ? 'bg-blue-600 hover:bg-blue-700 text-white' 
                  : 'text-gray-600 hover:text-gray-900'
                }
                ${!isVoiceAvailable && 'opacity-50 cursor-not-allowed'}
              `}
            >
              <Mic className="h-4 w-4 mr-1" />
              Voice
              {currentMode === 'voice' && (
                <Sparkles className="h-3 w-3 absolute -top-1 -right-1 text-yellow-300 animate-pulse" />
              )}
            </Button>
          </TooltipTrigger>
          <TooltipContent>
            <div className="text-sm">
              <p className="font-semibold">Voice Mode (Hands-free)</p>
              <p className="text-gray-400">Natural conversation with ultra-low latency</p>
              {!isVoiceAvailable && (
                <p className="text-red-400 mt-1">Voice mode not available</p>
              )}
            </div>
          </TooltipContent>
        </Tooltip>

        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              variant={currentMode === 'text' ? 'default' : 'ghost'}
              size="sm"
              onClick={() => onModeChange('text')}
              className={`
                transition-all duration-200
                ${currentMode === 'text' 
                  ? 'bg-green-600 hover:bg-green-700 text-white' 
                  : 'text-gray-600 hover:text-gray-900'
                }
              `}
            >
              <MessageSquare className="h-4 w-4 mr-1" />
              Text
            </Button>
          </TooltipTrigger>
          <TooltipContent>
            <div className="text-sm">
              <p className="font-semibold">Text Mode</p>
              <p className="text-gray-400">Upload documents, forms, and detailed interactions</p>
            </div>
          </TooltipContent>
        </Tooltip>
      </div>
    </TooltipProvider>
  );
};

export default ModeSwitcher; 