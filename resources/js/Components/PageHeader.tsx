import { ReactNode } from 'react';
import { Link } from '@inertiajs/react';

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
    return (
        <div className="mb-8">
            <div className="md:flex md:items-center md:justify-between">
                <div className="min-w-0 flex-1">
                    <h2 className="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">
                        {title}
                    </h2>
                    {description && (
                        <p className="mt-1 text-sm text-gray-500">
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
                <div className="mt-6 border-b border-gray-200">
                    <nav className="-mb-px flex space-x-8">
                        {navItems.map((item) => (
                            <Link
                                key={item.name}
                                href={item.href}
                                className={`whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium ${
                                    item.current
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                                }`}
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
