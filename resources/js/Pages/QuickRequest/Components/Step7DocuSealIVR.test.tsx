import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import Step7DocuSealIVR from './Step7DocuSealIVR';

// Mock dependencies
vi.mock('@/contexts/ThemeContext', () => ({
  useTheme: () => ({ theme: 'dark' })
}));

vi.mock('@/Components/DocuSeal/DocuSealIVRForm', () => ({
  default: ({ formData, onComplete, onError }: any) => (
    <div data-testid="docuseal-ivr-form">
      <button onClick={() => onComplete('test-submission-id')}>Complete</button>
      <button onClick={() => onError('Test error')}>Error</button>
      <div>{JSON.stringify(formData, null, 2)}</div>
    </div>
  )
}));

vi.mock('../manufacturerFields', () => ({
  getManufacturerByProduct: (productName: string) => {
    if (productName === 'Amnio AMP') {
      return {
        name: 'MedLife',
        signatureRequired: true,
        docusealTemplateId: '1234974',
        fields: []
      };
    }
    if (productName === 'No IVR Product') {
      return {
        name: 'TestManufacturer',
        signatureRequired: false,
        fields: []
      };
    }
    return null;
  }
}));

describe('Step7DocuSealIVR', () => {
  const mockUpdateFormData = vi.fn();
  
  const defaultProps = {
    formData: {
      patient_first_name: 'John',
      patient_last_name: 'Doe',
      patient_dob: '1980-01-01',
      provider_id: 1,
      selected_products: [{
        product_id: 1,
        quantity: 2,
        size: '10x10'
      }],
      wound_type: 'diabetic_foot_ulcer',
      wound_size_length: '5',
      wound_size_width: '4',
      wound_size_depth: '1',
      primary_diagnosis_code: 'E11.621',
      secondary_diagnosis_code: 'L97.201',
      wound_duration_days: '7',
      wound_duration_weeks: '2',
      prior_applications: '1',
      prior_application_product: 'Previous Product',
      prior_application_within_12_months: true,
      hospice_status: true,
      hospice_family_consent: true,
      hospice_clinically_necessary: true,
      episode_id: 'test-episode-123'
    },
    updateFormData: mockUpdateFormData,
    products: [
      {
        id: 1,
        code: 'AMP001',
        name: 'Amnio AMP',
        manufacturer: 'MedLife',
        manufacturer_id: 10
      },
      {
        id: 2,
        code: 'NIV001',
        name: 'No IVR Product',
        manufacturer: 'TestManufacturer',
        manufacturer_id: 20
      }
    ],
    providers: [
      {
        id: 1,
        name: 'Dr. Test Provider',
        credentials: 'MD',
        npi: '1234567890'
      }
    ],
    facilities: [
      {
        id: 1,
        name: 'Test Facility',
        address: '123 Main St'
      }
    ],
    errors: {}
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should show no product selected message when no product is selected', () => {
    const props = {
      ...defaultProps,
      formData: {
        ...defaultProps.formData,
        selected_products: []
      }
    };
    
    render(<Step7DocuSealIVR {...props} />);
    
    expect(screen.getByText('Please select a product first')).toBeInTheDocument();
  });

  it('should show no IVR required message for products without IVR', () => {
    const props = {
      ...defaultProps,
      formData: {
        ...defaultProps.formData,
        selected_products: [{
          product_id: 2,
          quantity: 1,
          size: '5x5'
        }]
      }
    };
    
    render(<Step7DocuSealIVR {...props} />);
    
    expect(screen.getByText('No IVR Required')).toBeInTheDocument();
    expect(screen.getByText('No IVR Product does not require an IVR form submission.')).toBeInTheDocument();
  });

  it('should set NO_IVR_REQUIRED for products without IVR requirement', async () => {
    const props = {
      ...defaultProps,
      formData: {
        ...defaultProps.formData,
        selected_products: [{
          product_id: 2,
          quantity: 1,
          size: '5x5'
        }]
      }
    };
    
    render(<Step7DocuSealIVR {...props} />);
    
    await waitFor(() => {
      expect(mockUpdateFormData).toHaveBeenCalledWith({ 
        docuseal_submission_id: 'NO_IVR_REQUIRED' 
      });
    });
  });

  it('should prepare form data with new fields for DocuSeal', () => {
    render(<Step7DocuSealIVR {...defaultProps} />);
    
    const formDataJson = screen.getByTestId('docuseal-ivr-form').textContent;
    const parsedData = JSON.parse(formDataJson!);
    
    // Check new fields are included
    expect(parsedData.wound_type).toBe('diabetic_foot_ulcer');
    expect(parsedData.wound_duration).toBe('2 weeks, 7 days');
    expect(parsedData.wound_duration_days).toBe('7');
    expect(parsedData.wound_duration_weeks).toBe('2');
    expect(parsedData.wound_duration_months).toBe('');
    expect(parsedData.wound_duration_years).toBe('');
    
    // Check diagnosis codes
    expect(parsedData.diagnosis_codes_display).toBe('Primary: E11.621, Secondary: L97.201');
    expect(parsedData.primary_diagnosis_code).toBe('E11.621');
    expect(parsedData.secondary_diagnosis_code).toBe('L97.201');
    
    // Check prior application fields
    expect(parsedData.prior_applications).toBe('1');
    expect(parsedData.prior_application_product).toBe('Previous Product');
    expect(parsedData.prior_application_within_12_months).toBe('Yes');
    
    // Check hospice fields
    expect(parsedData.hospice_status).toBe('Yes');
    expect(parsedData.hospice_family_consent).toBe('Yes');
    expect(parsedData.hospice_clinically_necessary).toBe('Yes');
  });

  it('should format wound duration correctly with multiple values', () => {
    const props = {
      ...defaultProps,
      formData: {
        ...defaultProps.formData,
        wound_duration_days: '15',
        wound_duration_weeks: '0',
        wound_duration_months: '3',
        wound_duration_years: '1'
      }
    };
    
    render(<Step7DocuSealIVR {...props} />);
    
    const formDataJson = screen.getByTestId('docuseal-ivr-form').textContent;
    const parsedData = JSON.parse(formDataJson!);
    
    expect(parsedData.wound_duration).toBe('1 years, 3 months, 15 days');
  });

  it('should handle single diagnosis code correctly', () => {
    const props = {
      ...defaultProps,
      formData: {
        ...defaultProps.formData,
        wound_type: 'pressure_ulcer',
        diagnosis_code: 'L89.001',
        primary_diagnosis_code: '',
        secondary_diagnosis_code: ''
      }
    };
    
    render(<Step7DocuSealIVR {...props} />);
    
    const formDataJson = screen.getByTestId('docuseal-ivr-form').textContent;
    const parsedData = JSON.parse(formDataJson!);
    
    expect(parsedData.diagnosis_codes_display).toBe('L89.001');
    expect(parsedData.diagnosis_code).toBe('L89.001');
  });

  it('should convert boolean values to Yes/No for display', () => {
    const props = {
      ...defaultProps,
      formData: {
        ...defaultProps.formData,
        manufacturer_fields: {
          physician_attestation: true,
          not_used_previously: false,
          temperature_controlled: true
        }
      }
    };
    
    render(<Step7DocuSealIVR {...props} />);
    
    const formDataJson = screen.getByTestId('docuseal-ivr-form').textContent;
    const parsedData = JSON.parse(formDataJson!);
    
    expect(parsedData.physician_attestation).toBe('Yes');
    expect(parsedData.not_used_previously).toBe('No');
    expect(parsedData.temperature_controlled).toBe('Yes');
  });

  it('should show order summary with correct information', () => {
    render(<Step7DocuSealIVR {...defaultProps} />);
    
    expect(screen.getByText('Order Summary')).toBeInTheDocument();
    expect(screen.getByText('John Doe')).toBeInTheDocument();
    expect(screen.getByText('Amnio AMP (AMP001)')).toBeInTheDocument();
    expect(screen.getByText('Dr. Test Provider')).toBeInTheDocument();
  });

  it('should handle completion successfully', () => {
    render(<Step7DocuSealIVR {...defaultProps} />);
    
    const completeButton = screen.getByText('Complete');
    completeButton.click();
    
    expect(screen.getByText('IVR Form Completed Successfully')).toBeInTheDocument();
    expect(screen.getByText('Submission ID:')).toBeInTheDocument();
    expect(screen.getByText('test-submission-id')).toBeInTheDocument();
    expect(mockUpdateFormData).toHaveBeenCalledWith({ 
      docuseal_submission_id: 'test-submission-id' 
    });
  });

  it('should handle errors correctly', () => {
    render(<Step7DocuSealIVR {...defaultProps} />);
    
    const errorButton = screen.getByText('Error');
    errorButton.click();
    
    expect(screen.getByText('Error Loading IVR Form')).toBeInTheDocument();
    expect(screen.getByText('Test error')).toBeInTheDocument();
    expect(screen.getByText('Try Again')).toBeInTheDocument();
  });

  it('should display validation errors', () => {
    const props = {
      ...defaultProps,
      errors: {
        docuseal: 'IVR completion is required'
      }
    };
    
    render(<Step7DocuSealIVR {...props} />);
    
    expect(screen.getByText('IVR completion is required')).toBeInTheDocument();
  });
});