import { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface NavItem {
    name: string;
    href: string;
    current?: boolean;
}

interface PageHeaderProps {
    title: string;
    description?: string;
    navItems?: NavItem[];
    actions?: ReactNode;
}

export function PageHeader({
    title,
    description,
    navItems,
    actions,
}: PageHeaderProps) {
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
        <div className="mb-8">
            <div className="md:flex md:items-center md:justify-between">
                <div className="min-w-0 flex-1">
                    <h2 className={cn(
                        'text-2xl font-bold leading-7 sm:truncate sm:text-3xl sm:tracking-tight',
                        t.text.primary
                    )}>
                        {title}
                    </h2>
                    {description && (
                        <p className={cn('mt-1 text-sm', t.text.secondary)}>
                            {description}
                        </p>
                    )}
                </div>
                {actions && (
                    <div className="mt-4 flex md:ml-4 md:mt-0">
                        {actions}
                    </div>
                )}
            </div>
            {navItems && navItems.length > 0 && (
                <div className={cn(
                    'mt-6 border-b',
                    theme === 'dark' ? 'border-white/20' : 'border-gray-200'
                )}>
                    <nav className="-mb-px flex space-x-8">
                        {navItems.map((item) => (
                            <Link
                                key={item.name}
                                href={item.href}
                                className={cn(
                                    'whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition-colors duration-200',
                                    item.current
                                        ? theme === 'dark'
                                            ? 'border-blue-400 text-blue-300'
                                            : 'border-indigo-500 text-indigo-600'
                                        : theme === 'dark'
                                            ? 'border-transparent text-white/60 hover:border-white/30 hover:text-white/80'
                                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                                )}
                            >
                                {item.name}
                            </Link>
                        ))}
                    </nav>
                </div>
            )}
        </div>
    );
}
