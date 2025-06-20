import { useState, useEffect } from 'react';
import { ValidationRules, roleApi } from './api';

export interface ValidationError {
    field: string;
    message: string;
}

export interface FormValidationState {
    errors: ValidationError[];
    isValid: boolean;
    isValidating: boolean;
}

export interface UseFormValidationReturn {
    errors: ValidationError[];
    isValid: boolean;
    isValidating: boolean;
    validateField: (field: string, value: any) => Promise<boolean>;
    validateForm: (data: Record<string, any>) => Promise<boolean>;
    clearErrors: () => void;
    clearFieldError: (field: string) => void;
    setFieldError: (field: string, message: string) => void;
    getFieldError: (field: string) => string | undefined;
}

export const useFormValidation = (
    validationRules?: ValidationRules,
    customValidators?: Record<string, (value: any) => Promise<string | null>>
): UseFormValidationReturn => {
    const [state, setState] = useState<FormValidationState>({
        errors: [],
        isValid: true,
        isValidating: false,
    });

    const [rules, setRules] = useState<ValidationRules | null>(validationRules || null);

    // Load validation rules if not provided
    useEffect(() => {
        if (!validationRules) {
            loadValidationRules();
        }
    }, [validationRules]);

    const loadValidationRules = async () => {
        try {
            const loadedRules = await roleApi.getValidationRules();
            setRules(loadedRules);
        } catch (error) {
            console.error('Failed to load validation rules:', error);
        }
    };

    const validateField = async (field: string, value: any): Promise<boolean> => {
        if (!rules) return true;

        setState(prev => ({ ...prev, isValidating: true }));

        try {
            const fieldRules = rules.rules[field];
            if (!fieldRules) return true;

            const errors: string[] = [];

            // Required validation
            if (fieldRules.required && (!value || (Array.isArray(value) && value.length === 0))) {
                errors.push(rules.messages[`${field}.required`] || `${field} is required`);
            }

            // Skip other validations if field is empty and not required
            if (!value && !fieldRules.required) {
                clearFieldError(field);
                return true;
            }

            // Max length validation
            if (fieldRules.max_length && value && value.toString().length > fieldRules.max_length) {
                errors.push(rules.messages[`${field}.max_length`] || `${field} must not exceed ${fieldRules.max_length} characters`);
            }

            // Min items validation (for arrays)
            if (fieldRules.min_items && Array.isArray(value) && value.length < fieldRules.min_items) {
                errors.push(rules.messages[`${field}.min_items`] || `${field} must have at least ${fieldRules.min_items} items`);
            }

            // Pattern validation
            if (fieldRules.pattern && value) {
                const regex = new RegExp(fieldRules.pattern);
                if (!regex.test(value.toString())) {
                    errors.push(rules.messages[`${field}.pattern`] || `${field} format is invalid`);
                }
            }

            // Custom validators
            if (customValidators && customValidators[field]) {
                const customError = await customValidators[field](value);
                if (customError) {
                    errors.push(customError);
                }
            }

            // Update errors
            if (errors.length > 0) {
                setFieldError(field, errors[0] || '');
                return false;
            } else {
                clearFieldError(field);
                return true;
            }
        } catch (error) {
            console.error('Validation error:', error);
            return false;
        } finally {
            setState(prev => ({ ...prev, isValidating: false }));
        }
    };

    const validateForm = async (data: Record<string, any>): Promise<boolean> => {
        if (!rules) return true;

        setState(prev => ({ ...prev, isValidating: true }));

        try {
            const validationPromises = Object.keys(data).map(field =>
                validateField(field, data[field])
            );

            const results = await Promise.all(validationPromises);
            const isFormValid = results.every(result => result);

            setState(prev => ({
                ...prev,
                isValid: isFormValid,
                isValidating: false,
            }));

            return isFormValid;
        } catch (error) {
            console.error('Form validation error:', error);
            setState(prev => ({ ...prev, isValidating: false }));
            return false;
        }
    };

    const clearErrors = () => {
        setState(prev => ({
            ...prev,
            errors: [],
            isValid: true,
        }));
    };

    const clearFieldError = (field: string) => {
        setState(prev => ({
            ...prev,
            errors: prev.errors.filter(error => error.field !== field),
            isValid: prev.errors.filter(error => error.field !== field).length === 0,
        }));
    };

    const setFieldError = (field: string, message: string) => {
        setState(prev => {
            const newErrors = prev.errors.filter(error => error.field !== field);
            newErrors.push({ field, message });

            return {
                ...prev,
                errors: newErrors,
                isValid: false,
            };
        });
    };

    const getFieldError = (field: string): string | undefined => {
        const error = state.errors.find(error => error.field === field);
        return error?.message;
    };

    return {
        errors: state.errors,
        isValid: state.isValid,
        isValidating: state.isValidating,
        validateField,
        validateForm,
        clearErrors,
        clearFieldError,
        setFieldError,
        getFieldError,
    };
};

// Utility function to handle API validation errors
export const handleApiValidationErrors = (
    error: any,
    setFieldError: (field: string, message: string) => void
) => {
    if (error.status === 422 && error.data?.errors) {
        // Laravel validation errors
        Object.keys(error.data.errors).forEach(field => {
            const messages = error.data.errors[field];
            if (Array.isArray(messages) && messages.length > 0) {
                setFieldError(field, messages[0]);
            }
        });
    } else if (error.data?.message) {
        // Generic error message
        setFieldError('general', error.data.message);
    }
};

// Common validation patterns
export const validationPatterns = {
    slug: /^[a-z0-9-_]+$/,
    email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
    phone: /^\+?[\d\s\-\(\)]+$/,
    alphanumeric: /^[a-zA-Z0-9]+$/,
    alphanumericWithSpaces: /^[a-zA-Z0-9\s]+$/,
};

// Common validation functions
export const validators = {
    required: (value: any): string | null => {
        if (!value || (Array.isArray(value) && value.length === 0)) {
            return 'This field is required';
        }
        return null;
    },

    email: (value: string): string | null => {
        if (value && !validationPatterns.email.test(value)) {
            return 'Please enter a valid email address';
        }
        return null;
    },

    minLength: (min: number) => (value: string): string | null => {
        if (value && value.length < min) {
            return `Must be at least ${min} characters long`;
        }
        return null;
    },

    maxLength: (max: number) => (value: string): string | null => {
        if (value && value.length > max) {
            return `Must not exceed ${max} characters`;
        }
        return null;
    },

    pattern: (pattern: RegExp, message: string) => (value: string): string | null => {
        if (value && !pattern.test(value)) {
            return message;
        }
        return null;
    },

    unique: (checkUnique: (value: string) => Promise<boolean>, message: string = 'This value already exists') =>
        async (value: string): Promise<string | null> => {
            if (value) {
                const isUnique = await checkUnique(value);
                if (!isUnique) {
                    return message;
                }
            }
            return null;
        },
};
