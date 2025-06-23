import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { createInertiaApp } from '@inertiajs/react';
import CreateNew from './CreateNew';
import axios from 'axios';

// Mock dependencies
vi.mock('axios');
vi.mock('@inertiajs/react', () => ({
  Head: ({ title }: { title: string }) => <title>{title}</title>,
  router: {
    post: vi.fn()
  }
}));

// Mock theme context
vi.mock('@/contexts/ThemeContext', () => ({
  useTheme: () => ({ theme: 'dark' })
}));

// Mock components
vi.mock('./Components/Step2PatientInsurance', () => ({
  default: ({ formData, updateFormData }: any) => (
    <div data-testid="step2-patient-insurance">
      <input
        data-testid="patient-first-name"
        value={formData.patient_first_name}
        onChange={(e) => updateFormData({ patient_first_name: e.target.value })}
      />
      <input
        data-testid="patient-last-name"
        value={formData.patient_last_name}
        onChange={(e) => updateFormData({ patient_last_name: e.target.value })}
      />
    </div>
  )
}));

vi.mock('./Components/Step5ProductSelection', () => ({
  default: ({ formData, updateFormData }: any) => (
    <div data-testid="step5-product-selection">
      <button
        data-testid="select-product"
        onClick={() => updateFormData({
          selected_products: [{
            product_id: 1,
            quantity: 1,
            size: '10x10',
            product: { id: 1, name: 'Test Product', manufacturer_id: 10 }
          }]
        })}
      >
        Select Product
      </button>
    </div>
  )
}));

describe('QuickRequestCreateNew', () => {
  const mockProps = {
    facilities: [
      { id: 1, name: 'Test Facility', address: '123 Main St' }
    ],
    providers: [
      { id: 1, name: 'Dr. Test', npi: '1234567890', fhir_practitioner_id: 'test-fhir-id' }
    ],
    products: [
      {
        id: 1,
        code: 'TEST001',
        name: 'Test Product',
        manufacturer: 'Test Manufacturer',
        manufacturer_id: 10,
        available_sizes: ['10x10', '15x15'],
        price_per_sq_cm: 5
      }
    ],
    currentUser: {
      id: 1,
      name: 'Test User',
      role: 'provider',
      organization: {
        id: 1,
        name: 'Test Organization',
        fhir_organization_id: 'org-fhir-id'
      }
    }
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should render initial step correctly', () => {
    render(<CreateNew {...mockProps} />);
    
    expect(screen.getByText('Create New Order')).toBeInTheDocument();
    expect(screen.getByText('Patient & Insurance')).toBeInTheDocument();
    expect(screen.getByTestId('step2-patient-insurance')).toBeInTheDocument();
  });

  it('should validate required fields before proceeding', async () => {
    render(<CreateNew {...mockProps} />);
    
    const nextButton = screen.getByText('Next');
    fireEvent.click(nextButton);
    
    await waitFor(() => {
      expect(screen.getByText(/First name is required/)).toBeInTheDocument();
    });
  });

  it('should properly format selected_products data for submission', async () => {
    const mockAxiosPost = vi.mocked(axios.post);
    mockAxiosPost.mockResolvedValueOnce({ data: { id: 'patient-123' } }); // Patient creation
    mockAxiosPost.mockResolvedValueOnce({ data: { id: 'episode-123' } }); // EpisodeOfCare
    mockAxiosPost.mockResolvedValueOnce({ data: { episode_id: 'local-episode-123' } }); // Local episode
    
    render(<CreateNew {...mockProps} />);
    
    // Fill out patient info
    fireEvent.change(screen.getByTestId('patient-first-name'), {
      target: { value: 'John' }
    });
    fireEvent.change(screen.getByTestId('patient-last-name'), {
      target: { value: 'Doe' }
    });
    
    // Navigate to product selection
    // ... (navigate through steps)
    
    // Select a product
    fireEvent.click(screen.getByTestId('select-product'));
    
    // Verify the formData structure
    await waitFor(() => {
      const formData = new FormData();
      expect(formData.get('selected_products[0][product_id]')).toBe('1');
      expect(formData.get('selected_products[0][quantity]')).toBe('1');
      expect(formData.get('selected_products[0][size]')).toBe('10x10');
    });
  });

  it('should create episode after product selection', async () => {
    const mockAxiosPost = vi.mocked(axios.post);
    mockAxiosPost
      .mockResolvedValueOnce({ data: { id: 'patient-123' } })
      .mockResolvedValueOnce({ data: { id: 'episode-of-care-123' } })
      .mockResolvedValueOnce({ data: { episode_id: 'ivr-episode-123' } });

    render(<CreateNew {...mockProps} />);
    
    // Navigate through steps and select product...
    
    await waitFor(() => {
      expect(mockAxiosPost).toHaveBeenCalledWith(
        '/api/quick-request/create-episode',
        expect.objectContaining({
          patient_fhir_id: 'patient-123',
          manufacturer_id: 10
        }),
        expect.any(Object)
      );
    });
  });

  it('should set NO_IVR_REQUIRED when manufacturer does not require IVR', async () => {
    // Test that when navigating to IVR step with a product that doesn't require IVR,
    // the docuseal_submission_id is set to 'NO_IVR_REQUIRED'
  });

  it('should properly serialize manufacturer_fields as array format', async () => {
    // Test that manufacturer_fields is sent in the correct array format
    // manufacturer_fields[field_name] = value
  });
});