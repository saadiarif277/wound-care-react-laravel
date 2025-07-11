import React, { useState, useEffect } from 'react';
import { AwesomeButton } from 'react-awesome-button';
import { motion, AnimatePresence } from 'framer-motion';
import { 
  Brain, 
  Sparkles, 
  Zap, 
  Wand2, 
  MessageCircle,
  Mic,
  FileSearch,
  Upload,
  Settings,
  X,
  ChevronUp,
  ChevronDown,
  Mail,
  Calendar,
  BarChart3,
  Users,
  Clock,
  PieChart,
  TrendingUp,
  Activity
} from 'lucide-react';
import { useTheme } from '@/contexts/ThemeContext';
import { cn } from '@/theme/glass-theme';

interface FloatingAIButtonProps {
  onClick: () => void;
  isActive?: boolean;
  showTooltip?: boolean;
  position?: 'bottom-right' | 'bottom-left' | 'top-right' | 'top-left';
  size?: 'small' | 'medium' | 'large';
  pulse?: boolean;
  disabled?: boolean;
  className?: string;
}

const positionStyles = {
  'bottom-right': 'bottom-6 right-6',
  'bottom-left': 'bottom-6 left-6',
  'top-right': 'top-6 right-6',
  'top-left': 'top-6 left-6',
};

const sizeStyles = {
  small: 'w-12 h-12',
  medium: 'w-16 h-16',
  large: 'w-20 h-20',
};

export default function FloatingAIButton({
  onClick,
  isActive = false,
  showTooltip = true,
  position = 'bottom-right',
  size = 'medium',
  pulse = true,
  disabled = false,
  className = '',
}: FloatingAIButtonProps) {
  const { theme } = useTheme();
  const [isHovered, setIsHovered] = useState(false);
  const [showQuickActions, setShowQuickActions] = useState(false);
  const [sparkles, setSparkles] = useState<Array<{ id: number; x: number; y: number; delay: number }>>([]);

  // Generate sparkles effect
  useEffect(() => {
    if (isActive || isHovered) {
      const newSparkles = Array.from({ length: 6 }, (_, i) => ({
        id: i,
        x: Math.random() * 100,
        y: Math.random() * 100,
        delay: Math.random() * 2,
      }));
      setSparkles(newSparkles);
    } else {
      setSparkles([]);
    }
  }, [isActive, isHovered]);

  // Quick actions available from the floating button - Admin focused
  const quickActions = [
    {
      id: 'email',
      icon: <Mail className="w-4 h-4" />,
      label: 'Organize Inbox',
      color: 'from-blue-500 to-purple-600',
    },
    {
      id: 'calendar',
      icon: <Calendar className="w-4 h-4" />,
      label: 'Smart Scheduling',
      color: 'from-green-500 to-blue-500',
    },
    {
      id: 'reports',
      icon: <BarChart3 className="w-4 h-4" />,
      label: 'Generate Reports',
      color: 'from-orange-500 to-red-500',
    },
    {
      id: 'team',
      icon: <Users className="w-4 h-4" />,
      label: 'Team Coordination',
      color: 'from-purple-500 to-pink-500',
    },
  ];

  return (
    <div className={cn('fixed z-50', positionStyles[position], className)}>
      {/* Quick Actions Menu */}
      <AnimatePresence>
        {showQuickActions && (
          <motion.div
            initial={{ opacity: 0, scale: 0.8, y: 20 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.8, y: 20 }}
            transition={{ type: "spring", damping: 20, stiffness: 300 }}
            className={cn(
              "absolute mb-4 right-0 bottom-full",
              position.includes('top') && 'top-full bottom-auto mt-4'
            )}
          >
            <div className={cn(
              "flex flex-col gap-2 p-3 rounded-xl shadow-2xl border backdrop-blur-xl",
              theme === 'dark' 
                ? 'bg-gray-900/90 border-gray-700/50'
                : 'bg-white/90 border-gray-200/50'
            )}>
              {quickActions.map((action, index) => (
                <motion.button
                  key={action.id}
                  initial={{ opacity: 0, x: 20 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: index * 0.1 }}
                  className={cn(
                    "flex items-center gap-3 p-3 rounded-lg transition-all duration-200",
                    "hover:scale-105 active:scale-95",
                    theme === 'dark' 
                      ? 'hover:bg-gray-800/50 text-gray-300 hover:text-white'
                      : 'hover:bg-gray-100/50 text-gray-700 hover:text-gray-900'
                  )}
                  onClick={() => {
                    setShowQuickActions(false);
                    onClick();
                  }}
                >
                  <div className={cn(
                    "w-8 h-8 rounded-lg bg-gradient-to-r flex items-center justify-center text-white",
                    action.color
                  )}>
                    {action.icon}
                  </div>
                  <span className="text-sm font-medium whitespace-nowrap">
                    {action.label}
                  </span>
                </motion.button>
              ))}
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Main AI Button */}
      <div className="relative">
        {/* Sparkles Effect */}
        <AnimatePresence>
          {sparkles.map((sparkle) => (
            <motion.div
              key={sparkle.id}
              initial={{ opacity: 0, scale: 0, rotate: 0 }}
              animate={{ 
                opacity: [0, 1, 0], 
                scale: [0, 1, 0],
                rotate: [0, 180, 360],
                x: [0, (sparkle.x - 50) * 2],
                y: [0, (sparkle.y - 50) * 2]
              }}
              transition={{
                duration: 2,
                delay: sparkle.delay,
                repeat: Infinity,
                repeatDelay: 1,
              }}
              className="absolute inset-0 pointer-events-none"
            >
              <Sparkles className="w-3 h-3 text-yellow-400 absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" />
            </motion.div>
          ))}
        </AnimatePresence>

        {/* Pulse Ring */}
        {pulse && (
          <motion.div
            animate={{
              scale: [1, 1.2, 1],
              opacity: [0.5, 0, 0.5],
            }}
            transition={{
              duration: 2,
              repeat: Infinity,
              ease: "easeInOut",
            }}
            className={cn(
              "absolute inset-0 rounded-full",
              isActive 
                ? "bg-gradient-to-r from-purple-500 to-blue-500"
                : "bg-gradient-to-r from-gray-500 to-gray-600"
            )}
          />
        )}

        {/* Glow Effect */}
        <motion.div
          animate={{
            boxShadow: isActive 
              ? [
                  "0 0 20px rgba(168, 85, 247, 0.5)",
                  "0 0 40px rgba(59, 130, 246, 0.7)",
                  "0 0 20px rgba(168, 85, 247, 0.5)",
                ]
              : "0 0 10px rgba(0, 0, 0, 0.1)",
          }}
          transition={{
            duration: 2,
            repeat: Infinity,
            ease: "easeInOut",
          }}
          className={cn(
            "rounded-full",
            sizeStyles[size]
          )}
        />

        {/* Main Button */}
        <AwesomeButton
          type="primary"
          size={size}
          onPress={onClick}
          disabled={disabled}
          className={cn(
            "!relative !overflow-hidden !rounded-full !border-0 !shadow-2xl",
            sizeStyles[size],
            isActive && "!animate-pulse"
          )}
          style={{
            '--awesome-button-primary-color': isActive 
              ? 'linear-gradient(135deg, #8B5CF6 0%, #3B82F6 100%)'
              : 'linear-gradient(135deg, #6B7280 0%, #4B5563 100%)',
            '--awesome-button-primary-color-dark': isActive 
              ? 'linear-gradient(135deg, #7C3AED 0%, #2563EB 100%)'
              : 'linear-gradient(135deg, #374151 0%, #111827 100%)',
            '--awesome-button-primary-color-light': isActive 
              ? 'linear-gradient(135deg, #A78BFA 0%, #60A5FA 100%)'
              : 'linear-gradient(135deg, #9CA3AF 0%, #6B7280 100%)',
          }}
          onMouseEnter={() => setIsHovered(true)}
          onMouseLeave={() => setIsHovered(false)}
        >
          <div className="relative flex items-center justify-center w-full h-full">
            {/* Brain Icon with rotation */}
            <motion.div
              animate={{
                rotate: isActive ? 360 : 0,
                scale: isHovered ? 1.1 : 1,
              }}
              transition={{
                rotate: { duration: 2, repeat: Infinity, ease: "linear" },
                scale: { duration: 0.2 },
              }}
            >
              <Brain className={cn(
                size === 'small' ? 'w-6 h-6' : 
                size === 'medium' ? 'w-8 h-8' : 'w-10 h-10',
                "text-white drop-shadow-sm"
              )} />
            </motion.div>

            {/* Processing Indicator */}
            {isActive && (
              <motion.div
                initial={{ opacity: 0, scale: 0 }}
                animate={{ opacity: 1, scale: 1 }}
                className="absolute -top-2 -right-2 w-4 h-4 bg-green-500 rounded-full border-2 border-white shadow-lg"
              />
            )}
          </div>
        </AwesomeButton>

        {/* Quick Actions Toggle */}
        <motion.button
          whileHover={{ scale: 1.1 }}
          whileTap={{ scale: 0.9 }}
          onClick={() => setShowQuickActions(!showQuickActions)}
          className={cn(
            "absolute -top-2 -left-2 w-6 h-6 rounded-full flex items-center justify-center",
            "bg-gradient-to-r from-purple-500 to-blue-500 text-white shadow-lg",
            "hover:from-purple-600 hover:to-blue-600 transition-all duration-200"
          )}
        >
          <motion.div
            animate={{ rotate: showQuickActions ? 180 : 0 }}
            transition={{ duration: 0.2 }}
          >
            <ChevronUp className="w-3 h-3" />
          </motion.div>
        </motion.button>
      </div>

      {/* Tooltip */}
      <AnimatePresence>
        {showTooltip && isHovered && !showQuickActions && (
          <motion.div
            initial={{ opacity: 0, scale: 0.8, y: 10 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.8, y: 10 }}
            transition={{ type: "spring", damping: 20, stiffness: 300 }}
            className={cn(
              "absolute mb-2 right-0 bottom-full",
              position.includes('top') && 'top-full bottom-auto mt-2',
              position.includes('left') && 'left-0 right-auto'
            )}
          >
            <div className={cn(
              "px-3 py-2 rounded-lg shadow-lg text-sm font-medium whitespace-nowrap",
              "backdrop-blur-xl border",
              theme === 'dark'
                ? 'bg-gray-900/90 border-gray-700/50 text-gray-100'
                : 'bg-white/90 border-gray-200/50 text-gray-900'
            )}>
              {isActive ? 'AI Assistant Active' : 'Open AI Assistant'}
              <div className="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900 dark:border-t-gray-100" />
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}

// CSS for react-awesome-button styling
const awesomeButtonStyles = `
  .aws-btn {
    --awesome-button-primary-color: linear-gradient(135deg, #8B5CF6 0%, #3B82F6 100%);
    --awesome-button-primary-color-dark: linear-gradient(135deg, #7C3AED 0%, #2563EB 100%);
    --awesome-button-primary-color-light: linear-gradient(135deg, #A78BFA 0%, #60A5FA 100%);
    --awesome-button-border-radius: 9999px;
    --awesome-button-font-weight: 600;
    --awesome-button-box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    --awesome-button-hover-pressure: 2;
    --awesome-button-active-pressure: 4;
    --awesome-button-transition-timing: cubic-bezier(0.25, 0.46, 0.45, 0.94);
  }
  
  .aws-btn:hover {
    --awesome-button-box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.15), 0 20px 20px -10px rgba(0, 0, 0, 0.08);
  }
`;

// Inject styles
if (typeof window !== 'undefined') {
  const styleSheet = document.createElement('style');
  styleSheet.textContent = awesomeButtonStyles;
  document.head.appendChild(styleSheet);
} 