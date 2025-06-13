
import React from 'react';
import { MessageCircle } from 'lucide-react';
import { Button } from '@/Components/GhostAiUi/ui/button';

interface FloatingAIButtonProps {
  onClick: () => void;
}

const FloatingAIButton: React.FC<FloatingAIButtonProps> = ({ onClick }) => {
  return (
    <Button
      onClick={onClick}
      className="fixed bottom-6 right-6 z-40 h-14 w-14 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 shadow-lg hover:shadow-xl transition-all duration-300 border-0 group"
      size="sm"
    >
      <MessageCircle className="h-6 w-6 text-white group-hover:scale-110 transition-transform duration-200" />
      <span className="sr-only">Open AI Assistant</span>
    </Button>
  );
};

export default FloatingAIButton;
