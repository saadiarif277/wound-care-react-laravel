
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
            const response = await axios.post('/quick-requests/submit-order', {
                formData,
                episodeData: validatedEpisodeData,
                adminNote
            });

            if (response.data.success) {
                setShowSubmitModal(false);
                setOrderSubmitted(true);
                setShowSuccessModal(true);
            } else {
                toast({ title: "Submission Failed", description: response.data.message, variant: "destructive" });
            }
        } catch (error) {
            toast({ title: "Error", description: "An unexpected error occurred.", variant: "destructive" });
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
