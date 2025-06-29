import { useReducer, useCallback, useContext, createContext, useEffect, ReactNode, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import axios from 'axios';
import {
  QuickRequestState,
  QuickRequestStep,
  QuickRequestAction,
  QuickRequestContextValue,
  ValidationResult,
  UseQuickRequestReturn,
  PatientInsuranceData,
  ClinicalBillingData,
  ProductSelectionData,
  DocuSealIVRData,
  ReviewSubmitData,
} from '@/types/quickRequest';

// Map kebab-case steps to camelCase data keys
const stepToDataKey = {
  'patient-insurance': 'patientInsurance',
  'clinical-billing': 'clinicalBilling',
  'product-selection': 'productSelection',
  'docuseal-ivr': 'docuSealIVR',
  'review-submit': 'reviewSubmit',
} as const;

// Initial state
const initialState: QuickRequestState = {
  currentStep: 'patient-insurance',
  data: {},
  validation: {},
  navigation: {
    canGoBack: false,
    canGoForward: false,
    completedSteps: [],
    visitedSteps: ['patient-insurance'],
  },
  metadata: {
    startedAt: new Date().toISOString(),
    lastModifiedAt: new Date().toISOString(),
    sessionId: crypto.randomUUID(),
    userId: '',
  },
};

// Extended action types to include UPDATE_DATA
type ExtendedQuickRequestAction = QuickRequestAction | 
  { type: 'UPDATE_DATA'; payload: Partial<QuickRequestState['data']> };

// Reducer
function quickRequestReducer(
  state: QuickRequestState,
  action: ExtendedQuickRequestAction
): QuickRequestState {
  switch (action.type) {
    case 'SET_STEP':
      return {
        ...state,
        currentStep: action.payload,
        navigation: {
          ...state.navigation,
          visitedSteps: state.navigation.visitedSteps.includes(action.payload)
            ? state.navigation.visitedSteps
            : [...state.navigation.visitedSteps, action.payload],
        },
        metadata: {
          ...state.metadata,
          lastModifiedAt: new Date().toISOString(),
        },
      };

    case 'SET_STEP_DATA':
      const dataKey = stepToDataKey[action.payload.step];
      return {
        ...state,
        data: {
          ...state.data,
          [dataKey]: {
            ...state.data[dataKey as keyof typeof state.data],
            ...action.payload.data,
          },
        },
        metadata: {
          ...state.metadata,
          lastModifiedAt: new Date().toISOString(),
        },
      };

    case 'UPDATE_DATA':
      return {
        ...state,
        data: {
          ...state.data,
          ...action.payload,
        },
        metadata: {
          ...state.metadata,
          lastModifiedAt: new Date().toISOString(),
        },
      };

    case 'SET_VALIDATION':
      return {
        ...state,
        validation: {
          ...state.validation,
          [action.payload.step]: action.payload.results,
        },
      };

    case 'COMPLETE_STEP':
      const completedSteps = state.navigation.completedSteps.includes(action.payload)
        ? state.navigation.completedSteps
        : [...state.navigation.completedSteps, action.payload];
      
      return {
        ...state,
        navigation: {
          ...state.navigation,
          completedSteps,
          canGoForward: true,
        },
      };

    case 'RESET_WORKFLOW':
      return {
        ...initialState,
        metadata: {
          ...initialState.metadata,
          sessionId: crypto.randomUUID(),
          userId: state.metadata.userId,
        },
      };

    case 'LOAD_STATE':
      return action.payload;

    default:
      return state;
  }
}

// Extended context value
interface ExtendedQuickRequestContextValue extends Omit<QuickRequestContextValue, 'dispatch'> {
  dispatch: React.Dispatch<ExtendedQuickRequestAction>;
}

// Context
const QuickRequestContext = createContext<ExtendedQuickRequestContextValue | null>(null);

// Provider component
export function QuickRequestProvider({ children }: { children: ReactNode }) {
  const [state, dispatch] = useReducer(quickRequestReducer, initialState);
  const { props } = usePage<{ auth?: { user?: { id?: string } } }>();

  // Set user ID on mount
  useEffect(() => {
    if (props.auth?.user?.id && state.metadata.userId !== props.auth.user.id) {
      dispatch({
        type: 'UPDATE_DATA',
        payload: { userId: props.auth.user.id } as any,
      });
    }
  }, [props.auth?.user?.id]);

  // Auto-save progress
  useEffect(() => {
    const dataKey = stepToDataKey[state.currentStep];
    const currentData = state.data[dataKey as keyof typeof state.data];
    
    const saveTimer = setTimeout(() => {
      if (currentData && Object.keys(currentData).length > 0) {
        saveProgress(state.metadata.sessionId, state.currentStep, currentData);
      }
    }, 5000); // Auto-save every 5 seconds

    return () => clearTimeout(saveTimer);
  }, [state.data, state.currentStep, state.metadata.sessionId]);

  // API methods
  const saveProgress = useCallback(
    async (sessionId: string, step: QuickRequestStep, data: any) => {
      try {
        await axios.post('/api/v1/quick-request/save-progress', {
          sessionId,
          step,
          data,
        });
      } catch (error) {
        console.error('Failed to save progress:', error);
      }
    },
    []
  );

  const loadProgress = useCallback(async (sessionId: string) => {
    try {
      const response = await axios.get(`/api/v1/quick-request/load-progress/${sessionId}`);
      if (response.data.success && response.data.data) {
        dispatch({ type: 'LOAD_STATE', payload: response.data.data });
        return response.data.data;
      }
      return null;
    } catch (error) {
      console.error('Failed to load progress:', error);
      return null;
    }
  }, []);

  const createEpisode = useCallback(async (data: QuickRequestState['data']) => {
    try {
      // Ensure we have the required DocuSeal submission ID
      if (!data.docuSealIVR?.documents?.length && 
          data.productSelection?.products?.length) {
        console.warn('DocuSeal submission may be required for this product');
      }

      const response = await axios.post('/api/v1/quick-request/episodes', {
        ...data,
        sessionId: state.metadata.sessionId,
      });
      
      return response.data.data;
    } catch (error) {
      console.error('Failed to create episode:', error);
      throw error;
    }
  }, [state.metadata.sessionId]);

  const validateStep = useCallback(
    async (step: QuickRequestStep, data: any): Promise<ValidationResult[]> => {
      try {
        const response = await axios.post(`/api/v1/quick-request/validate/${step}`, data);
        return response.data.data || [];
      } catch (error: any) {
        if (error.response?.status === 422) {
          return error.response.data.errors.map((err: any) => ({
            field: err.field || err.path,
            rule: err.code || 'validation_error',
            passed: false,
            message: err.message || 'Validation failed',
            severity: 'error' as const,
          }));
        }
        throw error;
      }
    },
    []
  );

  const contextValue: ExtendedQuickRequestContextValue = {
    state,
    dispatch,
    api: {
      saveProgress,
      loadProgress,
      createEpisode,
      validateStep,
    },
  };

  return (
    <QuickRequestContext.Provider value={contextValue}>
      {children}
    </QuickRequestContext.Provider>
  );
}

// Extended return type to include missing properties
interface ExtendedUseQuickRequestReturn extends UseQuickRequestReturn {
  formData: QuickRequestState['data'];
  updateFormData: (data: any) => void;
  canGoBack: boolean;
  canGoNext: boolean;
  progress: number;
}

// Main hook
export function useQuickRequest(): ExtendedUseQuickRequestReturn {
  const context = useContext(QuickRequestContext);
  if (!context) {
    throw new Error('useQuickRequest must be used within QuickRequestProvider');
  }

  const { state, dispatch, api } = context;
  const [isLoading, setIsLoading] = useState(false);

  const steps: QuickRequestStep[] = [
    'patient-insurance',
    'clinical-billing',
    'product-selection',
    'docuseal-ivr',
    'review-submit',
  ];

  const currentStepIndex = steps.indexOf(state.currentStep);

  const goToStep = useCallback(
    (step: QuickRequestStep) => {
      // Don't allow jumping to DocuSeal step if product selection not complete
      if (step === 'docuseal-ivr' && !state.data.productSelection?.products?.length) {
        console.warn('Cannot proceed to DocuSeal without product selection');
        return;
      }
      dispatch({ type: 'SET_STEP', payload: step });
    },
    [dispatch, state.data]
  );

  const goNext = useCallback(async () => {
    if (currentStepIndex < steps.length - 1) {
      const nextStep = steps[currentStepIndex + 1]!; // We know it exists due to the bounds check
      setIsLoading(true);
      try {
        // Get current step data
        const dataKey = stepToDataKey[state.currentStep];
        const currentData = state.data[dataKey as keyof typeof state.data];
        
        // Validate current step before proceeding (only if there's data)
        const validationResults = currentData 
          ? await api.validateStep(state.currentStep, currentData)
          : [];
        
        if (validationResults.some(r => !r.passed && r.severity === 'error')) {
          dispatch({ type: 'SET_VALIDATION', payload: { 
            step: state.currentStep, 
            results: validationResults 
          }});
          throw new Error('Please fix validation errors before proceeding');
        }
        
        dispatch({ type: 'COMPLETE_STEP', payload: state.currentStep });
        dispatch({ type: 'SET_STEP', payload: nextStep });
      } catch (error) {
        console.error('Navigation error:', error);
        throw error;
      } finally {
        setIsLoading(false);
      }
    }
  }, [currentStepIndex, state.currentStep, state.data, api, dispatch]);

  const goBack = useCallback(() => {
    if (currentStepIndex > 0) {
      const prevStep = steps[currentStepIndex - 1]!; // We know it exists due to the bounds check
      dispatch({ type: 'SET_STEP', payload: prevStep });
    }
  }, [currentStepIndex, dispatch]);

  const saveStep = useCallback(
    async (data: any) => {
      setIsLoading(true);
      try {
        dispatch({ type: 'SET_STEP_DATA', payload: { step: state.currentStep, data } });
        const dataKey = stepToDataKey[state.currentStep];
        await api.saveProgress(state.metadata.sessionId, state.currentStep, data);
      } finally {
        setIsLoading(false);
      }
    },
    [state.currentStep, state.metadata.sessionId, api, dispatch]
  );

  const updateFormData = useCallback(
    (data: any) => {
      // Map the flat data to the correct step structure
      const mappedData: Partial<QuickRequestState['data']> = {};
      
      // Determine which step data this belongs to based on the fields
      if (data.patient_first_name || data.patient_last_name || data.insurance_name) {
        mappedData.patientInsurance = { ...state.data.patientInsurance, ...data } as PatientInsuranceData;
      } else if (data.provider_name || data.facility_name || data.wound_type) {
        mappedData.clinicalBilling = { ...state.data.clinicalBilling, ...data } as ClinicalBillingData;
      } else if (data.selected_products || data.manufacturer_id) {
        mappedData.productSelection = { ...state.data.productSelection, ...data } as ProductSelectionData;
      } else if (data.docuseal_submission_id) {
        mappedData.docuSealIVR = { ...state.data.docuSealIVR, ...data } as DocuSealIVRData;
      }
      
      dispatch({ type: 'UPDATE_DATA', payload: mappedData });
    },
    [dispatch, state.data]
  );

  const submitEpisode = useCallback(async () => {
    setIsLoading(true);
    try {
      // Validate all steps
      for (const step of steps) {
        const dataKey = stepToDataKey[step];
        const stepData = state.data[dataKey as keyof typeof state.data];
        
        if (stepData) {
          const results = await api.validateStep(step, stepData);
          if (results.some(r => !r.passed && r.severity === 'error')) {
            throw new Error(`Validation failed for step: ${step}`);
          }
        }
      }

      // Create episode
      const episode = await api.createEpisode(state.data);
      
      if (!episode?.id) {
        throw new Error('Failed to create episode');
      }

      // Reset workflow
      dispatch({ type: 'RESET_WORKFLOW' });
      
      // Navigate to success page using Inertia
      router.visit(`/quick-request/success/${episode.id}`, {
        method: 'get',
        data: { 
          episode_id: episode.id,
          submission_id: state.data.docuSealIVR?.documents?.[0]?.id
        }
      });
      
      return episode;
    } catch (error) {
      console.error('Failed to submit episode:', error);
      throw error;
    } finally {
      setIsLoading(false);
    }
  }, [state.data, api, dispatch, steps]);

  const validateCurrentStep = useCallback(
    async (step?: QuickRequestStep) => {
      setIsLoading(true);
      try {
        const stepToValidate: QuickRequestStep = step || state.currentStep;
        const dataKey = stepToDataKey[stepToValidate];
        const data = state.data[dataKey as keyof typeof state.data];
        
        if (!data) {
          return [];
        }
        
        const results = await api.validateStep(stepToValidate, data);
        dispatch({ type: 'SET_VALIDATION', payload: { step: stepToValidate, results } });
        
        return results;
      } finally {
        setIsLoading(false);
      }
    },
    [state.currentStep, state.data, api, dispatch]
  );

  const resetWorkflow = useCallback(() => {
    dispatch({ type: 'RESET_WORKFLOW' });
  }, [dispatch]);

  // Get current step data
  const currentDataKey = stepToDataKey[state.currentStep];
  const currentStepData = state.data[currentDataKey as keyof typeof state.data];

  // Extract errors and warnings from validation
  const currentValidation = state.validation[state.currentStep] || [];
  const errors = currentValidation
    .filter(v => !v.passed && v.severity === 'error')
    .map(v => ({
      field: v.field,
      code: v.rule,
      message: v.message || 'Validation failed',
    }));
    
  const warnings = currentValidation
    .filter(v => v.severity === 'warning')
    .map(v => ({
      code: v.rule,
      message: v.message || 'Warning',
      field: v.field,
    }));

  return {
    state,
    currentStepData,
    formData: state.data,
    isLoading,
    errors,
    warnings,
    goToStep,
    goNext,
    goBack,
    saveStep,
    updateFormData,
    submitEpisode,
    validateStep: validateCurrentStep,
    resetWorkflow,
    canGoBack: currentStepIndex > 0,
    canGoNext: currentStepIndex < steps.length - 1,
    progress: ((currentStepIndex + 1) / steps.length) * 100,
  };
}
