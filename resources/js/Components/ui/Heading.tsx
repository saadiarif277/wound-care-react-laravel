import React from 'react';
import clsx from 'clsx';
import { useTheme } from '@/contexts/ThemeContext';
import { themes } from '@/theme/glass-theme';

interface HeadingProps {
  level?: 1 | 2 | 3 | 4 | 5 | 6;
  className?: string;
  children: React.ReactNode;
}

const sizeMap: Record<number, string> = {
  1: 'text-4xl font-bold',
  2: 'text-2xl font-semibold',
  3: 'text-xl font-semibold',
  4: 'text-lg font-semibold',
  5: 'text-base font-semibold',
  6: 'text-sm font-semibold',
};

const Heading: React.FC<HeadingProps> = ({ level = 2, className, children }) => {
  // Try to use theme if available, fallback to dark theme
  let textColor = themes.dark.text.primary;
  
  try {
    const { theme } = useTheme();
    textColor = themes[theme].text.primary;
  } catch (e) {
    // If not in ThemeProvider, use dark theme
  }
  
  const Tag = (`h${level}` as unknown) as keyof JSX.IntrinsicElements;
  return (
    <Tag className={clsx(sizeMap[level], textColor, className)}>{children}</Tag>
  );
};

export default Heading;