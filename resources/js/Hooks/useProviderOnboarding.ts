// Hook for managing provider onboarding steps
import { useState, useCallback, useMemo } from 'react';
import type { 
  ProviderRegistrationData, 
  PracticeType, 
  OnboardingStep 
} from '@/types/provider';

// Define step flows for each practice type
const STEP_FLOWS: Record<PracticeType, string[]> = {
  solo_practitioner: [
    'review', 
    'practice-type', 
    'personal', 
    'organization', 
    'facility', 
    'credentials', 
    'billing'
  ],
  group_practice: [
    'review', 
    'practice-type', 
    'personal', 
    'organization', 
    'facility', 
    'credentials', 
    'billing'
  ],
  existing_organization: [
    'review', 
    'practice-type', 
    'personal', 
    'facility-selection', 
    'credentials'
  ],
};

interface UseProviderOnboardingProps {
  initialData: Partial<ProviderRegistrationData>;
  onComplete: (data: ProviderRegistrationData) => void;
}

export function useProviderOnboarding({ 
  initialData, 
  onComplete 
}: UseProviderOnboardingProps) {
  const [currentStep, setCurrentStep] = useState('review');
  const [completedSteps, setCompletedSteps] = useState<string[]>([]);
  const [data, setData] = useState<Partial<ProviderRegistrationData>>(initialData);
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Get current step flow based on practice type
  const currentFlow = useMemo(() => {
    const practiceType = data.practice_type || 'solo_practitioner';
    return STEP_FLOWS[practiceType];
  }, [data.practice_type]);

  // Calculate progress
  const progress = useMemo(() => {
    const currentIndex = currentFlow.indexOf(currentStep);
    return Math.round(((currentIndex + 1) / currentFlow.length) * 100);
  }, [currentFlow, currentStep]);

  // Navigate to next step
  const goToNext = useCallback(() => {
    const currentIndex = currentFlow.indexOf(currentStep);
    if (currentIndex < currentFlow.length - 1) {
      const nextStep = currentFlow[currentIndex + 1];
      setCurrentStep(nextStep);
      if (!completedSteps.includes(currentStep)) {
        setCompletedSteps([...completedSteps, currentStep]);
      }
    } else {
      // Last step - trigger completion
      onComplete(data as ProviderRegistrationData);
    }
  }, [currentFlow, currentStep, completedSteps, data, onComplete]);

  // Navigate to previous step
  const goToPrevious = useCallback(() => {
    const currentIndex = currentFlow.indexOf(currentStep);
    if (currentIndex > 0) {
      setCurrentStep(currentFlow[currentIndex - 1]);
    }
  }, [currentFlow, currentStep]);

  // Update form data
  const updateData = useCallback(<K extends keyof ProviderRegistrationData>(
    field: K,
    value: ProviderRegistrationData[K]
  ) => {
    setData(prev => ({ ...prev, [field]: value }));
    // Clear error for this field
    if (errors[field]) {
      setErrors(prev => {
        const newErrors = { ...prev };
        delete newErrors[field];
        return newErrors;
      });
    }
  }, [errors]);

  // Set validation errors
  const setValidationErrors = useCallback((newErrors: Record<string, string>) => {
    setErrors(newErrors);
  }, []);

  // Check if can proceed to next step
  const canProceed = useMemo(() => {
    return Object.keys(errors).length === 0;
  }, [errors]);

  return {
    currentStep,
    completedSteps,
    data,
    errors,
    progress,
    currentFlow,
    goToNext,
    goToPrevious,
    updateData,
    setValidationErrors,
    canProceed,
    isFirstStep: currentFlow.indexOf(currentStep) === 0,
    isLastStep: currentFlow.indexOf(currentStep) === currentFlow.length - 1,
  };
}
