import React from 'react';
import { Button } from '@/Components/Button';
import { CheckCircle2 } from 'lucide-react';

interface CompleteStepProps {
  onContinue?: () => void;
}

export default function CompleteStep({ onContinue }: CompleteStepProps) {
  const handleContinue = () => {
    if (onContinue) {
      onContinue();
    } else {
      window.location.href = '/login';
    }
  };

  return (
    <div className="text-center space-y-6">
      <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
        <CheckCircle2 className="h-8 w-8 text-green-600" />
      </div>
      
      <h1 className="text-2xl font-bold text-gray-900">Account Created Successfully!</h1>
      
      <p className="text-gray-600 max-w-md mx-auto">
        Your comprehensive practice profile has been created and is pending verification.
        You'll receive email confirmation once your credentials are approved.
      </p>

      <div className="bg-blue-50 p-6 rounded-lg max-w-lg mx-auto">
        <h3 className="text-sm font-medium text-blue-800 mb-2">What's Next?</h3>
        <ul className="text-sm text-blue-700 space-y-2 text-left">
          <li className="flex items-start">
            <span className="mr-2">•</span>
            <span>We'll verify your credentials within 1-2 business days</span>
          </li>
          <li className="flex items-start">
            <span className="mr-2">•</span>
            <span>Your organization and facility will be set up in our system</span>
          </li>
          <li className="flex items-start">
            <span className="mr-2">•</span>
            <span>You'll receive email notification when your account is activated</span>
          </li>
          <li className="flex items-start">
            <span className="mr-2">•</span>
            <span>Manufacturer onboarding forms will be auto-populated with your information</span>
          </li>
          <li className="flex items-start">
            <span className="mr-2">•</span>
            <span>Start accessing wound care products and services</span>
          </li>
        </ul>
      </div>

      <div className="bg-gray-50 p-4 rounded-lg max-w-md mx-auto">
        <p className="text-sm text-gray-600">
          A confirmation email has been sent to your registered email address. 
          Please check your inbox for next steps.
        </p>
      </div>

      <Button onClick={handleContinue} size="lg">
        Continue to Login
      </Button>
    </div>
  );
}