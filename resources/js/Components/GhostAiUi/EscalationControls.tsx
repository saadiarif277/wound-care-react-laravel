
import React from 'react';
import { X, HelpCircle } from 'lucide-react';
import { Button } from '@/Components/GhostAiUi/ui/button';

interface EscalationControlsProps {
  isEscalating: boolean;
  onEscalate: () => void;
  onCancelEscalation: () => void;
}

const EscalationControls: React.FC<EscalationControlsProps> = ({
  isEscalating,
  onEscalate,
  onCancelEscalation
}) => {
  return (
    <>
      {/* Escalation button */}
      <Button
        onClick={onEscalate}
        variant="ghost"
        size="sm"
        className={`absolute -bottom-16 left-0 p-2 rounded-full border border-white/20 transition-all duration-200 ${
          isEscalating
            ? 'bg-orange-500/20 text-orange-300 hover:bg-orange-500/30 border-orange-400/30'
            : 'bg-white/10 text-white/70 hover:bg-white/20 hover:text-white'
        }`}
        title={isEscalating ? 'Next message goes to human support' : 'Get human help'}
      >
        <HelpCircle className="h-4 w-4" />
      </Button>

      {/* Cancel escalation button */}
      {isEscalating && (
        <Button
          onClick={onCancelEscalation}
          variant="ghost"
          size="sm"
          className="absolute -bottom-16 left-12 p-0.5 rounded-full bg-red-500/20 text-red-300 hover:bg-red-500/30 border border-red-400/30 transition-all duration-200"
          title="Cancel escalation"
        >
          <X className="h-2.5 w-2.5" />
        </Button>
      )}
    </>
  );
};

export default EscalationControls;
