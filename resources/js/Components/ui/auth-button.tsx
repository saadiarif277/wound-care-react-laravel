import React from 'react';
import { Button } from '@/Components/Button';

interface AuthButtonProps extends React.ComponentProps<typeof Button> {
  // Simplified - no auth state needed with Inertia
}

/**
 * Simplified button for Inertia.js - no auth state management needed
 * Inertia handles authentication automatically
 */
export const AuthButton = React.forwardRef<HTMLButtonElement, AuthButtonProps>(
  ({ 
    disabled, 
    children, 
    className,
    ...props 
  }, ref) => {
    return (
      <Button
        ref={ref}
        disabled={disabled}
        className={className}
        {...props}
      >
        {children}
      </Button>
    );
  }
);

AuthButton.displayName = 'AuthButton';

export default AuthButton; 