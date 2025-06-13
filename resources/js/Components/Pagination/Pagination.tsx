import { Link } from '@inertiajs/react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface PaginationProps {
  links: PaginationItem[];
}

export default function Pagination({ links = [] }: PaginationProps) {
  /**
   * If there are only 3 links, it means there are no previous or next pages.
   * So, we don't need to render the pagination.
   */
  if (links.length === 3) return null;

  return (
    <div className="flex flex-wrap mt-6 -mb-1 gap-1">
      {links?.map(link => {
        return link?.url === null ? (
          <PageInactive key={link.label} label={link.label} />
        ) : (
          <PaginationItem key={link.label} {...link} />
        );
      })}
    </div>
  );
}

interface PaginationItem {
  url: null | string;
  label: string;
  active: boolean;
}

function PaginationItem({ active, label, url }: PaginationItem) {
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

  const className = cn(
    'px-4 py-2 text-sm font-medium rounded-xl transition-all duration-200',
    t.glass.base,
    active
      ? cn(t.button.primary, 'shadow-lg')
      : cn(
          t.text.secondary,
          t.glass.hover,
          'hover:scale-105'
        )
  );

  /**
   * Note: In general you should be aware when using `dangerouslySetInnerHTML`.
   *
   * In this case, `label` from the API is a string, so it's safe to use it.
   * It will be either `&laquo; Previous` or `Next &raquo;`
   */
  return (
    <Link className={className} href={url as string}>
      <span dangerouslySetInnerHTML={{ __html: label }}></span>
    </Link>
  );
}

function PageInactive({ label }: Pick<PaginationItem, 'label'>) {
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

  const className = cn(
    'px-4 py-2 text-sm font-medium rounded-xl',
    t.glass.base,
    t.text.muted,
    'cursor-not-allowed opacity-50'
  );

  /**
   * Note: In general you should be aware when using `dangerouslySetInnerHTML`.
   *
   * In this case, `label` from the API is a string, so it's safe to use it.
   * It will be either `&laquo; Previous` or `Next &raquo;`
   */
  return (
    <div className={className} dangerouslySetInnerHTML={{ __html: label }} />
  );
}
