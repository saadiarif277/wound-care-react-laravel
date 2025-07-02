import { useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';

// Keys representing collapsible order sections
export type SectionKey = 'patient' | 'provider' | 'clinical' | 'product' | 'forms' | 'submission';
import { UserRole } from '../types/orderTypes';
import { useToast } from '../hooks/use-toast';

export const useOrderState = (formData: any, validatedEpisodeData: any) => {
    const [userRole, setUserRole] = useState<UserRole>('Provider');
    // Keep track of which order sections are open
    const [openSections, setOpenSections] = useState<Record<SectionKey, boolean>>({
        patient: true,
        provider: true,
        clinical: true,
        product: true,
        forms: true,
        submission: true
    });
    const [showSubmitModal, setShowSubmitModal] = useState(false);
    const [showSuccessModal, setShowSuccessModal] = useState(false);
    const [showNoteModal, setShowNoteModal] = useState(false);
    const [confirmationChecked, setConfirmationChecked] = useState(false);
    const [adminNote, setAdminNote] = useState('');
    const [orderSubmitted, setOrderSubmitted] = useState(false);
    const { toast } = useToast();

    const toggleSection = (section: string) => {
        setOpenSections(prev => ({
            ...prev,
            [section as SectionKey]: !prev[section as SectionKey]
        }));
    };

    const isOrderComplete = () => {
        // Basic validation: check if key submission fields are confirmed
        return (
            formData.information_accurate &&
            formData.maintain_documentation &&
            formData.authorize_prior_auth
        );
    };

    const handleSubmitOrder = () => {
        if (!isOrderComplete()) {
            toast({
                title: "Order Incomplete",
                description: "Please confirm all declarations in the submission section before submitting.",
                variant: "destructive"
            });
            return;
        }
        setShowSubmitModal(true);
    };

    const confirmSubmission = async () => {
        if (!confirmationChecked) {
            toast({
                title: "Confirmation Required",
                description: "Please confirm that the information is accurate and complete.",
                variant: "destructive"
            });
            return;
        }

        try {
            // Clean and validate form data before submission
            const cleanedFormData = { ...formData };

            // Ensure all string fields are properly formatted
            const stringFields = [
                'patient_first_name', 'patient_last_name', 'patient_dob', 'patient_gender',
                'patient_member_id', 'patient_address_line1', 'patient_address_line2',
                'patient_city', 'patient_state', 'patient_zip', 'patient_phone', 'patient_email',
                'provider_first_name', 'provider_last_name', 'provider_npi', 'provider_ptan',
                'provider_credentials', 'facility_name', 'facility_npi', 'facility_ptan',
                'facility_address_line1', 'facility_address_line2', 'facility_city',
                'facility_state', 'facility_zip', 'facility_phone', 'wound_type',
                'wound_size_length', 'wound_size_width', 'wound_size_depth',
                'wound_duration', 'diagnosis_codes', 'place_of_service',
                'primary_insurance_payer', 'primary_insurance_plan', 'primary_insurance_policy',
                'secondary_insurance_payer', 'secondary_insurance_plan', 'secondary_insurance_policy',
                'request_type', 'information_accurate', 'maintain_documentation', 'authorize_prior_auth'
            ];

            stringFields.forEach(field => {
                if (cleanedFormData[field] !== undefined && cleanedFormData[field] !== null) {
                    cleanedFormData[field] = String(cleanedFormData[field]).trim();
                } else {
                    cleanedFormData[field] = '';
                }
            });

            // Ensure arrays are properly formatted
            if (!Array.isArray(cleanedFormData.selected_products)) {
                cleanedFormData.selected_products = [];
            }

            if (!Array.isArray(cleanedFormData.diagnosis_codes)) {
                cleanedFormData.diagnosis_codes = [];
            }

            // Ensure adminNote is always a string
            const safeAdminNote = adminNote ? String(adminNote).trim() : 'No admin note provided';

            console.log('Submitting order with cleaned formData:', cleanedFormData);
            console.log('Submitting order with episodeData:', validatedEpisodeData);
            console.log('Admin note value:', adminNote);
            console.log('Safe admin note:', safeAdminNote);

            const response = await axios.post('/quick-requests/submit-order', {
                formData: cleanedFormData, // Send the cleaned form data
                episodeData: validatedEpisodeData,
                adminNote: safeAdminNote // Use the safe admin note
            });

            if (response.data.success) {
                setShowSubmitModal(false);
                setOrderSubmitted(true);
                setShowSuccessModal(true);
            } else {
                toast({ title: "Submission Failed", description: response.data.message, variant: "destructive" });
            }
        } catch (error: any) {
            console.error('Order submission error:', error);
            console.error('Error response data:', error.response?.data);

            let errorMessage = "An unexpected error occurred.";

            if (error.response?.status === 422) {
                // Validation errors
                const errors = error.response.data.errors;
                if (errors && typeof errors === 'object') {
                    const errorList = Object.entries(errors)
                        .map(([field, messages]) => `${field}: ${Array.isArray(messages) ? messages.join(', ') : messages}`)
                        .join('\n');
                    errorMessage = `Validation errors:\n${errorList}`;
                } else {
                    errorMessage = error.response.data.message || "Please check your form data and try again.";
                }
            } else if (error.response?.data?.message) {
                errorMessage = error.response.data.message;
            } else if (error.message) {
                errorMessage = error.message;
            }

            toast({
                title: "Submission Failed",
                description: errorMessage,
                variant: "destructive"
            });
        }
    };

    const handleAddNote = () => {
        setShowSuccessModal(false);
        setShowNoteModal(true);
    };

    const finishSubmission = () => {
        setShowNoteModal(false);
        toast({
            title: "Order Submitted Successfully",
            description: "Your order has been submitted for admin review.",
        });

        // Redirect to the main dashboard
        router.get('/dashboard');
    };

  return {
    userRole,
    setUserRole,
    openSections,
    toggleSection,
    showSubmitModal,
    setShowSubmitModal,
    showSuccessModal,
    setShowSuccessModal,
    showNoteModal,
    setShowNoteModal,
    confirmationChecked,
    setConfirmationChecked,
    adminNote,
    setAdminNote,
    orderSubmitted,
    isOrderComplete,
    handleSubmitOrder,
    confirmSubmission,
    handleAddNote,
    finishSubmission
  };
};
