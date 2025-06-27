import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
import DiagnosisCodeSelector from './DiagnosisCodeSelector';

// Mock dependencies
vi.mock('@/contexts/ThemeContext', () => ({
  useTheme: () => ({ theme: 'dark' })
}));

describe('DiagnosisCodeSelector', () => {
  const mockOnChange = vi.fn();
  
  const defaultProps = {
    value: {},
    onChange: mockOnChange,
    errors: {},
    diagnosisCodes: {
      yellow: [
        { code: 'E11.621', description: 'Type 2 diabetes mellitus with foot ulcer' },
        { code: 'E11.622', description: 'Type 2 diabetes mellitus with other skin ulcer' },
        { code: 'E10.621', description: 'Type 1 diabetes mellitus with foot ulcer' }
      ],
      orange: [
        { code: 'L97.101', description: 'Non-pressure chronic ulcer of unspecified thigh limited to breakdown of skin' },
        { code: 'L97.201', description: 'Non-pressure chronic ulcer of unspecified calf limited to breakdown of skin' },
        { code: 'L97.301', description: 'Non-pressure chronic ulcer of unspecified ankle limited to breakdown of skin' }
      ]
    }
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should render wound type selection grid', () => {
    render(<DiagnosisCodeSelector {...defaultProps} />);
    
    expect(screen.getByText('Select Wound Type')).toBeInTheDocument();
    expect(screen.getByLabelText('Diabetic Foot Ulcer')).toBeInTheDocument();
    expect(screen.getByLabelText('Venous Leg Ulcer')).toBeInTheDocument();
    expect(screen.getByLabelText('Pressure Ulcer')).toBeInTheDocument();
    expect(screen.getByLabelText('Other')).toBeInTheDocument();
  });

  it('should show dual code badge for wound types requiring dual coding', () => {
    render(<DiagnosisCodeSelector {...defaultProps} />);
    
    const diabeticFootUlcer = screen.getByLabelText('Diabetic Foot Ulcer').closest('div');
    expect(within(diabeticFootUlcer!).getByText('Dual Code')).toBeInTheDocument();
    
    const venousLegUlcer = screen.getByLabelText('Venous Leg Ulcer').closest('div');
    expect(within(venousLegUlcer!).getByText('Dual Code')).toBeInTheDocument();
  });

  it('should show diagnosis code fields after wound type selection', () => {
    render(<DiagnosisCodeSelector {...defaultProps} />);
    
    fireEvent.click(screen.getByLabelText('Pressure Ulcer'));
    
    expect(screen.getByText('Pressure Ulcer with Stage (L89-codes)')).toBeInTheDocument();
    expect(screen.getByText('Search for a diagnosis code...')).toBeInTheDocument();
  });

  it('should show dual code fields for diabetic foot ulcer', () => {
    render(<DiagnosisCodeSelector {...defaultProps} />);
    
    fireEvent.click(screen.getByLabelText('Diabetic Foot Ulcer'));
    
    expect(screen.getByText('Diabetes Diagnosis (E-codes)')).toBeInTheDocument();
    expect(screen.getByText('Chronic Ulcer Location (L97-codes)')).toBeInTheDocument();
    expect(screen.getAllByText('Search for a diagnosis code...').length).toBe(2);
  });

  it('should show alert for dual coding requirements', () => {
    render(<DiagnosisCodeSelector {...defaultProps} />);
    
    fireEvent.click(screen.getByLabelText('Diabetic Foot Ulcer'));
    
    expect(screen.getByText(/Diabetic Foot Ulcer requires both a primary diagnosis code and a secondary chronic ulcer code/)).toBeInTheDocument();
  });

  it('should filter diagnosis codes in searchable dropdown', async () => {
    render(<DiagnosisCodeSelector {...defaultProps} />);
    
    fireEvent.click(screen.getByLabelText('Diabetic Foot Ulcer'));
    
    // Click on the first dropdown to open it
    const dropdowns = screen.getAllByText('Search for a diagnosis code...');
    fireEvent.click(dropdowns[0]);
    
    // Type in search
    const searchInput = screen.getByPlaceholderText('Search by code or description...');
    fireEvent.change(searchInput, { target: { value: 'E11' } });
    
    // Should show E11 codes but not E10
    await waitFor(() => {
      expect(screen.getByText('E11.621')).toBeInTheDocument();
      expect(screen.getByText('E11.622')).toBeInTheDocument();
      expect(screen.queryByText('E10.621')).not.toBeInTheDocument();
    });
  });

  it('should update values when codes are selected', () => {
    render(<DiagnosisCodeSelector {...defaultProps} />);
    
    fireEvent.click(screen.getByLabelText('Diabetic Foot Ulcer'));
    
    // Click first dropdown
    const dropdowns = screen.getAllByText('Search for a diagnosis code...');
    fireEvent.click(dropdowns[0]);
    
    // Select a code
    fireEvent.click(screen.getByText('E11.621'));
    
    expect(mockOnChange).toHaveBeenCalledWith({
      wound_type: 'diabetic_foot_ulcer',
      primary_diagnosis_code: 'E11.621',
      secondary_diagnosis_code: '',
      diagnosis_code: ''
    });
  });

  it('should properly set diagnosis_code for single-code wound types', () => {
    render(<DiagnosisCodeSelector {...defaultProps} />);
    
    fireEvent.click(screen.getByLabelText('Pressure Ulcer'));
    
    // Wait for codes to load
    waitFor(() => {
      expect(screen.getByText('Search for a diagnosis code...')).toBeInTheDocument();
    });
    
    // Click dropdown
    fireEvent.click(screen.getByText('Search for a diagnosis code...'));
    
    // Select a code
    fireEvent.click(screen.getByText('L89.001'));
    
    expect(mockOnChange).toHaveBeenCalledWith({
      wound_type: 'pressure_ulcer',
      diagnosis_code: 'L89.001',
      primary_diagnosis_code: '',
      secondary_diagnosis_code: ''
    });
  });

  it('should show selection complete when all required codes are selected', () => {
    const props = {
      ...defaultProps,
      value: {
        wound_type: 'diabetic_foot_ulcer',
        primary_diagnosis_code: 'E11.621',
        secondary_diagnosis_code: 'L97.201'
      }
    };
    
    render(<DiagnosisCodeSelector {...props} />);
    
    expect(screen.getByText('Selection Complete')).toBeInTheDocument();
    expect(screen.getByText('Wound Type:')).toBeInTheDocument();
    expect(screen.getByText('Primary Code:')).toBeInTheDocument();
    expect(screen.getByText('Secondary Code:')).toBeInTheDocument();
  });

  it('should display error messages', () => {
    const props = {
      ...defaultProps,
      errors: {
        wound_type: 'Wound type is required',
        diagnosis: 'Diagnosis code is required'
      }
    };
    
    render(<DiagnosisCodeSelector {...props} />);
    
    expect(screen.getByText('Wound type is required')).toBeInTheDocument();
  });

  it('should clear codes when wound type changes', () => {
    const props = {
      ...defaultProps,
      value: {
        wound_type: 'diabetic_foot_ulcer',
        primary_diagnosis_code: 'E11.621',
        secondary_diagnosis_code: 'L97.201'
      }
    };
    
    render(<DiagnosisCodeSelector {...props} />);
    
    fireEvent.click(screen.getByLabelText('Pressure Ulcer'));
    
    expect(mockOnChange).toHaveBeenCalledWith({
      wound_type: 'pressure_ulcer',
      primary_diagnosis_code: '',
      secondary_diagnosis_code: '',
      diagnosis_code: ''
    });
  });

  it('should handle single code wounds correctly', () => {
    render(<DiagnosisCodeSelector {...defaultProps} />);
    
    fireEvent.click(screen.getByLabelText('Pressure Ulcer'));
    
    // Should only show one dropdown
    expect(screen.getAllByText('Search for a diagnosis code...').length).toBe(1);
    
    // Click dropdown
    fireEvent.click(screen.getByText('Search for a diagnosis code...'));
    
    // Select a code
    fireEvent.click(screen.getByText('L89.001'));
    
    expect(mockOnChange).toHaveBeenCalledWith({
      wound_type: 'pressure_ulcer',
      diagnosis_code: 'L89.001',
      primary_diagnosis_code: '',
      secondary_diagnosis_code: ''
    });
  });

  it('should show max results message when search returns many results', async () => {
    // Create many diagnosis codes
    const manyYellowCodes = Array.from({ length: 60 }, (_, i) => ({
      code: `E11.${i}`,
      description: `Diabetes code ${i}`
    }));
    
    const props = {
      ...defaultProps,
      diagnosisCodes: {
        yellow: manyYellowCodes,
        orange: []
      }
    };
    
    render(<DiagnosisCodeSelector {...props} />);
    
    fireEvent.click(screen.getByLabelText('Diabetic Foot Ulcer'));
    fireEvent.click(screen.getAllByText('Search for a diagnosis code...')[0]);
    
    await waitFor(() => {
      expect(screen.getByText('Showing first 50 results. Type to narrow search.')).toBeInTheDocument();
    });
  });

  it('should show all available codes for "Other" wound type', async () => {
    render(<DiagnosisCodeSelector {...defaultProps} />);
    
    fireEvent.click(screen.getByLabelText('Other'));
    
    await waitFor(() => {
      expect(screen.getByText('Search for a diagnosis code...')).toBeInTheDocument();
    });
    
    // Click dropdown
    fireEvent.click(screen.getByText('Search for a diagnosis code...'));
    
    // Should show codes from both yellow and orange categories
    await waitFor(() => {
      // Check for codes from yellow category
      expect(screen.getByText('E11.621')).toBeInTheDocument();
      // Check for codes from orange category  
      expect(screen.getByText('L97.101')).toBeInTheDocument();
    });
  });

  it('should show diagnosis codes for all wound types', async () => {
    const woundTypesToTest = [
      'Surgical Wound',
      'Traumatic Wound', 
      'Arterial Ulcer',
      'Chronic Ulcer'
    ];
    
    for (const woundType of woundTypesToTest) {
      const { unmount } = render(<DiagnosisCodeSelector {...defaultProps} />);
      
      fireEvent.click(screen.getByLabelText(woundType));
      
      await waitFor(() => {
        expect(screen.getByText('Search for a diagnosis code...')).toBeInTheDocument();
      });
      
      // Click dropdown
      fireEvent.click(screen.getByText('Search for a diagnosis code...'));
      
      // Should show at least one diagnosis code option
      await waitFor(() => {
        const options = screen.getAllByText(/^[A-Z]\d{2}/, { selector: '.font-mono' });
        expect(options.length).toBeGreaterThan(0);
      });
      
      unmount();
    }
  });
});