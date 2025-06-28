declare module '@/Layouts/AuthenticatedLayout' {
    import { User } from '@/types';
    import { ReactNode } from 'react';

    interface Props {
        user: User;
        header?: ReactNode;
        children: ReactNode;
    }

    const AuthenticatedLayout: React.FC<Props>;
    export default AuthenticatedLayout;
}


declare module '@/Components/Form/NumberInput' {
    import { InputHTMLAttributes, ReactNode } from 'react';

    interface Props extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type' | 'onChange'> {
        label: string;
        value: string | number;
        onChange: (value: string) => void;
        error?: string;
        min?: number;
        max?: number;
        step?: string | number;
        required?: boolean;
        disabled?: boolean;
    }

    const NumberInput: React.FC<Props>;
    export default NumberInput;
}

declare module '@/Components/Form/DateInput' {
    import { InputHTMLAttributes, ReactNode } from 'react';

    interface Props extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type' | 'onChange'> {
        label: string;
        value: string;
        onChange: (value: string) => void;
        error?: string;
        icon?: ReactNode;
        required?: boolean;
        disabled?: boolean;
    }

    const DateInput: React.FC<Props>;
    export default DateInput;
}

declare module '@/Components/Form/TextAreaInput' {
    import { TextareaHTMLAttributes } from 'react';

    interface Props extends Omit<TextareaHTMLAttributes<HTMLTextAreaElement>, 'onChange'> {
        label: string;
        value: string;
        onChange: (value: string) => void;
        error?: string;
        required?: boolean;
        disabled?: boolean;
        rows?: number;
    }

    const TextAreaInput: React.FC<Props>;
    export default TextAreaInput;
}

export interface MainLayoutProps {
    children: ReactNode;
    header?: ReactNode;
    user: User;
}

export interface ButtonProps extends ComponentProps<'button'> {
    children: ReactNode;
    className?: string;
    variant?: 'primary' | 'secondary' | 'danger';
    disabled?: boolean;
    type?: 'button' | 'submit' | 'reset';
}
