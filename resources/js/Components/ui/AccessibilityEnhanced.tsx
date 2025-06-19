import React, { useState, useRef, useEffect } from 'react';
import { cn } from '@/theme/glass-theme';
import { Eye, EyeOff, Volume2, Keyboard, MousePointer, Settings } from 'lucide-react';

/**
 * AccessibilityToolbar - Comprehensive accessibility controls for 2025 compliance
 * Includes WCAG 2.2 AA standards and healthcare-specific accessibility features
 */
interface AccessibilitySettings {
  fontSize: 'small' | 'medium' | 'large' | 'extra-large';
  contrast: 'normal' | 'high' | 'extra-high';
  reducedMotion: boolean;
  focusIndicators: 'normal' | 'enhanced';
  screenReaderOptimized: boolean;
  keyboardNavigation: boolean;
}

interface AccessibilityToolbarProps {
  className?: string;
}

const AccessibilityToolbar: React.FC<AccessibilityToolbarProps> = ({ className }) => {
  const [isOpen, setIsOpen] = useState(false);
  const [settings, setSettings] = useState<AccessibilitySettings>({
    fontSize: 'medium',
    contrast: 'normal',
    reducedMotion: false,
    focusIndicators: 'normal',
    screenReaderOptimized: false,
    keyboardNavigation: true
  });

  // Apply accessibility settings to document
  useEffect(() => {
    const root = document.documentElement;

    // Font size adjustments
    const fontSizeMap = {
      small: '14px',
      medium: '16px',
      large: '18px',
      'extra-large': '20px'
    };
    root.style.fontSize = fontSizeMap[settings.fontSize];

    // Contrast adjustments
    if (settings.contrast === 'high') {
      root.classList.add('high-contrast');
    } else if (settings.contrast === 'extra-high') {
      root.classList.add('extra-high-contrast');
    } else {
      root.classList.remove('high-contrast', 'extra-high-contrast');
    }

    // Reduced motion
    if (settings.reducedMotion) {
      root.classList.add('reduce-motion');
    } else {
      root.classList.remove('reduce-motion');
    }

    // Enhanced focus indicators
    if (settings.focusIndicators === 'enhanced') {
      root.classList.add('enhanced-focus');
    } else {
      root.classList.remove('enhanced-focus');
    }

  }, [settings]);

  const updateSetting = <K extends keyof AccessibilitySettings>(
    key: K,
    value: AccessibilitySettings[K]
  ) => {
    setSettings(prev => ({ ...prev, [key]: value }));
  };

  return (
    <div className={cn('fixed bottom-4 right-4 z-50', className)}>
      {/* Accessibility Toggle Button */}
      <button
        onClick={() => setIsOpen(!isOpen)}
        className={cn(
          'w-12 h-12 rounded-full bg-blue-600 text-white shadow-lg',
          'hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500',
          'transition-all duration-200'
        )}
        aria-label="Open accessibility settings"
        aria-expanded={isOpen}
      >
        <Settings className="w-6 h-6 mx-auto" />
      </button>

      {/* Accessibility Panel */}
      {isOpen && (
        <div className={cn(
          'absolute bottom-14 right-0 w-80 bg-white rounded-lg shadow-xl border',
          'p-6 space-y-6'
        )}>
          <div className="flex items-center justify-between">
            <h3 className="text-lg font-semibold text-gray-900">
              Accessibility Settings
            </h3>
            <button
              onClick={() => setIsOpen(false)}
              className="text-gray-500 hover:text-gray-700"
              aria-label="Close accessibility settings"
            >
              ×
            </button>
          </div>

          {/* Font Size Control */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Font Size
            </label>
            <div className="grid grid-cols-2 gap-2">
              {(['small', 'medium', 'large', 'extra-large'] as const).map((size) => (
                <button
                  key={size}
                  onClick={() => updateSetting('fontSize', size)}
                  className={cn(
                    'px-3 py-2 text-sm border rounded-md transition-colors',
                    settings.fontSize === size
                      ? 'bg-blue-100 border-blue-500 text-blue-700'
                      : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                  )}
                >
                  {size.charAt(0).toUpperCase() + size.slice(1).replace('-', ' ')}
                </button>
              ))}
            </div>
          </div>

          {/* Contrast Control */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Contrast Level
            </label>
            <div className="space-y-2">
              {(['normal', 'high', 'extra-high'] as const).map((contrast) => (
                <label key={contrast} className="flex items-center">
                  <input
                    type="radio"
                    name="contrast"
                    value={contrast}
                    checked={settings.contrast === contrast}
                    onChange={() => updateSetting('contrast', contrast)}
                    className="mr-2"
                  />
                  <span className="text-sm text-gray-700">
                    {contrast.charAt(0).toUpperCase() + contrast.slice(1).replace('-', ' ')} Contrast
                  </span>
                </label>
              ))}
            </div>
          </div>

          {/* Motion Settings */}
          <div>
            <label className="flex items-center">
              <input
                type="checkbox"
                checked={settings.reducedMotion}
                onChange={(e) => updateSetting('reducedMotion', e.target.checked)}
                className="mr-2"
              />
              <span className="text-sm text-gray-700">Reduce Motion</span>
            </label>
          </div>

          {/* Focus Indicators */}
          <div>
            <label className="flex items-center">
              <input
                type="checkbox"
                checked={settings.focusIndicators === 'enhanced'}
                onChange={(e) => updateSetting('focusIndicators', e.target.checked ? 'enhanced' : 'normal')}
                className="mr-2"
              />
              <span className="text-sm text-gray-700">Enhanced Focus Indicators</span>
            </label>
          </div>

          {/* Screen Reader Optimization */}
          <div>
            <label className="flex items-center">
              <input
                type="checkbox"
                checked={settings.screenReaderOptimized}
                onChange={(e) => updateSetting('screenReaderOptimized', e.target.checked)}
                className="mr-2"
              />
              <span className="text-sm text-gray-700">Screen Reader Optimized</span>
            </label>
          </div>
        </div>
      )}
    </div>
  );
};

/**
 * ScreenReaderText - Hidden text for screen readers
 */
interface ScreenReaderTextProps {
  children: React.ReactNode;
}

const ScreenReaderText: React.FC<ScreenReaderTextProps> = ({ children }) => (
  <span className="sr-only">{children}</span>
);

/**
 * FocusTrap - Trap focus within a component for modal accessibility
 */
interface FocusTrapProps {
  children: React.ReactNode;
  isActive: boolean;
  className?: string;
}

const FocusTrap: React.FC<FocusTrapProps> = ({ children, isActive, className }) => {
  const containerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!isActive || !containerRef.current) return;

    const focusableElements = containerRef.current.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );

    const firstElement = focusableElements[0] as HTMLElement;
    const lastElement = focusableElements[focusableElements.length - 1] as HTMLElement;

    const handleTabKey = (e: KeyboardEvent) => {
      if (e.key !== 'Tab') return;

      if (e.shiftKey) {
        if (document.activeElement === firstElement) {
          lastElement?.focus();
          e.preventDefault();
        }
      } else {
        if (document.activeElement === lastElement) {
          firstElement?.focus();
          e.preventDefault();
        }
      }
    };

    firstElement?.focus();
    document.addEventListener('keydown', handleTabKey);

    return () => {
      document.removeEventListener('keydown', handleTabKey);
    };
  }, [isActive]);

  return (
    <div ref={containerRef} className={className}>
      {children}
    </div>
  );
};

/**
 * KeyboardNavigationHelper - Visual indicator for keyboard navigation
 */
const KeyboardNavigationHelper: React.FC = () => {
  const [showHelper, setShowHelper] = useState(false);

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Tab') {
        setShowHelper(true);
      }
    };

    const handleMouseDown = () => {
      setShowHelper(false);
    };

    document.addEventListener('keydown', handleKeyDown);
    document.addEventListener('mousedown', handleMouseDown);

    return () => {
      document.removeEventListener('keydown', handleKeyDown);
      document.removeEventListener('mousedown', handleMouseDown);
    };
  }, []);

  if (!showHelper) return null;

  return (
    <div className="fixed top-4 left-1/2 transform -translate-x-1/2 z-50">
      <div className="bg-blue-600 text-white px-4 py-2 rounded-lg shadow-lg">
        <div className="flex items-center space-x-2">
          <Keyboard className="w-4 h-4" />
          <span className="text-sm">Keyboard navigation active - Use Tab to navigate</span>
        </div>
      </div>
    </div>
  );
};

/**
 * AccessibleButton - Button with comprehensive accessibility features
 */
interface AccessibleButtonProps {
  children: React.ReactNode;
  onClick?: () => void;
  variant?: 'primary' | 'secondary' | 'danger';
  size?: 'sm' | 'md' | 'lg';
  disabled?: boolean;
  loading?: boolean;
  ariaLabel?: string;
  ariaDescribedBy?: string;
  className?: string;
}

const AccessibleButton: React.FC<AccessibleButtonProps> = ({
  children,
  onClick,
  variant = 'primary',
  size = 'md',
  disabled = false,
  loading = false,
  ariaLabel,
  ariaDescribedBy,
  className
}) => {
  const variantStyles = {
    primary: 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500',
    secondary: 'bg-gray-200 text-gray-900 hover:bg-gray-300 focus:ring-gray-500',
    danger: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500'
  };

  const sizeStyles = {
    sm: 'px-3 py-2 text-sm min-h-[40px]',
    md: 'px-4 py-2 text-base min-h-[44px]',
    lg: 'px-6 py-3 text-lg min-h-[48px]'
  };

  return (
    <button
      onClick={onClick}
      disabled={disabled || loading}
      aria-label={ariaLabel}
      aria-describedby={ariaDescribedBy}
      aria-busy={loading}
      className={cn(
        'font-medium rounded-lg transition-all duration-200',
        'focus:outline-none focus:ring-2 focus:ring-offset-2',
        'disabled:opacity-50 disabled:cursor-not-allowed',
        variantStyles[variant],
        sizeStyles[size],
        className
      )}
    >
      {loading ? (
        <div className="flex items-center justify-center">
          <div className="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin mr-2" />
          <ScreenReaderText>Loading</ScreenReaderText>
          {children}
        </div>
      ) : (
        children
      )}
    </button>
  );
};

export {
  AccessibilityToolbar,
  ScreenReaderText,
  FocusTrap,
  KeyboardNavigationHelper,
  AccessibleButton
};
