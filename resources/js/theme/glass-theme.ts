import { colors } from './colors.config';

export const themes = {
  dark: {
    // Backgrounds
    background: {
      base: 'bg-gradient-to-br from-[#0a0f1c] via-[#121829] to-[#1a1f2e]',
      noise: 'before:bg-[url("/noise.png")] before:opacity-[0.03]',
    },
    
    // Glass effects - Enhanced for better contrast
    glass: {
      base: 'bg-white/[0.07] backdrop-blur-xl border border-white/[0.12]',
      hover: 'hover:bg-white/[0.11] hover:border-white/[0.18]',
      active: 'bg-white/[0.13] border-white/[0.22]',
      frost: 'bg-white/[0.08] backdrop-blur-3xl backdrop-saturate-150', // Increased opacity
      card: 'bg-white/[0.06] backdrop-blur-2xl border border-white/[0.10] shadow-xl shadow-black/20',
      sidebar: 'bg-black/[0.25] backdrop-blur-2xl border-r border-white/[0.08]',
    },
    
    // Text colors - Enhanced contrast
    text: {
      primary: 'text-white/95',
      secondary: 'text-white/80', // Increased from 75
      tertiary: 'text-white/65', // Increased from 55
      muted: 'text-white/50', // Increased from 40
      inverse: 'text-gray-900',
    },
    
    // Status colors - Better contrast
    status: {
      success: 'bg-emerald-500/25 text-emerald-400 border border-emerald-500/40',
      warning: 'bg-amber-500/25 text-amber-400 border border-amber-500/40',
      error: 'bg-red-500/25 text-red-400 border border-red-500/40',
      info: 'bg-blue-500/25 text-blue-400 border border-blue-500/40',
    },
    
    // Shadows
    shadows: {
      glass: 'shadow-2xl shadow-black/30',
      glow: 'shadow-[0_0_50px_rgba(25,37,195,0.3)]',
      danger: 'shadow-[0_0_30px_rgba(199,23,25,0.4)]',
      soft: 'shadow-lg shadow-black/20',
      inner: 'shadow-[inset_0_1px_0_0_rgba(255,255,255,0.1)]',
    },
    
    // Input styles - Enhanced for dark theme
    input: {
      base: 'bg-white/[0.05] backdrop-blur-md border border-white/[0.1] text-white/90 placeholder:text-white/30 rounded-xl px-4 py-2.5',
      focus: 'focus:bg-white/[0.08] focus:border-[#1925c3] focus:ring-2 focus:ring-[#1925c3]/30 focus:outline-none',
      error: 'border-red-500/50 focus:border-red-500 focus:ring-red-500/30',
      disabled: 'opacity-50 cursor-not-allowed bg-white/[0.02]',
    },
    
    // Button variants
    button: {
      primary: `bg-gradient-to-r from-[${colors.msc.blue.DEFAULT}] to-[${colors.msc.red.DEFAULT}] text-white font-semibold shadow-lg hover:shadow-[0_0_30px_rgba(25,37,195,0.5)] transform hover:scale-105 transition-all duration-200`,
      secondary: 'bg-white/[0.07] backdrop-blur-xl border border-white/[0.12] text-white/95 hover:bg-white/[0.11] hover:border-white/[0.18] transition-all duration-200',
      ghost: 'bg-transparent border border-white/20 text-white/80 hover:bg-white/[0.07] hover:border-white/[0.25] transition-all duration-200',
      danger: 'bg-red-500/20 border border-red-500/30 text-red-300 hover:bg-red-500/30 hover:border-red-500/40 transition-all duration-200',
      approve: `bg-[${colors.actions.approve.dark}] text-emerald-400 border border-emerald-500/30 hover:bg-emerald-500/30 transition-all duration-200`,
      warning: `bg-[${colors.actions.warning.dark}] text-amber-400 border border-amber-500/30 hover:bg-amber-500/30 transition-all duration-200`,
    },
    
    // Table styles - Enhanced contrast
    table: {
      container: 'bg-white/[0.03] backdrop-blur-3xl backdrop-saturate-150 rounded-2xl overflow-hidden',
      header: 'bg-white/[0.10] border-b border-white/[0.20]', // Increased opacity
      headerText: 'text-white/95 font-semibold text-sm uppercase tracking-wider',
      row: 'border-b border-white/[0.08]',
      rowHover: 'hover:bg-white/[0.06] transition-colors duration-200',
      cell: 'text-white/90', // Increased contrast
      evenRow: 'bg-white/[0.02]',
      actionButton: 'bg-white/[0.12] hover:bg-white/[0.16]', // Better contrast
    },
    
    // Navigation styles
    navigation: {
      container: 'bg-white/[0.08] backdrop-blur-xl border border-white/[0.12] shadow-2xl shadow-black/30 rounded-3xl',
      item: 'text-white/80 py-3 px-4 rounded-xl transition-all duration-200',
      itemHover: 'hover:bg-white/[0.11] hover:text-white/95',
      itemActive: `bg-gradient-to-r from-[${colors.msc.blue.DEFAULT}] to-[${colors.msc.red.DEFAULT}] text-white font-semibold shadow-[0_0_20px_rgba(25,37,195,0.3)]`,
      icon: 'w-5 h-5 text-white/80',
    },
    
    // Modal styles - Enhanced glass effect
    modal: {
      backdrop: 'bg-black/80 backdrop-blur-md',
      container: 'bg-white/[0.08] backdrop-blur-2xl backdrop-saturate-150 border border-white/[0.12] shadow-2xl shadow-black/40 rounded-3xl',
      header: 'border-b border-white/[0.12] px-6 py-4',
      body: 'px-6 py-4',
      footer: 'border-t border-white/[0.12] px-6 py-4',
    },
    
    // Badge styles
    badge: {
      default: 'bg-white/[0.08] text-white/80 border border-white/[0.12]',
      primary: `bg-[${colors.msc.blue.dark}] text-blue-300 border border-[${colors.msc.blue.DEFAULT}]/30`,
      danger: 'bg-red-500/20 text-red-300 border border-red-500/30',
      success: 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30',
    },
  },
  
  light: {
    // Backgrounds
    background: {
      base: 'bg-gradient-to-br from-gray-50 via-white to-blue-50/30',
      noise: 'before:bg-[url("/noise-light.png")] before:opacity-[0.02]',
    },
    
    // Glass effects (adjusted for light theme with better opacity)
    glass: {
      base: 'bg-white/70 backdrop-blur-xl border border-gray-200/60 backdrop-saturate-100 shadow-sm',
      hover: 'hover:bg-white/80 hover:border-gray-300 hover:shadow-md',
      active: 'bg-white/90 border-gray-400',
      frost: 'bg-white/60 backdrop-blur-2xl backdrop-saturate-150 border border-gray-200/50',
      card: 'bg-white/80 backdrop-blur-sm border border-gray-200/50 shadow-sm hover:shadow-md',
      sidebar: 'bg-white/90 backdrop-blur-xl border-r border-gray-200/60 shadow-[2px_0_12px_rgba(0,0,0,0.08)]',
    },
    
    // Text colors - MUCH darker for visibility
    text: {
      primary: 'text-gray-900',
      secondary: 'text-gray-800',  // Darker from 700
      tertiary: 'text-gray-700',   // Darker from 600
      muted: 'text-gray-600',      // Darker from 500
      inverse: 'text-white',
    },
    
    // Status colors (adjusted for light backgrounds)
    status: {
      success: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
      warning: 'bg-amber-50 text-amber-700 border border-amber-200',
      error: 'bg-red-50 text-red-700 border border-red-200',
      info: 'bg-blue-50 text-blue-700 border border-blue-200',
    },
    
    // Shadows
    shadows: {
      glass: 'shadow-lg shadow-gray-200/50',
      glow: 'shadow-[0_0_30px_rgba(25,37,195,0.15)]',
      danger: 'shadow-[0_0_20px_rgba(199,23,25,0.2)]',
      soft: 'shadow-md shadow-gray-200/40',
      inner: 'shadow-[inset_0_1px_0_0_rgba(0,0,0,0.05)]',
    },
    
    // Input styles - Enhanced for visibility
    input: {
      base: 'bg-white border border-gray-400 text-gray-900 placeholder:text-gray-500 rounded-xl px-4 py-2.5',
      focus: 'focus:bg-white focus:ring-2 focus:ring-[#1925c3]/60 focus:border-[#1925c3] focus:outline-none',
      error: 'border-red-600 focus:ring-red-600/40 focus:border-red-600',
      disabled: 'opacity-60 cursor-not-allowed bg-gray-100',
    },
    
    // Button variants
    button: {
      primary: `bg-gradient-to-r from-[${colors.msc.blue.DEFAULT}] to-[${colors.msc.red.DEFAULT}] text-white font-semibold shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200`,
      secondary: 'bg-white border border-gray-400 text-gray-800 hover:bg-gray-100 hover:border-gray-500 hover:shadow-md transition-all duration-200',
      ghost: 'bg-transparent border border-gray-400 text-gray-800 hover:bg-gray-100 hover:border-gray-500 transition-all duration-200',
      danger: 'bg-red-50 border border-red-200 text-red-700 hover:bg-red-100 hover:border-red-300 transition-all duration-200',
      approve: `bg-[${colors.actions.approve.light}] text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition-all duration-200`,
      warning: `bg-[${colors.actions.warning.light}] text-amber-700 border border-amber-200 hover:bg-amber-100 transition-all duration-200`,
    },
    
    // Table styles
    table: {
      container: 'bg-white rounded-2xl overflow-hidden shadow-md border border-gray-300',
      header: 'bg-gray-100 border-b-2 border-gray-300',
      headerText: 'text-gray-900 font-semibold text-sm uppercase tracking-wider',
      row: 'border-b border-gray-200',
      rowHover: 'hover:bg-gray-100 transition-colors duration-200',
      cell: 'text-gray-800',
      evenRow: 'bg-gray-50',
      actionButton: 'bg-gray-200 hover:bg-gray-300 text-gray-900',
    },
    
    // Navigation styles
    navigation: {
      container: 'bg-white/80 backdrop-blur-xl border border-gray-200/60 shadow-xl rounded-3xl',
      item: 'text-gray-700 py-3 px-4 rounded-xl transition-all duration-200',
      itemHover: 'hover:bg-gray-100 hover:text-gray-900',
      itemActive: `${colors.gradients.primarySubtle} text-[${colors.msc.blue.DEFAULT}] font-semibold border-l-[3px] border-l-[${colors.msc.blue.DEFAULT}]`,
      icon: 'w-5 h-5 text-gray-600',
    },
    
    // Modal styles
    modal: {
      backdrop: 'bg-gray-900/30 backdrop-blur-sm',
      container: 'bg-white border border-gray-200 shadow-2xl rounded-3xl',
      header: 'border-b border-gray-200 px-6 py-4',
      body: 'px-6 py-4',
      footer: 'border-t border-gray-200 px-6 py-4',
    },
    
    // Badge styles
    badge: {
      default: 'bg-gray-100 text-gray-700 border border-gray-200',
      primary: 'bg-blue-50 text-blue-700 border border-blue-200',
      danger: 'bg-red-50 text-red-700 border border-red-200',
      success: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
    },
  },
} as const;

// Legacy support - map to dark theme
export const glassTheme = themes.dark;

// Utility function to combine classes
export const cn = (...classes: (string | undefined | false)[]) => {
  return classes.filter(Boolean).join(' ');
};

// Get theme-aware classes
export const getThemeClass = (theme: 'dark' | 'light', path: string) => {
  const keys = path.split('.');
  let value: any = themes[theme];
  
  for (const key of keys) {
    value = value?.[key];
  }
  
  return value || '';
};

// Special frost card class for maximum readability
export const frostCard = (theme: 'dark' | 'light' = 'dark') => cn(
  theme === 'dark' 
    ? 'bg-white/[0.09] backdrop-blur-3xl backdrop-saturate-200'
    : 'bg-white/60 backdrop-blur-2xl backdrop-saturate-150',
  'before:absolute before:inset-0 before:bg-gradient-to-b',
  theme === 'dark'
    ? 'before:from-white/[0.06] before:to-transparent'
    : 'before:from-white/20 before:to-transparent',
  'before:rounded-2xl before:pointer-events-none',
  'relative'
);

// Text with glass shadow for critical readability
export const glassTextShadow = 'drop-shadow-[0_2px_4px_rgba(0,0,0,0.5)] [text-shadow:_0_0_20px_rgba(0,0,0,0.3)]';