import { Link } from '@inertiajs/react';
import get from 'lodash/get';
import { ChevronRight } from 'lucide-react';
import { themes, cn } from '@/theme/glass-theme';
import { useTheme } from '@/contexts/ThemeContext';

interface TableProps<T> {
  columns: {
    name: string;
    label: string;
    colSpan?: number;
    renderCell?: (row: T) => React.ReactNode;
  }[];
  rows: T[];
  getRowDetailsUrl?: (row: T) => string;
}

export default function Table<T>({
  columns = [],
  rows = [],
  getRowDetailsUrl
}: TableProps<T>) {
  // Theme setup with fallback
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;
  
  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // If not in ThemeProvider, use dark theme
  }
  
  return (
    <div className={cn(t.table.container, 'overflow-x-auto', theme === 'dark' ? 'shadow-2xl shadow-black/30' : '')}>
      <table className="w-full whitespace-nowrap">
        <thead>
          <tr className={cn(t.table.header, 'text-left')}>
            {columns?.map(column => (
              <th
                key={column.label}
                colSpan={column.colSpan ?? 1}
                className={cn(t.table.headerText, 'px-6 pt-5 pb-4')}
              >
                {column.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {/* Empty state */}
          {rows?.length === 0 && (
            <tr>
              <td
                className={cn(t.table.cell, t.text.secondary, 'px-6 py-24 text-center')}
                colSpan={columns.length}
              >
                No data found.
              </td>
            </tr>
          )}
          {rows?.map((row, index) => {
            const isEven = index % 2 === 0;
            return (
              <tr
                key={index}
                className={cn(
                  t.table.row,
                  t.table.rowHover,
                  isEven && t.table.evenRow,
                  theme === 'dark' ? 'focus-within:bg-white/[0.08]' : 'focus-within:bg-gray-50'
                )}
              >
                {columns.map(column => {
                  return (
                    <td key={column.name} className={t.table.cell}>
                      <Link
                        tabIndex={-1}
                        href={getRowDetailsUrl?.(row) as string}
                        className={cn(
                          'flex items-center px-6 py-4',
                          theme === 'dark' ? 'focus:text-white' : 'focus:text-gray-900',
                          'focus:outline-none',
                          'transition-colors duration-200'
                        )}
                      >
                        {column.renderCell?.(row) ??
                          get(row, column.name) ??
                          'N/A'}
                      </Link>
                    </td>
                  );
                })}
                <td className="w-px">
                  <Link
                    href={getRowDetailsUrl?.(row)!}
                    className={cn(
                      'flex items-center px-4',
                      'focus:outline-none',
                      t.text.secondary,
                      theme === 'dark' ? 'hover:text-white/95' : 'hover:text-gray-900',
                      'transition-colors duration-200'
                    )}
                  >
                    <ChevronRight size={24} />
                  </Link>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
