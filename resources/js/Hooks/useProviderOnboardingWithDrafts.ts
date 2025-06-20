// Enhanced version of useProviderOnboarding with draft saving
import { useState, useCallback, useMemo, useEffect } from 'react';
import { debounce } from 'lodash';
import type { 
  ProviderRegistrationData, 
  PracticeType, 
  OnboardingStep 
} from '@/types/provider';

// ... existing imports and STEP_FLOWS ...

interface UseProviderOnboardingProps {
  initialData: Partial<ProviderRegistrationData>;
  onComplete: (data: ProviderRegistrationData) => void;
  token?: string; // For draft identification
  enableDrafts?: boolean;
}

interface DraftData {
  data: Partial<ProviderRegistrationData>;
  currentStep: string;
  completedSteps: string[];
  savedAt: string;
}

export function useProviderOnboarding({ 
  initialData, 
  onComplete,
  token,
  enableDrafts = true
}: UseProviderOnboardingProps) {
  // Draft key for localStorage
  const draftKey = `provider-draft-${token || 'default'}`;

  // Load draft on mount
  const loadDraft = useCallback((): Partial<ProviderRegistrationData> => {
    if (!enableDrafts || !token) return initialData;

    try {
      const savedDraft = localStorage.getItem(draftKey);
      if (savedDraft) {
        const draft: DraftData = JSON.parse(savedDraft);
        
        // Check if draft is less than 24 hours old
        const savedTime = new Date(draft.savedAt).getTime();
        const now = new Date().getTime();
        const hoursSinceSave = (now - savedTime) / (1000 * 60 * 60);
        
        if (hoursSinceSave < 24) {
          // Restore state from draft
          setCurrentStep(draft.currentStep);
          setCompletedSteps(draft.completedSteps);
          return { ...initialData, ...draft.data };
        } else {
          // Draft expired, remove it
          localStorage.removeItem(draftKey);
        }
      }
    } catch (error) {
      console.error('Error loading draft:', error);
    }

    return initialData;
  }, [initialData, token, enableDrafts, draftKey]);

  // Initialize state with draft or initial data
  const [currentStep, setCurrentStep] = useState('review');
  const [completedSteps, setCompletedSteps] = useState<string[]>([]);
  const [data, setData] = useState<Partial<ProviderRegistrationData>>(loadDraft);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [lastSaved, setLastSaved] = useState<Date | null>(null);

  // ... existing code for currentFlow, progress, navigation ...

  // Save draft function
  const saveDraft = useCallback(() => {
    if (!enableDrafts || !token) return;

    try {
      const draft: DraftData = {
        data,
        currentStep,
        completedSteps,
        savedAt: new Date().toISOString(),
      };

      localStorage.setItem(draftKey, JSON.stringify(draft));
      setLastSaved(new Date());
    } catch (error) {
      console.error('Error saving draft:', error);
    }
  }, [data, currentStep, completedSteps, enableDrafts, token, draftKey]);

  // Debounced auto-save
  const debouncedSave = useMemo(
    () => debounce(saveDraft, 2000),
    [saveDraft]
  );

  // Auto-save on data changes
  useEffect(() => {
    if (enableDrafts && currentStep !== 'review' && currentStep !== 'complete') {
      debouncedSave();
    }
  }, [data, currentStep, completedSteps, debouncedSave, enableDrafts]);

  // Clear draft on completion
  const handleComplete = useCallback((finalData: ProviderRegistrationData) => {
    if (enableDrafts && token) {
      localStorage.removeItem(draftKey);
    }
    onComplete(finalData);
  }, [enableDrafts, token, draftKey, onComplete]);

  // ... rest of existing code ...

  // Check if has saved draft
  const hasSavedDraft = useCallback((): boolean => {
    if (!enableDrafts || !token) return false;
    
    try {
      const savedDraft = localStorage.getItem(draftKey);
      return !!savedDraft;
    } catch {
      return false;
    }
  }, [enableDrafts, token, draftKey]);

  // Clear draft manually
  const clearDraft = useCallback(() => {
    if (enableDrafts && token) {
      localStorage.removeItem(draftKey);
      setData(initialData);
      setCurrentStep('review');
      setCompletedSteps([]);
      setLastSaved(null);
    }
  }, [enableDrafts, token, draftKey, initialData]);

  return {
    // ... existing returns ...
    lastSaved,
    hasSavedDraft,
    clearDraft,
    saveDraft, // Manual save
  };
}

// Usage in component:
/*
const {
  // ... other values
  lastSaved,
  hasSavedDraft,
  clearDraft,
} = useProviderOnboarding({
  initialData: { email: invitation.invited_email },
  onComplete: handleComplete,
  token: invitationToken,
  enableDrafts: true,
});

// Show draft indicator
{lastSaved && (
  <p className="text-xs text-gray-500 text-center mt-2">
    Draft saved {format(lastSaved, 'h:mm a')}
  </p>
)}

// Offer to resume or start fresh
{hasSavedDraft() && currentStep === 'review' && (
  <div className="bg-blue-50 p-4 rounded-lg mb-4">
    <p className="text-sm text-blue-700 mb-2">
      We found a saved draft from your previous session.
    </p>
    <div className="flex gap-2">
      <Button size="sm" onClick={() => goToNext()}>
        Resume Registration
      </Button>
      <Button size="sm" variant="secondary" onClick={clearDraft}>
        Start Fresh
      </Button>
    </div>
  </div>
)}
*/
