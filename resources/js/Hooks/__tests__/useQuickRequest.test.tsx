import { renderHook, act, waitFor } from '@testing-library/react';
import { ReactNode } from 'react';
import { useQuickRequest, QuickRequestProvider } from '../useQuickRequest';
import axios from 'axios';
import { useNavigate } from 'react-router-dom';
import { usePage } from '@inertiajs/react';

// Mock dependencies
jest.mock('axios');
jest.mock('react-router-dom', () => ({
  useNavigate: jest.fn(),
}));
jest.mock('@inertiajs/react', () => ({
  usePage: jest.fn(),
}));

const mockedAxios = axios as jest.Mocked<typeof axios>;
const mockedNavigate = jest.fn();
const mockedUsePage = usePage as jest.MockedFunction<typeof usePage>;

describe('useQuickRequest', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    (useNavigate as jest.Mock).mockReturnValue(mockedNavigate);
    mockedUsePage.mockReturnValue({
      props: {
        auth: {
          user: {
            id: 'test-user-123',
          },
        },
      },
    } as any);
  });

  const wrapper = ({ children }: { children: ReactNode }) => (
    <QuickRequestProvider>{children}</QuickRequestProvider>
  );

  test('initializes with correct default state', () => {
    const { result } = renderHook(() => useQuickRequest(), { wrapper });

    expect(result.current.state.currentStep).toBe('patient-insurance');
    expect(result.current.state.data).toEqual({});
    expect(result.current.state.navigation.completedSteps).toEqual([]);
    expect(result.current.state.navigation.visitedSteps).toEqual(['patient-insurance']);
  });

  test('navigates to next step', async () => {
    const { result } = renderHook(() => useQuickRequest(), { wrapper });

    // Mock validation response
    mockedAxios.post.mockResolvedValueOnce({
      data: { data: [] },
    });

    // Set data for current step
    await act(async () => {
      await result.current.saveStep({
        patient: {
          firstName: 'John',
          lastName: 'Doe',
          dateOfBirth: '1990-01-01',
        },
      });
    });

    // Navigate to next step
    await act(async () => {
      await result.current.goNext();
    });

    expect(result.current.state.currentStep).toBe('clinical-billing');
    expect(result.current.state.navigation.completedSteps).toContain('patient-insurance');
  });

  test('navigates to previous step', () => {
    const { result } = renderHook(() => useQuickRequest(), { wrapper });

    // First navigate forward
    act(() => {
      result.current.goToStep('clinical-billing');
    });

    // Then navigate back
    act(() => {
      result.current.goBack();
    });

    expect(result.current.state.currentStep).toBe('patient-insurance');
  });

  test('saves step data and calls API', async () => {
    const { result } = renderHook(() => useQuickRequest(), { wrapper });

    const stepData = {
      patient: {
        firstName: 'Jane',
        lastName: 'Smith',
      },
    };

    mockedAxios.post.mockResolvedValueOnce({ data: { success: true } });

    await act(async () => {
      await result.current.saveStep(stepData);
    });

    expect(mockedAxios.post).toHaveBeenCalledWith(
      '/api/v1/quick-request/save-progress',
      expect.objectContaining({
        sessionId: expect.any(String),
        step: 'patient-insurance',
        data: stepData,
      })
    );
  });

  test('validates step data', async () => {
    const { result } = renderHook(() => useQuickRequest(), { wrapper });

    const validationResults = [
      { field: 'patient.firstName', rule: 'required', passed: false, message: 'First name is required', severity: 'error' },
    ];

    mockedAxios.post.mockResolvedValueOnce({
      data: { data: validationResults },
    });

    const results = await act(async () => {
      return await result.current.validateStep();
    });

    expect(results).toEqual(validationResults);
    expect(result.current.errors).toHaveLength(1);
    expect(result.current.errors[0].message).toBe('First name is required');
  });

  test('submits episode successfully', async () => {
    const { result } = renderHook(() => useQuickRequest(), { wrapper });

    // Set data for all steps
    const steps = [
      'patient-insurance',
      'clinical-billing',
      'product-selection',
      'docuseal-ivr',
      'review-submit',
    ];

    for (const step of steps) {
      act(() => {
        result.current.state.data[step] = { test: 'data' };
      });
    }

    // Mock validation responses
    mockedAxios.post.mockImplementation((url) => {
      if (url.includes('validate')) {
        return Promise.resolve({ data: { data: [] } });
      }
      if (url.includes('episodes')) {
        return Promise.resolve({
          data: { data: { id: 'episode-123' } },
        });
      }
      return Promise.resolve({ data: {} });
    });

    await act(async () => {
      await result.current.submitEpisode();
    });

    expect(mockedNavigate).toHaveBeenCalledWith('/quick-request/success/episode-123');
  });

  test('handles validation errors on submit', async () => {
    const { result } = renderHook(() => useQuickRequest(), { wrapper });

    // Mock validation error
    mockedAxios.post.mockResolvedValueOnce({
      data: {
        data: [
          { field: 'patient.email', rule: 'email', passed: false, message: 'Invalid email', severity: 'error' },
        ],
      },
    });

    await expect(
      act(async () => {
        await result.current.submitEpisode();
      })
    ).rejects.toThrow('Missing data for step: patient-insurance');
  });

  test('resets workflow', () => {
    const { result } = renderHook(() => useQuickRequest(), { wrapper });

    // Make some changes
    act(() => {
      result.current.goToStep('clinical-billing');
      result.current.state.data['patient-insurance'] = { test: 'data' };
    });

    // Reset
    act(() => {
      result.current.resetWorkflow();
    });

    expect(result.current.state.currentStep).toBe('patient-insurance');
    expect(result.current.state.data).toEqual({});
    expect(result.current.state.navigation.completedSteps).toEqual([]);
  });

  test('auto-saves progress after delay', async () => {
    const { result } = renderHook(() => useQuickRequest(), { wrapper });

    mockedAxios.post.mockResolvedValue({ data: { success: true } });

    act(() => {
      result.current.state.data['patient-insurance'] = { test: 'data' };
    });

    // Wait for auto-save delay
    await waitFor(() => {
      expect(mockedAxios.post).toHaveBeenCalledWith(
        '/api/v1/quick-request/save-progress',
        expect.any(Object)
      );
    }, { timeout: 6000 });
  });

  test('extracts errors and warnings from validation', () => {
    const { result } = renderHook(() => useQuickRequest(), { wrapper });

    act(() => {
      result.current.state.validation['patient-insurance'] = [
        { field: 'patient.firstName', rule: 'required', passed: false, message: 'Required', severity: 'error' },
        { field: 'patient.email', rule: 'format', passed: false, message: 'Invalid format', severity: 'warning' },
        { field: 'patient.lastName', rule: 'length', passed: true, message: 'Valid', severity: 'info' },
      ];
    });

    expect(result.current.errors).toHaveLength(1);
    expect(result.current.errors[0].code).toBe('required');
    expect(result.current.warnings).toHaveLength(1);
    expect(result.current.warnings[0].code).toBe('format');
  });
});