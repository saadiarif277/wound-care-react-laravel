
import React from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/Components/GhostAiUi/ui/button';
import { Slider } from '@/Components/GhostAiUi/ui/slider';

interface TransparencyControlsProps {
  showTransparencySlider: boolean;
  transparency: number[];
  onToggleSlider: () => void;
  onTransparencyChange: (value: number[]) => void;
}

const TransparencyControls: React.FC<TransparencyControlsProps> = ({
  showTransparencySlider,
  transparency,
  onToggleSlider,
  onTransparencyChange
}) => {
  return (
    <div className="flex items-center">
      {/* Transparency slider toggle */}
      <Button
        onClick={onToggleSlider}
        variant="ghost"
        size="sm"
        className="p-3 rounded-full bg-white/90 text-gray-700 hover:bg-white border border-gray-200 transition-all duration-200 shadow-sm hover:shadow-md"
      >
        {showTransparencySlider ? (
          <ChevronLeft className="h-4 w-4" />
        ) : (
          <ChevronRight className="h-4 w-4" />
        )}
      </Button>

      {/* Transparency slider - pops out to the right */}
      {showTransparencySlider && (
        <div className="ml-3 bg-white/95 backdrop-blur-xl rounded-2xl p-4 border border-gray-200 shadow-lg animate-slide-in-left">
          <div className="flex items-center space-x-3 min-w-[220px]">
            <span className="text-xs text-gray-700 w-20 font-medium">Transparency</span>
            <Slider
              value={transparency}
              onValueChange={onTransparencyChange}
              max={50}
              min={5}
              step={5}
              className="flex-1 [&_[role=slider]]:bg-gradient-to-r [&_[role=slider]]:from-msc-blue-500 [&_[role=slider]]:to-msc-red-500 [&_[role=slider]]:border-0 [&_[role=slider]]:shadow-md"
            />
            <span className="text-xs text-gray-700 w-10 font-medium text-right">{transparency[0]}%</span>
          </div>
        </div>
      )}
    </div>
  );
};

export default TransparencyControls;
