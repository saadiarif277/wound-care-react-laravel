import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
import Step4ClinicalBilling from './Step4ClinicalBilling';

// Mock dependencies
vi.mock('@/contexts/ThemeContext', () => ({
  useTheme: () => ({ theme: 'dark' })
}));

vi.mock('@/Components/PlaceOfService/PlaceOfServiceSelector', () => ({
  default: () => <div data-testid="place-of-service-selector" />
}));

vi.mock('@/Components/DiagnosisCode/DiagnosisCodeSelector', () => ({
  default: ({ value, onChange, errors }: any) => (
    <div data-testid="diagnosis-code-selector">
      <input
        data-testid="wound-type"
        value={value.wound_type || ''}
        onChange={(e) => onChange({ ...value, wound_type: e.target.value })}
      />
      <input
        data-testid="primary-diagnosis"
        value={value.primary_diagnosis_code || ''}
        onChange={(e) => onChange({ ...value, primary_diagnosis_code: e.target.value })}
      />
      {errors.wound_type && <span>{errors.wound_type}</span>}
    </div>
  )
}));

describe('Step4ClinicalBilling', () => {
  const mockUpdateFormData = vi.fn();
  
  const defaultProps = {
    formData: {},
    updateFormData: mockUpdateFormData,
    diagnosisCodes: {
      yellow: [
        { code: 'E11.621', description: 'Type 2 diabetes mellitus with foot ulcer' },
        { code: 'E11.622', description: 'Type 2 diabetes mellitus with other skin ulcer' }
      ],
      orange: [
        { code: 'L97.101', description: 'Non-pressure chronic ulcer of unspecified thigh limited to breakdown of skin' },
        { code: 'L97.201', description: 'Non-pressure chronic ulcer of unspecified calf limited to breakdown of skin' }
      ]
    },
    woundArea: '10',
    errors: {}
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should render all wound information fields', () => {
    render(<Step4ClinicalBilling {...defaultProps} />);
    
    expect(screen.getByText('Wound Information')).toBeInTheDocument();
    expect(screen.getByText('Wound Location & CPT Code')).toBeInTheDocument();
    expect(screen.getByText('Length (cm)')).toBeInTheDocument();
    expect(screen.getByText('Width (cm)')).toBeInTheDocument();
  });

  it('should render diagnosis code selector', () => {
    render(<Step4ClinicalBilling {...defaultProps} />);
    
    expect(screen.getByTestId('diagnosis-code-selector')).toBeInTheDocument();
  });

  it('should render wound duration fields', () => {
    render(<Step4ClinicalBilling {...defaultProps} />);
    
    expect(screen.getByText('Wound Duration')).toBeInTheDocument();
    expect(screen.getByText('Days')).toBeInTheDocument();
    expect(screen.getByText('Weeks')).toBeInTheDocument();
    expect(screen.getByText('Months')).toBeInTheDocument();
    expect(screen.getByText('Years')).toBeInTheDocument();
  });

  it('should validate at least one duration field is filled', () => {
    const props = {
      ...defaultProps,
      errors: {
        wound_duration: 'At least one duration field is required'
      }
    };
    
    render(<Step4ClinicalBilling {...props} />);
    
    expect(screen.getByText('At least one duration field is required')).toBeInTheDocument();
  });

  it('should show prior application product field when prior applications > 0', () => {
    const props = {
      ...defaultProps,
      formData: {
        prior_applications: '2'
      }
    };
    
    render(<Step4ClinicalBilling {...props} />);
    
    expect(screen.getByText('Which product was previously used?')).toBeInTheDocument();
    expect(screen.getByLabelText('Applied within the last 12 months')).toBeInTheDocument();
  });

  it('should show hospice consent fields when hospice is selected', () => {
    render(<Step4ClinicalBilling {...defaultProps} />);
    
    const hospiceCheckbox = screen.getByLabelText('Patient is in Hospice');
    fireEvent.click(hospiceCheckbox);
    
    expect(screen.getByLabelText('Family consent obtained')).toBeInTheDocument();
    expect(screen.getByLabelText('Clinically necessary per hospice guidelines')).toBeInTheDocument();
  });

  it('should display correct title for facility section', () => {
    render(<Step4ClinicalBilling {...defaultProps} />);
    
    expect(screen.getByText('Facility Information')).toBeInTheDocument();
    expect(screen.queryByText('Facility & Billing Status')).not.toBeInTheDocument();
  });

  it('should show other wound type specification field when other is selected', () => {
    const props = {
      ...defaultProps,
      formData: {
        wound_type: 'other'
      }
    };
    
    render(<Step4ClinicalBilling {...props} />);
    
    expect(screen.getByText('Specify Other Wound Type')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('Please specify...')).toBeInTheDocument();
  });

  it('should show CPT code selection after wound location is selected', () => {
    render(<Step4ClinicalBilling {...defaultProps} />);
    
    const locationSelect = screen.getByRole('combobox', { name: /wound location/i });
    fireEvent.change(locationSelect, { target: { value: 'trunk_arms_legs_small' } });
    
    expect(screen.getByText('Application CPT Codes (Based on Location)')).toBeInTheDocument();
  });

  it('should calculate wound area correctly', () => {
    const props = {
      ...defaultProps,
      formData: {
        wound_size_length: '5',
        wound_size_width: '4'
      },
      woundArea: '20'
    };
    
    render(<Step4ClinicalBilling {...props} />);
    
    expect(screen.getByText('20 sq cm')).toBeInTheDocument();
  });

  it('should show SNF Medicare authorization when SNF place of service is selected', () => {
    const props = {
      ...defaultProps,
      formData: {
        place_of_service: '31'
      }
    };
    
    render(<Step4ClinicalBilling {...props} />);
    
    expect(screen.getByText('Medicare Part B Authorization Required')).toBeInTheDocument();
    expect(screen.getByText(/Skilled Nursing Facility requires special Medicare authorization/)).toBeInTheDocument();
  });

  it('should show global period fields when global period status is checked', () => {
    render(<Step4ClinicalBilling {...defaultProps} />);
    
    const globalPeriodCheckbox = screen.getByLabelText('Patient under post-op global period');
    fireEvent.click(globalPeriodCheckbox);
    
    expect(screen.getByLabelText('Previous Surgery CPT')).toBeInTheDocument();
    expect(screen.getByLabelText('Surgery Date')).toBeInTheDocument();
  });

  it('should update form data when duration fields are changed', () => {
    render(<Step4ClinicalBilling {...defaultProps} />);
    
    const daysInput = screen.getByPlaceholderText('0', { exact: false });
    fireEvent.change(daysInput, { target: { value: '7' } });
    
    expect(mockUpdateFormData).toHaveBeenCalledWith({ wound_duration_days: '7' });
  });

  it('should update form data when diagnosis is changed', () => {
    render(<Step4ClinicalBilling {...defaultProps} />);
    
    const woundTypeInput = screen.getByTestId('wound-type');
    fireEvent.change(woundTypeInput, { target: { value: 'diabetic_foot_ulcer' } });
    
    expect(mockUpdateFormData).toHaveBeenCalledWith(
      expect.objectContaining({
        wound_type: 'diabetic_foot_ulcer'
      })
    );
  });

  it('should display validation errors', () => {
    const props = {
      ...defaultProps,
      errors: {
        wound_location: 'Wound location is required',
        wound_size: 'Wound size is required',
        wound_duration: 'At least one duration field is required'
      }
    };
    
    render(<Step4ClinicalBilling {...props} />);
    
    expect(screen.getByText('Wound location is required')).toBeInTheDocument();
    expect(screen.getByText('Wound size is required')).toBeInTheDocument();
    expect(screen.getByText('At least one duration field is required')).toBeInTheDocument();
  });
});