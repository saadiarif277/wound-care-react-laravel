// components/provider/onboarding/ProviderRegistration.tsx
import React, { useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
// import { useParams } from 'react-router-dom'; // If using react-router for token
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/input'; // Assuming Shadcn Input
import { Label } from '@/Components/ui/label';   // Assuming Shadcn Label
import { Checkbox } from '@/Components/ui/checkbox'; // Assuming Shadcn Checkbox
import { Alert, AlertDescription, AlertTitle } from "@/Components/ui/alert"; // Assuming Shadcn Alert
import { CheckCircle2 } from 'lucide-react';
// For a real form, you'd likely use react-hook-form with Shadcn components
// import { useForm } from 'react-hook-form';

// Helper to get token from URL query params (alternative to useParams if not using client-side routing for this page)
const getTokenFromUrl = () => {
  const urlParams = new URLSearchParams(window.location.search);
  return urlParams.get('token') || window.location.pathname.split('/').pop();
};

export function ProviderRegistration() {
  // const { token } = useParams(); // Use if token is a route parameter, e.g., /register/:token
  const token = getTokenFromUrl(); // Use if token is a query parameter, e.g., /register?token=...

  const [invitationDetails, setInvitationDetails] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);
  const [registrationError, setRegistrationError] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [registrationSuccess, setRegistrationSuccess] = useState(false);
  const [successData, setSuccessData] = useState(null);

  // Form state - simple state for now, ideally use react-hook-form
  const [formData, setFormData] = useState({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
    npi: '',
    // medical_license_number: '', // From markdown, not in API validation yet
    // medical_license_state: '',  // From markdown, not in API validation yet
    agree_terms: false,
  });

  useEffect(() => {
    if (token) {
      setIsLoading(true);
      fetch(`/api/v1/invitations/verify/${token}`)
        .then(res => {
          if (!res.ok) {
            throw new Error('Failed to verify invitation. Token might be invalid or expired.');
          }
          return res.json();
        })
        .then(data => {
          if (data.valid) {
            setInvitationDetails(data);
            // Pre-fill form data from invitation
            setFormData(prev => ({
              ...prev,
              email: data.email || '',
              first_name: data.first_name || '',
              last_name: data.last_name || '',
            }));
            setError(null);
          } else {
            setError(data.message || 'Invalid or expired invitation.');
          }
        })
        .catch(err => {
          setError(err.message || 'An error occurred while verifying the invitation.');
        })
        .finally(() => setIsLoading(false));
    } else {
      setError('No invitation token provided.');
      setIsLoading(false);
    }
  }, [token]);

  const handleInputChange = (e) => {
    const { name, value, type, checked } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value,
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setRegistrationError(null);
    setIsSubmitting(true);

    if (!formData.agree_terms) {
      setRegistrationError('You must agree to the terms and conditions.');
      setIsSubmitting(false);
      return;
    }

    if (formData.password !== formData.password_confirmation) {
        setRegistrationError('Passwords do not match.');
        setIsSubmitting(false);
        return;
    }

    const payload = {
        first_name: formData.first_name,
        last_name: formData.last_name,
        password: formData.password,
        password_confirmation: formData.password_confirmation,
        npi: formData.npi,
        // Potentially add other fields required by the API
    };

    try {
      const response = await fetch(`/api/v1/invitations/accept/${token}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify(payload),
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || `Registration failed with status ${response.status}`);
      }

      // Handle successful registration
      setSuccessData(data);
      setRegistrationSuccess(true);

    } catch (err) {
      setRegistrationError(err.message || 'An unexpected error occurred during registration.');
    } finally {
      setIsSubmitting(false);
    }
  };

  if (isLoading) {
    return <div className="flex justify-center items-center h-screen">Verifying invitation...</div>;
  }

  if (error || !invitationDetails) {
    return (
      <div className="max-w-2xl mx-auto p-6 flex justify-center items-center h-screen">
        <Alert variant="destructive">
          <AlertTitle>Error</AlertTitle>
          <AlertDescription>{error || 'Could not load invitation details.'}</AlertDescription>
        </Alert>
      </div>
    );
  }

  // Show success screen after successful registration
  if (registrationSuccess) {
    return (
      <div className="max-w-2xl mx-auto p-6 my-10">
        <Card>
          <CardContent className="p-8 text-center">
            <div className="flex justify-center mb-6">
              <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center">
                <CheckCircle2 className="w-8 h-8 text-green-600" />
              </div>
            </div>

            <h1 className="text-2xl font-bold text-gray-900 mb-4">
              Registration Successful!
            </h1>

            <p className="text-gray-600 mb-6">
              Welcome, {successData?.user?.first_name}! Your account has been created successfully.
            </p>

            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
              <h3 className="text-sm font-medium text-blue-800 mb-2">What's Next?</h3>
              <ul className="text-sm text-blue-700 space-y-1 text-left">
                <li>• Your account is now active and ready to use</li>
                <li>• You can log in using your email: {successData?.user?.email}</li>
                <li>• Complete your profile setup in the dashboard</li>
                <li>• Start collaborating with {invitationDetails?.organization_name}</li>
              </ul>
            </div>

            <div className="space-y-3">
              <Button
                onClick={() => window.location.href = '/login'}
                className="w-full"
              >
                Continue to Login
              </Button>
              <Button
                variant="secondary"
                onClick={() => window.location.href = '/'}
                className="w-full"
              >
                Go to Homepage
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto p-6 my-10">
      <Card>
        <CardHeader>
          <CardTitle className="text-2xl font-bold">
            Welcome to {invitationDetails.organization_name || 'Our Portal'}!
          </CardTitle>
          <CardDescription className="mb-6">
            You've been invited by {invitationDetails.organization_name} to join as a provider. Please complete your registration.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-6">
            <div className="space-y-2">
              <Label htmlFor="email">Email</Label>
              <Input id="email" name="email" type="email" value={formData.email} disabled readOnly />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="first_name">First Name</Label>
                <Input id="first_name" name="first_name" value={formData.first_name} onChange={handleInputChange} required />
              </div>
              <div className="space-y-2">
                <Label htmlFor="last_name">Last Name</Label>
                <Input id="last_name" name="last_name" value={formData.last_name} onChange={handleInputChange} required />
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="password">Password</Label>
              <Input id="password" name="password" type="password" value={formData.password} onChange={handleInputChange} required minLength={8} />
            </div>

            <div className="space-y-2">
              <Label htmlFor="password_confirmation">Confirm Password</Label>
              <Input id="password_confirmation" name="password_confirmation" type="password" value={formData.password_confirmation} onChange={handleInputChange} required />
            </div>

            <div className="space-y-2">
              <Label htmlFor="npi">NPI Number (Optional)</Label>
              <Input id="npi" name="npi" value={formData.npi} onChange={handleInputChange} pattern="[0-9]{10}" title="NPI must be 10 digits" />
              <p className="text-sm text-muted-foreground">We'll verify this automatically if provided.</p>
            </div>

            {/*
            <div className="space-y-2">
              <Label htmlFor="medical_license_number">Medical License Number</Label>
              <Input id="medical_license_number" name="medical_license_number" value={formData.medical_license_number} onChange={handleInputChange} required />
            </div>

            <div className="space-y-2">
              <Label htmlFor="medical_license_state">Medical License State</Label>
              <Input id="medical_license_state" name="medical_license_state" value={formData.medical_license_state} onChange={handleInputChange} required />
            </div>
            */}

            <div className="flex items-center space-x-2">
              <Checkbox id="agree_terms" name="agree_terms" checked={formData.agree_terms} onCheckedChange={(checked) => setFormData(p => ({...p, agree_terms: checked}))} />
              <Label htmlFor="agree_terms" className="text-sm font-normal">
                I agree to the <a href="/terms" target="_blank" className="underline">terms and conditions</a>.
              </Label>
            </div>

            {registrationError && (
              <Alert variant="destructive">
                <AlertTitle>Registration Error</AlertTitle>
                <AlertDescription>{registrationError}</AlertDescription>
              </Alert>
            )}
            <CardFooter className="px-0 pt-6">
                <Button type="submit" className="w-full" disabled={isSubmitting || !formData.agree_terms}>
                {isSubmitting ? 'Registering...' : 'Complete Registration'}
                </Button>
            </CardFooter>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
