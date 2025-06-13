export type ThemeMode = 'dark' | 'light';

export interface ThemePalette {
  surface: string;
  surfaceAlt: string;
  border: string;
  divider: string;
  text: {
    primary: string;
    secondary: string;
    muted: string;
  };
  accent: {
    blue: string;
    green: string;
    yellow: string;
    purple: string;
    red: string;
  };
}

export const palettes: Record<ThemeMode, ThemePalette> = {
  dark: {
    surface: 'bg-white/10 backdrop-blur-md',
    surfaceAlt: 'bg-white/15',
    border: 'border-white/20',
    divider: 'divide-white/10',
    text: {
      primary: 'text-white',
      secondary: 'text-gray-300',
      muted: 'text-gray-400',
    },
    accent: {
      blue: 'text-blue-400',
      green: 'text-green-400',
      yellow: 'text-yellow-400',
      purple: 'text-purple-400',
      red: 'text-red-400',
    },
  },
  light: {
    surface: 'bg-white/90 backdrop-blur-md shadow-md',
    surfaceAlt: 'bg-white',
    border: 'border-gray-300/60',
    divider: 'divide-gray-300/60',
    text: {
      primary: 'text-gray-900',
      secondary: 'text-gray-700',
      muted: 'text-gray-600',
    },
    accent: {
      blue: 'text-blue-600',
      green: 'text-green-600',
      yellow: 'text-yellow-600',
      purple: 'text-purple-600',
      red: 'text-red-600',
    },
  },
};
