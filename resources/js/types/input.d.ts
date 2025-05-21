import { ComponentProps, ChangeEvent, ReactNode } from 'react';

export interface InputProps extends Omit<ComponentProps<'input'>, 'onChange'> {
    label?: string;
    error?: string;
    onChange?: (e: ChangeEvent<HTMLInputElement>) => void;
}

export interface SelectProps extends Omit<ComponentProps<'select'>, 'onChange'> {
    label?: string;
    error?: string;
    children: ReactNode;
    onChange?: (e: ChangeEvent<HTMLSelectElement>) => void;
}
