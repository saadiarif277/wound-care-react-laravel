import { ComponentProps, ReactNode } from 'react';

export interface ButtonProps extends ComponentProps<'button'> {
    children: ReactNode;
    className?: string;
    variant?: 'primary' | 'secondary' | 'danger';
    disabled?: boolean;
    type?: 'button' | 'submit' | 'reset';
}
