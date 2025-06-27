import { useState, useCallback, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import axios from 'axios';
import { debounce } from 'lodash';
import {
  PatientInsuranceData,
  PatientAddress,
  InsuranceCoverage,
} from '@/types/quickRequest';

// Validation schema
const patientSchema = z.object({
  patient: z.object({
    firstName: z.string().min(1, 'First name is required'),
    lastName: z.string().min(1, 'Last name is required'),
    middleName: z.string().optional(),
    dateOfBirth: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Invalid date format'),
    gender: z.enum(['male', 'female', 'other', 'unknown']),
    ssn: z.string().regex(/^\d{3}-\d{2}-\d{4}$/, 'Invalid SSN format').optional(),
    medicareNumber: z.string().optional(),
    address: z.object({
      use: z.enum(['home', 'work', 'temp', 'old', 'billing']),
      type: z.enum(['postal', 'physical', 'both']),
      line: z.array(z.string()).min(1, 'Address is required'),
      city: z.string().min(1, 'City is required'),
      state: z.string().length(2, 'State must be 2 characters'),
      postalCode: z.string().regex(/^\d{5}(-\d{4})?$/, 'Invalid ZIP code'),
      country: z.string().default('USA'),
    }),
    phone: z.string().regex(/^\d{3}-\d{3}-\d{4}$/, 'Invalid phone format'),
    email: z.string().email('Invalid email').optional(),
    emergencyContact: z.object({
      name: z.object({
        given: z.array(z.string()),
        family: z.string(),
      }),
      telecom: z.array(z.object({
        system: z.enum(['phone', 'email']),
        value: z.string(),
        use: z.string(),
      })),
    }).optional(),
  }),
  insurance: z.object({
    primary: z.object({
      type: z.enum(['medicare', 'medicaid', 'private', 'other']),
      policyNumber: z.string().min(1, 'Policy number is required'),
      groupNumber: z.string().optional(),
      subscriberId: z.string().min(1, 'Subscriber ID is required'),
      subscriberName: z.string().min(1, 'Subscriber name is required'),
      subscriberRelationship: z.string().min(1, 'Relationship is required'),
      effectiveDate: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Invalid date'),
      expirationDate: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Invalid date').optional(),
      payorName: z.string().min(1, 'Payor name is required'),
      payorId: z.string().optional(),
      planName: z.string().optional(),
      planType: z.string().optional(),
    }),
    secondary: z.object({
      type: z.enum(['medicare', 'medicaid', 'private', 'other']),
      policyNumber: z.string(),
      subscriberId: z.string(),
      subscriberName: z.string(),
      subscriberRelationship: z.string(),
      effectiveDate: z.string(),
      payorName: z.string(),
    }).optional(),
    tertiary: z.object({
      type: z.enum(['medicare', 'medicaid', 'private', 'other']),
      policyNumber: z.string(),
      subscriberId: z.string(),
      subscriberName: z.string(),
      subscriberRelationship: z.string(),
      effectiveDate: z.string(),
      payorName: z.string(),
    }).optional(),
  }),
});

type PatientInsuranceFormData = z.infer<typeof patientSchema>;

interface UsePatientInsuranceProps {
  initialData?: Partial<PatientInsuranceData>;
  onSave?: (data: PatientInsuranceData) => void;
  onNext?: (data: PatientInsuranceData) => void;
}

export function usePatientInsurance({
  initialData,
  onSave,
  onNext,
}: UsePatientInsuranceProps) {
  const [isSearchingPatient, setIsSearchingPatient] = useState(false);
  const [patientSearchResults, setPatientSearchResults] = useState<any[]>([]);
  const [isVerifyingInsurance, setIsVerifyingInsurance] = useState(false);
  const [insuranceVerificationStatus, setInsuranceVerificationStatus] = useState<{
    primary?: 'verified' | 'unverified' | 'error';
    secondary?: 'verified' | 'unverified' | 'error';
    tertiary?: 'verified' | 'unverified' | 'error';
  }>({});

  const {
    register,
    handleSubmit,
    control,
    watch,
    setValue,
    formState: { errors, isSubmitting, isDirty },
    trigger,
  } = useForm<PatientInsuranceFormData>({
    resolver: zodResolver(patientSchema),
    defaultValues: initialData || {
      patient: {
        address: {
          use: 'home',
          type: 'physical',
          line: [''],
          country: 'USA',
        },
      },
    },
  });

  // Watch for patient name changes to search for duplicates
  const firstName = watch('patient.firstName');
  const lastName = watch('patient.lastName');
  const dateOfBirth = watch('patient.dateOfBirth');

  // Debounced patient search
  const searchForDuplicatePatients = useCallback(
    debounce(async (first: string, last: string, dob: string) => {
      if (!first || !last || !dob) return;

      setIsSearchingPatient(true);
      try {
        const response = await axios.get('/api/v1/patients/search', {
          params: { firstName: first, lastName: last, dateOfBirth: dob },
        });
        setPatientSearchResults(response.data.data);
      } catch (error) {
        console.error('Patient search failed:', error);
      } finally {
        setIsSearchingPatient(false);
      }
    }, 500),
    []
  );

  useEffect(() => {
    searchForDuplicatePatients(firstName, lastName, dateOfBirth);
  }, [firstName, lastName, dateOfBirth, searchForDuplicatePatients]);

  // Insurance verification
  const verifyInsurance = useCallback(
    async (type: 'primary' | 'secondary' | 'tertiary') => {
      const insurance = watch(`insurance.${type}`);
      if (!insurance) return;

      setIsVerifyingInsurance(true);
      try {
        const response = await axios.post('/api/v1/insurance/verify', {
          ...insurance,
          patient: {
            firstName: watch('patient.firstName'),
            lastName: watch('patient.lastName'),
            dateOfBirth: watch('patient.dateOfBirth'),
          },
        });

        setInsuranceVerificationStatus(prev => ({
          ...prev,
          [type]: response.data.verified ? 'verified' : 'unverified',
        }));

        // Update form with verified data
        if (response.data.verified && response.data.updatedData) {
          Object.entries(response.data.updatedData).forEach(([key, value]) => {
            setValue(`insurance.${type}.${key}` as any, value);
          });
        }
      } catch (error) {
        setInsuranceVerificationStatus(prev => ({
          ...prev,
          [type]: 'error',
        }));
      } finally {
        setIsVerifyingInsurance(false);
      }
    },
    [watch, setValue]
  );

  // Load existing patient data
  const loadPatientData = useCallback(
    (patientId: string) => {
      axios
        .get(`/api/v1/patients/${patientId}`)
        .then(response => {
          const patientData = response.data.data;
          // Map FHIR patient data to form structure
          setValue('patient', {
            firstName: patientData.name?.[0]?.given?.[0] || '',
            lastName: patientData.name?.[0]?.family || '',
            dateOfBirth: patientData.birthDate || '',
            gender: patientData.gender || 'unknown',
            address: {
              use: 'home',
              type: 'physical',
              line: patientData.address?.[0]?.line || [''],
              city: patientData.address?.[0]?.city || '',
              state: patientData.address?.[0]?.state || '',
              postalCode: patientData.address?.[0]?.postalCode || '',
              country: patientData.address?.[0]?.country || 'USA',
            },
            phone: patientData.telecom?.find((t: any) => t.system === 'phone')?.value || '',
            email: patientData.telecom?.find((t: any) => t.system === 'email')?.value || '',
          });
        })
        .catch(error => {
          console.error('Failed to load patient data:', error);
        });
    },
    [setValue]
  );

  // Format phone number
  const formatPhoneNumber = useCallback((value: string) => {
    const cleaned = value.replace(/\D/g, '');
    if (cleaned.length >= 10) {
      return `${cleaned.slice(0, 3)}-${cleaned.slice(3, 6)}-${cleaned.slice(6, 10)}`;
    }
    return value;
  }, []);

  // Format SSN
  const formatSSN = useCallback((value: string) => {
    const cleaned = value.replace(/\D/g, '');
    if (cleaned.length >= 9) {
      return `${cleaned.slice(0, 3)}-${cleaned.slice(3, 5)}-${cleaned.slice(5, 9)}`;
    }
    return value;
  }, []);

  // Handle form submission
  const onSubmit = handleSubmit(async (data) => {
    // Validate insurance verification status
    if (insuranceVerificationStatus.primary === 'unverified') {
      const shouldContinue = window.confirm(
        'Primary insurance is not verified. Do you want to continue?'
      );
      if (!shouldContinue) return;
    }

    // Save progress
    if (onSave) {
      await onSave(data as PatientInsuranceData);
    }

    // Proceed to next step
    if (onNext) {
      await onNext(data as PatientInsuranceData);
    }
  });

  // Auto-save functionality
  useEffect(() => {
    if (isDirty && onSave) {
      const saveTimer = setTimeout(() => {
        const formData = watch();
        onSave(formData as PatientInsuranceData);
      }, 3000);

      return () => clearTimeout(saveTimer);
    }
  }, [isDirty, watch, onSave]);

  return {
    // Form methods
    register,
    handleSubmit: onSubmit,
    control,
    errors,
    isSubmitting,
    isDirty,
    watch,
    setValue,
    trigger,

    // Patient search
    isSearchingPatient,
    patientSearchResults,
    loadPatientData,

    // Insurance verification
    isVerifyingInsurance,
    insuranceVerificationStatus,
    verifyInsurance,

    // Formatters
    formatPhoneNumber,
    formatSSN,

    // Computed values
    hasSecondaryInsurance: !!watch('insurance.secondary'),
    hasTertiaryInsurance: !!watch('insurance.tertiary'),
    canProceed: !Object.values(errors).length && !isSubmitting,
  };
}