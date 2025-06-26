import React from 'react';
import { render, fireEvent, screen } from '@testing-library/react';
import Step2PatientInsurance from '../Step2PatientInsurance';

const baseFormData = {
  patient_is_subscriber: true,
  primary_insurance_name: '',
  primary_member_id: '',
  primary_plan_type: '',
  has_secondary_insurance: false,
  secondary_insurance_name: '',
  secondary_member_id: '',
  secondary_plan_type: '',
};

describe('Step2PatientInsurance', () => {
  it('renders primary plan type dropdown and updates value', () => {
    const updateFormData = jest.fn();
    render(
      <Step2PatientInsurance
        formData={{ ...baseFormData, primary_plan_type: 'hmo' }}
        updateFormData={updateFormData}
        errors={{}}
      />
    );
    const planTypeSelect = screen.getByLabelText(/Plan Type/i);
    expect(planTypeSelect).toBeInTheDocument();
    expect(planTypeSelect).toHaveValue('hmo');
    fireEvent.change(planTypeSelect, { target: { value: 'ppo' } });
    expect(updateFormData).toHaveBeenCalledWith({ primary_plan_type: 'ppo' });
  });

  it('shows and updates secondary plan type when secondary insurance is enabled', () => {
    const updateFormData = jest.fn();
    render(
      <Step2PatientInsurance
        formData={{ ...baseFormData, has_secondary_insurance: true, secondary_plan_type: 'pos' }}
        updateFormData={updateFormData}
        errors={{}}
      />
    );
    // Secondary plan type select should be present
    const secondaryPlanType = screen.getAllByLabelText(/Plan Type/i)[1];
    expect(secondaryPlanType).toBeInTheDocument();
    expect(secondaryPlanType).toHaveValue('pos');
    fireEvent.change(secondaryPlanType, { target: { value: 'ffs' } });
    expect(updateFormData).toHaveBeenCalledWith({ secondary_plan_type: 'ffs' });
  });

  it('primary plan type is required and shows error', () => {
    render(
      <Step2PatientInsurance
        formData={{ ...baseFormData, primary_plan_type: '' }}
        updateFormData={jest.fn()}
        errors={{ primary_plan_type: 'Plan type is required' }}
      />
    );
    expect(screen.getByText('Plan type is required')).toBeInTheDocument();
  });

  it('secondary plan type can be empty and not required if not shown', () => {
    render(
      <Step2PatientInsurance
        formData={baseFormData}
        updateFormData={jest.fn()}
        errors={{}}
      />
    );
    // Only one Plan Type select
    expect(screen.getAllByLabelText(/Plan Type/i)).toHaveLength(1);
  });
});
