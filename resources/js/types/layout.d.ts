import { ReactNode } from 'react';
import { User } from './index';

export interface MainLayoutProps {
    children: ReactNode;
    header?: ReactNode;
    user: User;
}
