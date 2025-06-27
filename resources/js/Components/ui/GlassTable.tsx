import React from 'react';
import { cn } from '@/lib/utils';
import { themes } from '@/theme/glass-theme';
import { useTheme } from '@/contexts/ThemeContext';

interface GlassTableProps extends React.HTMLAttributes<HTMLDivElement> {
  children: React.ReactNode;
}

export const GlassTable: React.FC<GlassTableProps> = ({ 
  className, 
  children, 
  ...props 
}) => {
  // Get theme context with fallback
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;
  
  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }
  
  return (
    <div 
      className={cn(
        t.table.container, 
        'overflow-x-auto',
        theme === 'dark' ? 'shadow-2xl shadow-black/30' : 'shadow-lg',
        className
      )} 
      {...props}
    >
      {children}
    </div>
  );
};

interface TableProps extends React.HTMLAttributes<HTMLTableElement> {
  children: React.ReactNode;
}

export const Table: React.FC<TableProps> = ({ 
  className, 
  children, 
  ...props 
}) => {
  // Get theme context with fallback
  let theme: 'dark' | 'light' = 'dark';
  
  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }
  
  return (
    <table 
      className={cn(
        'min-w-full divide-y',
        theme === 'dark' ? 'divide-white/10' : 'divide-gray-200',
        className
      )} 
      {...props}
    >
      {children}
    </table>
  );
};

interface TheadProps extends React.HTMLAttributes<HTMLTableSectionElement> {
  children: React.ReactNode;
}

export const Thead: React.FC<TheadProps> = ({ 
  className, 
  children, 
  ...props 
}) => {
  // Get theme context with fallback
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;
  
  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }
  
  return (
    <thead 
      className={cn(t.table.header, className)} 
      {...props}
    >
      {children}
    </thead>
  );
};

interface TbodyProps extends React.HTMLAttributes<HTMLTableSectionElement> {
  children: React.ReactNode;
}

export const Tbody: React.FC<TbodyProps> = ({ 
  className, 
  children, 
  ...props 
}) => {
  // Get theme context with fallback
  let theme: 'dark' | 'light' = 'dark';
  
  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }
  
  return (
    <tbody 
      className={cn(
        'divide-y',
        theme === 'dark' ? 'divide-white/[0.08]' : 'divide-gray-100',
        className
      )} 
      {...props}
    >
      {children}
    </tbody>
  );
};

interface TrProps extends React.HTMLAttributes<HTMLTableRowElement> {
  isEven?: boolean;
}

export const Tr: React.FC<TrProps> = ({ 
  className, 
  isEven,
  children, 
  ...props 
}) => {
  // Get theme context with fallback
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;
  
  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }
  
  return (
    <tr 
      className={cn(
        t.table.row,
        t.table.rowHover,
        isEven && t.table.evenRow,
        'transition-colors',
        className
      )} 
      {...props}
    >
      {children}
    </tr>
  );
};

interface ThProps extends React.ThHTMLAttributes<HTMLTableCellElement> {
  children: React.ReactNode;
}

export const Th: React.FC<ThProps> = ({ 
  className, 
  children, 
  ...props 
}) => {
  // Get theme context with fallback
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;
  
  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }
  
  return (
    <th 
      className={cn(
        t.table.headerText,
        'px-6 py-3 text-left',
        className
      )} 
      {...props}
    >
      {children}
    </th>
  );
};

interface TdProps extends React.TdHTMLAttributes<HTMLTableCellElement> {
  children: React.ReactNode;
}

export const Td: React.FC<TdProps> = ({ 
  className, 
  children, 
  ...props 
}) => {
  // Get theme context with fallback
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;
  
  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }
  
  return (
    <td 
      className={cn(
        t.table.cell,
        'px-6 py-4 whitespace-nowrap',
        className
      )} 
      {...props}
    >
      {children}
    </td>
  );
};

// Export all components
export default {
  GlassTable,
  Table,
  Thead,
  Tbody,
  Tr,
  Th,
  Td,
};