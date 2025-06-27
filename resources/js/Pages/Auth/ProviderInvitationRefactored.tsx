import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/Components/Button';
import { Card, CardContent } from '@/Components/ui/card';
import { useProviderOnboarding } from '@/Hooks/useProviderOnboarding';
import { validateStep } from '@/utils/providerValidation';

// Import all step components
import ReviewStep from '@/Components/Onboarding/Steps/ReviewStep';
import PracticeTypeStep from '@/Components/Onboarding/Steps/PracticeTypeStep';
import PersonalInfoStep from '@/Components/Onboarding/Steps/PersonalInfoStep';
import OrganizationStep from '@/Components/Onboarding/Steps/OrganizationStep';
import FacilityInfoStep from '@/Components/Onboarding/Steps/FacilityInfoStep';
import FacilitySelectionStep from '@/Components/Onboarding/Steps/FacilitySelectionStep';
import CredentialsStep from '@/Components/Onboarding/Steps/CredentialsStep';
import BillingStep from '@/Components/Onboarding/Steps/BillingStep';
import CompleteStep from '@/Components/Onboarding/Steps/CompleteStep';

import type { 
  ProviderInvitationData, 
  FacilityData, 
  StateOption,
  ProviderRegistrationData 
} from '@/types/provider';

interface ProviderInvitationProps {
  invitation: ProviderInvitationData;
  token: string;
  facilities: FacilityData[];
  states: StateOption[];
}

// Step titles for progress display
const STEP_TITLES: Record<string, string> = {
  'review': 'Review Invitation',
  'practice-type': 'Practice Type',
  'personal': 'Personal Information',
  'organization': 'Organization Details',
  'facility': 'Facility Information',
  'facility-selection': 'Select Facility',
  'credentials': 'Professional Credentials',
  'billing': 'Billing Information',
  'complete': 'Registration Complete',
};

export default function ProviderInvitation({ 
  invitation, 
  token, 
  facilities, 
  states 
}: ProviderInvitationProps) {
  const { post, processing } = useForm();
  const [showComplete, setShowComplete] = useState(false);

  const handleComplete = (data: ProviderRegistrationData) => {
    post(`/auth/provider-invitation/${token}/accept`, {
      data,
      onSuccess: () => {
        setShowComplete(true);
      }
    });
  };

  const {
    currentStep,
    data,
    errors,
    progress,
    goToNext,
    goToPrevious,
    updateData,
    setValidationErrors,
    isFirstStep,
    isLastStep,
  } = useProviderOnboarding({
    initialData: {
      email: invitation.invited_email,
      practice_type: 'solo_practitioner',
      // Pre-populate organization name for existing organization flow
      organization_name: invitation.organization_name,
    },
    onComplete: handleComplete,
  });

  const handleNext = () => {
    // Skip validation for review step
    if (currentStep === 'review') {
      goToNext();
      return;
    }

    // Validate current step
    const stepErrors = validateStep(currentStep, data);
    
    if (Object.keys(stepErrors).length > 0) {
      setValidationErrors(stepErrors);
      return;
    }

    goToNext();
  };

  const renderCurrentStep = () => {
    // Show completion screen if registration is done
    if (showComplete) {
      return <CompleteStep />;
    }

    switch (currentStep) {
      case 'review':
        return (
          <ReviewStep
            invitation={invitation}
            onAccept={handleNext}
            onDecline={() => window.location.href = '/'}
          />
        );

      case 'practice-type':
        return (
          <PracticeTypeStep
            data={data}
            onChange={updateData}
          />
        );

      case 'personal':
        return (
          <PersonalInfoStep
            data={data}
            errors={errors}
            onChange={updateData}
          />
        );

      case 'organization':
        return (
          <OrganizationStep
            data={data}
            errors={errors}
            onChange={updateData}
          />
        );

      case 'facility':
        return (
          <FacilityInfoStep
            data={data}
            errors={errors}
            states={states}
            onChange={updateData}
          />
        );

      case 'facility-selection':
        return (
          <FacilitySelectionStep
            data={data}
            errors={errors}
            facilities={facilities}
            invitation={invitation}
            onChange={updateData}
          />
        );

      case 'credentials':
        return (
          <CredentialsStep
            data={data}
            errors={errors}
            states={states}
            onChange={updateData}
          />
        );

      case 'billing':
        return (
          <BillingStep
            data={data}
            errors={errors}
            states={states}
            onChange={updateData}
          />
        );

      default:
        return <div>Step not found: {currentStep}</div>;
    }
  };

  // Don't show navigation for complete step
  const showNavigation = !showComplete && currentStep !== 'review';

  return (
    <>
      <Head title="Provider Invitation" />
      <div className="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div className="sm:mx-auto sm:w-full sm:max-w-2xl">
          <Card>
            <CardContent className="p-8">
              {/* Progress bar - only show if not on review or complete */}
              {!showComplete && currentStep !== 'review' && (
                <div className="mb-8">
                  <div className="bg-gray-200 rounded-full h-2">
                    <div 
                      className="bg-blue-600 h-2 rounded-full transition-all duration-300"
                      style={{ width: `${progress}%` }}
                    />
                  </div>
                  <p className="text-sm text-gray-600 mt-2 text-center">
                    {STEP_TITLES[currentStep]} - {progress}% complete
                  </p>
                </div>
              )}

              {/* Current step content */}
              {renderCurrentStep()}

              {/* Navigation buttons */}
              {showNavigation && (
                <div className="flex justify-between mt-8">
                  <Button 
                    variant="secondary" 
                    onClick={goToPrevious}
                    disabled={isFirstStep}
                  >
                    Back
                  </Button>
                  <Button 
                    onClick={handleNext}
                    disabled={processing}
                  >
                    {processing ? 'Processing...' : (isLastStep ? 'Complete Registration' : 'Continue')}
                  </Button>
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </>
  );
}