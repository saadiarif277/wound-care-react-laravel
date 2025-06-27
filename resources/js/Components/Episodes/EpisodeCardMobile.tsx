import React, { useState, useRef, TouchEvent } from 'react';
import { 
  Send, 
  Eye, 
  Phone,
  ChevronLeft,
  ChevronRight
} from 'lucide-react';
import { cn } from '@/theme/glass-theme';
import { useTheme } from '@/contexts/ThemeContext';
import EpisodeCard from './EpisodeCard';

interface EpisodeCardMobileProps {
  episode: any;
  onRefresh?: () => void;
  onSendIVR?: () => void;
  onViewDetails?: () => void;
  onContact?: () => void;
}

const EpisodeCardMobile: React.FC<EpisodeCardMobileProps> = ({
  episode,
  onRefresh,
  onSendIVR,
  onViewDetails,
  onContact
}) => {
  const [swipeOffset, setSwipeOffset] = useState(0);
  const [isSwipping, setIsSwipping] = useState(false);
  const startX = useRef(0);
  const cardRef = useRef<HTMLDivElement>(null);

  let theme: 'dark' | 'light' = 'dark';

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
  } catch (e) {
    // Fallback to dark theme
  }

  const handleTouchStart = (e: TouchEvent) => {
    const touch = e.touches[0];
    if (touch) {
      startX.current = touch.clientX;
      setIsSwipping(true);
    }
  };

  const handleTouchMove = (e: TouchEvent) => {
    if (!isSwipping) return;
    const touch = e.touches[0];
    if (!touch) return;
    
    const currentX = touch.clientX;
    const diff = startX.current - currentX;
    
    // Limit swipe distance
    if (diff > 0 && diff < 200) {
      setSwipeOffset(-diff);
    } else if (diff < 0 && diff > -200) {
      setSwipeOffset(-diff);
    }
  };

  const handleTouchEnd = () => {
    setIsSwipping(false);
    
    // Snap to action or reset
    if (Math.abs(swipeOffset) > 100) {
      // Trigger action based on swipe direction
      if (swipeOffset > 0) {
        // Right swipe - Quick approve
        onSendIVR?.();
      } else {
        // Left swipe - View details
        onViewDetails?.();
      }
    }
    
    // Reset position
    setTimeout(() => setSwipeOffset(0), 300);
  };

  // Quick action buttons revealed on swipe
  const leftActions = (
    <div className={cn(
      "absolute left-0 top-0 bottom-0 w-24 flex items-center justify-center",
      "bg-gradient-to-r from-green-500 to-green-600",
      "transition-opacity duration-300",
      swipeOffset > 50 ? 'opacity-100' : 'opacity-0'
    )}>
      <div className="text-white text-center">
        <Send className="w-6 h-6 mx-auto mb-1" />
        <span className="text-xs font-medium">Send IVR</span>
      </div>
    </div>
  );

  const rightActions = (
    <div className={cn(
      "absolute right-0 top-0 bottom-0 w-24 flex items-center justify-center",
      "bg-gradient-to-r from-blue-500 to-blue-600",
      "transition-opacity duration-300",
      swipeOffset < -50 ? 'opacity-100' : 'opacity-0'
    )}>
      <div className="text-white text-center">
        <Eye className="w-6 h-6 mx-auto mb-1" />
        <span className="text-xs font-medium">Details</span>
      </div>
    </div>
  );

  return (
    <div className="relative overflow-hidden rounded-xl">
      {/* Swipe action backgrounds */}
      {leftActions}
      {rightActions}

      {/* Main card with swipe */}
      <div
        ref={cardRef}
        className={cn(
          "relative transition-transform duration-300",
          isSwipping ? '' : 'ease-out'
        )}
        style={{ transform: `translateX(${swipeOffset}px)` }}
        onTouchStart={handleTouchStart}
        onTouchMove={handleTouchMove}
        onTouchEnd={handleTouchEnd}
      >
        <EpisodeCard
          episode={episode}
          onRefresh={onRefresh}
          viewMode="compact"
        />
      </div>

      {/* Swipe indicators */}
      {!isSwipping && Math.abs(swipeOffset) < 10 && (
        <>
          <div className={cn(
            "absolute left-2 top-1/2 -translate-y-1/2",
            "text-white/30 animate-pulse"
          )}>
            <ChevronRight className="w-5 h-5" />
          </div>
          <div className={cn(
            "absolute right-2 top-1/2 -translate-y-1/2",
            "text-white/30 animate-pulse"
          )}>
            <ChevronLeft className="w-5 h-5" />
          </div>
        </>
      )}

      {/* Bottom action sheet for mobile */}
      <div className={cn(
        "md:hidden fixed bottom-0 left-0 right-0 p-4",
        "bg-white/90 dark:bg-gray-900/90 backdrop-blur-xl",
        "border-t border-gray-200 dark:border-gray-700",
        "transform transition-transform duration-300",
        "translate-y-full" // Hidden by default, show on interaction
      )}>
        <div className="grid grid-cols-3 gap-2">
          <button
            onClick={onViewDetails}
            className={cn(
              "flex flex-col items-center p-3 rounded-lg",
              theme === 'dark' 
                ? "bg-white/10 text-white" 
                : "bg-gray-100 text-gray-700"
            )}
          >
            <Eye className="w-5 h-5 mb-1" />
            <span className="text-xs">View</span>
          </button>
          <button
            onClick={onSendIVR}
            className={cn(
              "flex flex-col items-center p-3 rounded-lg",
              "bg-gradient-to-r from-blue-500 to-blue-600 text-white"
            )}
          >
            <Send className="w-5 h-5 mb-1" />
            <span className="text-xs">Send IVR</span>
          </button>
          <button
            onClick={onContact}
            className={cn(
              "flex flex-col items-center p-3 rounded-lg",
              theme === 'dark' 
                ? "bg-white/10 text-white" 
                : "bg-gray-100 text-gray-700"
            )}
          >
            <Phone className="w-5 h-5 mb-1" />
            <span className="text-xs">Contact</span>
          </button>
        </div>
      </div>
    </div>
  );
};

export default EpisodeCardMobile;