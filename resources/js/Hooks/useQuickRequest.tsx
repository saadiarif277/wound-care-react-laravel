<<<<<<< HEAD
import { useReducer, useCallback, useContext, createContext, useEffect, ReactNode } from 'react';
import { useNavigate } from 'react-router-dom';
import { usePage } from '@inertiajs/react';
=======
import { useReducer, useCallback, useContext, createContext, useEffect, ReactNode, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
>>>>>>> origin/provider-side
import axios from 'axios';
import {
  QuickRequestState,
  QuickRequestStep,
  QuickRequestAction,
  QuickRequestContextValue,
  ValidationResult,
<<<<<<< HEAD
  ValidationError,
  Warning,
  Episode,
  UseQuickRequestReturn,
} from '@/types/quickRequest';

=======
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

>>>>>>> origin/provider-side
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

<<<<<<< HEAD
// Reducer
function quickRequestReducer(
  state: QuickRequestState,
  action: QuickRequestAction
=======
// Extended action types to include UPDATE_DATA
type ExtendedQuickRequestAction = QuickRequestAction | 
  { type: 'UPDATE_DATA'; payload: Partial<QuickRequestState['data']> };

// Reducer
function quickRequestReducer(
  state: QuickRequestState,
  action: ExtendedQuickRequestAction
>>>>>>> origin/provider-side
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
<<<<<<< HEAD
=======
      const dataKey = stepToDataKey[action.payload.step];
>>>>>>> origin/provider-side
      return {
        ...state,
        data: {
          ...state.data,
<<<<<<< HEAD
          [action.payload.step]: action.payload.data,
=======
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
>>>>>>> origin/provider-side
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

<<<<<<< HEAD
// Context
const QuickRequestContext = createContext<QuickRequestContextValue | null>(null);
=======
// Extended context value
interface ExtendedQuickRequestContextValue extends Omit<QuickRequestContextValue, 'dispatch'> {
  dispatch: React.Dispatch<ExtendedQuickRequestAction>;
}

// Context
const QuickRequestContext = createContext<ExtendedQuickRequestContextValue | null>(null);
>>>>>>> origin/provider-side

// Provider component
export function QuickRequestProvider({ children }: { children: ReactNode }) {
  const [state, dispatch] = useReducer(quickRequestReducer, initialState);
<<<<<<< HEAD
  const { props } = usePage();
  const navigate = useNavigate();

  // Set user ID on mount
  useEffect(() => {
    if (props.auth?.user?.id) {
      dispatch({
        type: 'SET_STEP_DATA',
        payload: {
          step: 'patient-insurance',
          data: { ...state.data['patient-insurance'], userId: props.auth.user.id },
        },
=======
  const { props } = usePage<{ auth?: { user?: { id?: string } } }>();

  // Set user ID on mount
  useEffect(() => {
    if (props.auth?.user?.id && state.metadata.userId !== props.auth.user.id) {
      dispatch({
        type: 'UPDATE_DATA',
        payload: { userId: props.auth.user.id } as any,
>>>>>>> origin/provider-side
      });
    }
  }, [props.auth?.user?.id]);

  // Auto-save progress
  useEffect(() => {
<<<<<<< HEAD
    const saveTimer = setTimeout(() => {
      if (state.data && Object.keys(state.data).length > 0) {
        saveProgress(state.currentStep, state.data[state.currentStep]);
=======
    const dataKey = stepToDataKey[state.currentStep];
    const currentData = state.data[dataKey as keyof typeof state.data];
    
    const saveTimer = setTimeout(() => {
      if (currentData && Object.keys(currentData).length > 0) {
        saveProgress(state.metadata.sessionId, state.currentStep, currentData);
>>>>>>> origin/provider-side
      }
    }, 5000); // Auto-save every 5 seconds

    return () => clearTimeout(saveTimer);
<<<<<<< HEAD
  }, [state.data, state.currentStep]);

  // API methods
  const saveProgress = useCallback(
    async (step: QuickRequestStep, data: any) => {
      try {
        await axios.post('/api/v1/quick-request/save-progress', {
          sessionId: state.metadata.sessionId,
=======
  }, [state.data, state.currentStep, state.metadata.sessionId]);

  // API methods
  const saveProgress = useCallback(
    async (sessionId: string, step: QuickRequestStep, data: any) => {
      try {
        await axios.post('/api/v1/quick-request/save-progress', {
          sessionId,
>>>>>>> origin/provider-side
          step,
          data,
        });
      } catch (error) {
        console.error('Failed to save progress:', error);
      }
    },
<<<<<<< HEAD
    [state.metadata.sessionId]
=======
    []
>>>>>>> origin/provider-side
  );

  const loadProgress = useCallback(async (sessionId: string) => {
    try {
      const response = await axios.get(`/api/v1/quick-request/load-progress/${sessionId}`);
<<<<<<< HEAD
      return response.data.data;
=======
      if (response.data.success && response.data.data) {
        dispatch({ type: 'LOAD_STATE', payload: response.data.data });
        return response.data.data;
      }
      return null;
>>>>>>> origin/provider-side
    } catch (error) {
      console.error('Failed to load progress:', error);
      return null;
    }
  }, []);

  const createEpisode = useCallback(async (data: QuickRequestState['data']) => {
<<<<<<< HEAD
    const response = await axios.post('/api/v1/quick-request/episodes', data);
    return response.data.data;
  }, []);
=======
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
>>>>>>> origin/provider-side

  const validateStep = useCallback(
    async (step: QuickRequestStep, data: any): Promise<ValidationResult[]> => {
      try {
        const response = await axios.post(`/api/v1/quick-request/validate/${step}`, data);
<<<<<<< HEAD
        return response.data.data;
      } catch (error: any) {
        if (error.response?.status === 422) {
          return error.response.data.errors.map((err: any) => ({
            field: err.field,
            rule: err.code,
            passed: false,
            message: err.message,
            severity: 'error',
=======
        return response.data.data || [];
      } catch (error: any) {
        if (error.response?.status === 422) {
          return error.response.data.errors.map((err: any) => ({
            field: err.field || err.path,
            rule: err.code || 'validation_error',
            passed: false,
            message: err.message || 'Validation failed',
            severity: 'error' as const,
>>>>>>> origin/provider-side
          }));
        }
        throw error;
      }
    },
    []
  );

<<<<<<< HEAD
  const contextValue: QuickRequestContextValue = {
=======
  const contextValue: ExtendedQuickRequestContextValue = {
>>>>>>> origin/provider-side
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

<<<<<<< HEAD
// Main hook
export function useQuickRequest(): UseQuickRequestReturn {
=======
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
>>>>>>> origin/provider-side
  const context = useContext(QuickRequestContext);
  if (!context) {
    throw new Error('useQuickRequest must be used within QuickRequestProvider');
  }

  const { state, dispatch, api } = context;
<<<<<<< HEAD
  const navigate = useNavigate();
=======
  const [isLoading, setIsLoading] = useState(false);
>>>>>>> origin/provider-side

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
<<<<<<< HEAD
      dispatch({ type: 'SET_STEP', payload: step });
    },
    [dispatch]
=======
      // Don't allow jumping to DocuSeal step if product selection not complete
      if (step === 'docuseal-ivr' && !state.data.productSelection?.products?.length) {
        console.warn('Cannot proceed to DocuSeal without product selection');
        return;
      }
      dispatch({ type: 'SET_STEP', payload: step });
    },
    [dispatch, state.data]
>>>>>>> origin/provider-side
  );

  const goNext = useCallback(async () => {
    if (currentStepIndex < steps.length - 1) {
<<<<<<< HEAD
      const nextStep = steps[currentStepIndex + 1];
      
      // Validate current step before proceeding
      const validationResults = await api.validateStep(
        state.currentStep,
        state.data[state.currentStep]
      );
      
      if (validationResults.some(r => !r.passed && r.severity === 'error')) {
        throw new Error('Please fix validation errors before proceeding');
      }
      
      dispatch({ type: 'COMPLETE_STEP', payload: state.currentStep });
      dispatch({ type: 'SET_STEP', payload: nextStep });
=======
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
>>>>>>> origin/provider-side
    }
  }, [currentStepIndex, state.currentStep, state.data, api, dispatch]);

  const goBack = useCallback(() => {
    if (currentStepIndex > 0) {
<<<<<<< HEAD
      const prevStep = steps[currentStepIndex - 1];
=======
      const prevStep = steps[currentStepIndex - 1]!; // We know it exists due to the bounds check
>>>>>>> origin/provider-side
      dispatch({ type: 'SET_STEP', payload: prevStep });
    }
  }, [currentStepIndex, dispatch]);

  const saveStep = useCallback(
    async (data: any) => {
<<<<<<< HEAD
      dispatch({ type: 'SET_STEP_DATA', payload: { step: state.currentStep, data } });
      await api.saveProgress(state.currentStep, data);
    },
    [state.currentStep, api, dispatch]
  );

  const submitEpisode = useCallback(async () => {
    // Validate all steps
    for (const step of steps) {
      const stepData = state.data[step];
      if (!stepData) {
        throw new Error(`Missing data for step: ${step}`);
      }
      
      const validationResults = await api.validateStep(step, stepData);
      if (validationResults.some(r => !r.passed && r.severity === 'error')) {
        throw new Error(`Validation failed for step: ${step}`);
      }
    }

    // Create episode
    const episode = await api.createEpisode(state.data);
    
    // Reset workflow
    dispatch({ type: 'RESET_WORKFLOW' });
    
    // Navigate to success page
    navigate(`/quick-request/success/${episode.id}`);
    
    return episode;
  }, [state.data, api, dispatch, navigate]);

  const validateCurrentStep = useCallback(
    async (step?: QuickRequestStep) => {
      const stepToValidate = step || state.currentStep;
      const data = state.data[stepToValidate];
      
      if (!data) {
        return [];
      }
      
      const results = await api.validateStep(stepToValidate, data);
      dispatch({ type: 'SET_VALIDATION', payload: { step: stepToValidate, results } });
      
      return results;
=======
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
>>>>>>> origin/provider-side
    },
    [state.currentStep, state.data, api, dispatch]
  );

  const resetWorkflow = useCallback(() => {
    dispatch({ type: 'RESET_WORKFLOW' });
  }, [dispatch]);

<<<<<<< HEAD
=======
  // Get current step data
  const currentDataKey = stepToDataKey[state.currentStep];
  const currentStepData = state.data[currentDataKey as keyof typeof state.data];

>>>>>>> origin/provider-side
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
<<<<<<< HEAD
    currentStepData: state.data[state.currentStep],
    isLoading: false, // TODO: Implement loading state
=======
    currentStepData,
    formData: state.data,
    isLoading,
>>>>>>> origin/provider-side
    errors,
    warnings,
    goToStep,
    goNext,
    goBack,
    saveStep,
<<<<<<< HEAD
    submitEpisode,
    validateStep: validateCurrentStep,
    resetWorkflow,
  };
}
=======
    updateFormData,
    submitEpisode,
    validateStep: validateCurrentStep,
    resetWorkflow,
    canGoBack: currentStepIndex > 0,
    canGoNext: currentStepIndex < steps.length - 1,
    progress: ((currentStepIndex + 1) / steps.length) * 100,
  };
}
>>>>>>> origin/provider-side
