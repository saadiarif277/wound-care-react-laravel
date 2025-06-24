import { useReducer, useCallback, useContext, createContext, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import {
  QuickRequestState,
  QuickRequestStep,
  QuickRequestAction,
  QuickRequestContextValue,
  ValidationResult,
  ValidationError,
  Warning,
  Episode,
  UseQuickRequestReturn,
} from '@/types/quickRequest';

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

// Reducer
function quickRequestReducer(
  state: QuickRequestState,
  action: QuickRequestAction
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
      return {
        ...state,
        data: {
          ...state.data,
          [action.payload.step]: action.payload.data,
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

// Context
const QuickRequestContext = createContext<QuickRequestContextValue | null>(null);

// Provider component
export function QuickRequestProvider({ children }: { children: React.ReactNode }) {
  const [state, dispatch] = useReducer(quickRequestReducer, initialState);
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
      });
    }
  }, [props.auth?.user?.id]);

  // Auto-save progress
  useEffect(() => {
    const saveTimer = setTimeout(() => {
      if (state.data && Object.keys(state.data).length > 0) {
        saveProgress(state.currentStep, state.data[state.currentStep]);
      }
    }, 5000); // Auto-save every 5 seconds

    return () => clearTimeout(saveTimer);
  }, [state.data, state.currentStep]);

  // API methods
  const saveProgress = useCallback(
    async (step: QuickRequestStep, data: any) => {
      try {
        await axios.post('/api/v1/quick-request/save-progress', {
          sessionId: state.metadata.sessionId,
          step,
          data,
        });
      } catch (error) {
        console.error('Failed to save progress:', error);
      }
    },
    [state.metadata.sessionId]
  );

  const loadProgress = useCallback(async (sessionId: string) => {
    try {
      const response = await axios.get(`/api/v1/quick-request/load-progress/${sessionId}`);
      return response.data.data;
    } catch (error) {
      console.error('Failed to load progress:', error);
      return null;
    }
  }, []);

  const createEpisode = useCallback(async (data: QuickRequestState['data']) => {
    const response = await axios.post('/api/v1/quick-request/episodes', data);
    return response.data.data;
  }, []);

  const validateStep = useCallback(
    async (step: QuickRequestStep, data: any): Promise<ValidationResult[]> => {
      try {
        const response = await axios.post(`/api/v1/quick-request/validate/${step}`, data);
        return response.data.data;
      } catch (error: any) {
        if (error.response?.status === 422) {
          return error.response.data.errors.map((err: any) => ({
            field: err.field,
            rule: err.code,
            passed: false,
            message: err.message,
            severity: 'error',
          }));
        }
        throw error;
      }
    },
    []
  );

  const contextValue: QuickRequestContextValue = {
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

// Main hook
export function useQuickRequest(): UseQuickRequestReturn {
  const context = useContext(QuickRequestContext);
  if (!context) {
    throw new Error('useQuickRequest must be used within QuickRequestProvider');
  }

  const { state, dispatch, api } = context;
  const navigate = useNavigate();

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
      dispatch({ type: 'SET_STEP', payload: step });
    },
    [dispatch]
  );

  const goNext = useCallback(async () => {
    if (currentStepIndex < steps.length - 1) {
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
    }
  }, [currentStepIndex, state.currentStep, state.data, api, dispatch]);

  const goBack = useCallback(() => {
    if (currentStepIndex > 0) {
      const prevStep = steps[currentStepIndex - 1];
      dispatch({ type: 'SET_STEP', payload: prevStep });
    }
  }, [currentStepIndex, dispatch]);

  const saveStep = useCallback(
    async (data: any) => {
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
    },
    [state.currentStep, state.data, api, dispatch]
  );

  const resetWorkflow = useCallback(() => {
    dispatch({ type: 'RESET_WORKFLOW' });
  }, [dispatch]);

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
    currentStepData: state.data[state.currentStep],
    isLoading: false, // TODO: Implement loading state
    errors,
    warnings,
    goToStep,
    goNext,
    goBack,
    saveStep,
    submitEpisode,
    validateStep: validateCurrentStep,
    resetWorkflow,
  };
}