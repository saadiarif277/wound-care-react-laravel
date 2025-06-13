/**
 * MSC Brand Colors Configuration
 * Using RGB values to prevent color shifting and ensure accurate rendering
 */

export const colors = {
  msc: {
    // Use RGB values to prevent color shift
    blue: {
      DEFAULT: 'rgb(25, 37, 195)', // #1925c3 in RGB
      hover: 'rgb(30, 45, 210)',
      active: 'rgb(20, 30, 180)',
      light: 'rgba(25, 37, 195, 0.1)', // For light theme backgrounds
      dark: 'rgba(25, 37, 195, 0.2)', // For dark theme backgrounds
    },
    red: {
      DEFAULT: 'rgb(199, 23, 25)', // #c71719 in RGB
      hover: 'rgb(215, 30, 32)',
      active: 'rgb(180, 20, 22)',
      light: 'rgba(199, 23, 25, 0.1)',
      dark: 'rgba(199, 23, 25, 0.2)',
    }
  },
  // Action colors that work in both themes
  actions: {
    approve: {
      DEFAULT: 'rgb(34, 197, 94)', // Green
      hover: 'rgb(22, 163, 74)',
      light: 'rgba(34, 197, 94, 0.1)',
      dark: 'rgba(34, 197, 94, 0.2)',
    },
    warning: {
      DEFAULT: 'rgb(245, 158, 11)', // Amber
      hover: 'rgb(217, 119, 6)',
      light: 'rgba(245, 158, 11, 0.1)',
      dark: 'rgba(245, 158, 11, 0.2)',
    },
    danger: {
      DEFAULT: 'rgb(239, 68, 68)', // Red
      hover: 'rgb(220, 38, 38)',
      light: 'rgba(239, 68, 68, 0.1)',
      dark: 'rgba(239, 68, 68, 0.2)',
    },
    info: {
      DEFAULT: 'rgb(59, 130, 246)', // Blue
      hover: 'rgb(37, 99, 235)',
      light: 'rgba(59, 130, 246, 0.1)',
      dark: 'rgba(59, 130, 246, 0.2)',
    }
  },
  // Gradient configurations
  gradients: {
    primary: 'linear-gradient(135deg, rgb(25, 37, 195) 0%, rgb(199, 23, 25) 100%)',
    primaryHover: 'linear-gradient(135deg, rgb(30, 45, 210) 0%, rgb(215, 30, 32) 100%)',
    primarySubtle: 'linear-gradient(135deg, rgba(25, 37, 195, 0.1) 0%, rgba(199, 23, 25, 0.08) 100%)',
  }
} as const;

export type Colors = typeof colors;