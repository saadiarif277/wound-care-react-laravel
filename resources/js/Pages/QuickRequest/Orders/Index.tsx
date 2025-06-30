

import { Button } from '@/Components/Button';
import { useOrderState } from './hooks/useOrderState';
import { PatientInsuranceSection } from './order/PatientInsuranceSection';
import { ProductSection } from './order/ProductSection';
import { FormsSection } from './order/FormsSection';
import { ClinicalSection } from './order/ClinicalSection';
import { ProviderSection } from './order/ProviderSection';
import { SubmissionSection } from './order/SubmissionSection';
import { OrderModals } from './order/OrderModals';
import { OrderDashboard } from './admin/OrderDashboard';

// Define the structure of the props we expect from Inertia
interface OrderReviewProps {
    formData: any; // Replace 'any' with a more specific type if available
    validatedEpisodeData: any; // Replace 'any' with a more specific type if available
}

// Permission helper functions
const canViewFinancialData = (userRole: string): boolean => {
    return userRole !== 'OM'; // Office Managers cannot see financial data
};

const canViewAllOrders = (userRole: string): boolean => {
    return userRole === 'Admin' || userRole === 'Provider'; // Providers see their own, Admins see all
};

const Index: React.FC<OrderReviewProps> = ({ formData, validatedEpisodeData }) => {
    const {
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
    } = useOrderState(formData, validatedEpisodeData);

    // Helper function to safely map form data to order data structure
    const mapFormDataToOrderData = (formData: any, validatedEpisodeData: any): OrderData => {
        return {
            orderNumber: validatedEpisodeData?.episode_id || formData?.episode_id || 'N/A',
            orderStatus: formData?.order_status || 'draft',
            createdDate: formData?.created_at ? new Date(formData.created_at).toLocaleDateString() : new Date().toLocaleDateString(),
            createdBy: formData?.provider_name || 'N/A',
            patient: {
                fullName: `${formData?.patient_first_name || ''} ${formData?.patient_last_name || ''}`.trim() || 'N/A',
                dateOfBirth: formData?.patient_dob || 'N/A',
                phone: formData?.patient_phone || 'N/A',
                email: formData?.patient_email || 'N/A',
                address: formData?.patient_address || 'N/A',
                primaryInsurance: {
                    payerName: formData?.primary_insurance_name || 'N/A',
                    planName: formData?.primary_plan_type || 'N/A',
                    policyNumber: formData?.primary_member_id || 'N/A',
                },
                secondaryInsurance: formData?.has_secondary_insurance ? {
                    payerName: formData?.secondary_insurance_name || 'N/A',
                    planName: formData?.secondary_plan_type || 'N/A',
                    policyNumber: formData?.secondary_member_id || 'N/A',
                } : null,
                insuranceCardUploaded: !!formData?.insurance_card_front,
            },
            provider: {
                name: formData?.provider_name || 'N/A',
                facilityName: formData?.facility_name || 'N/A',
                facilityAddress: formData?.facility_address || formData?.service_address || 'N/A',
                organization: formData?.organization_name || 'N/A',
                npi: formData?.provider_npi || 'N/A',
            },
            clinical: {
                woundType: formData?.wound_type || 'N/A',
                woundSize: formData?.wound_size_length && formData?.wound_size_width
                    ? `${formData.wound_size_length} x ${formData.wound_size_width}cm`
                    : 'N/A',
                diagnosisCodes: Array.isArray(formData?.diagnosis_codes)
                    ? formData.diagnosis_codes.map((code: any) => ({
                        code: typeof code === 'string' ? code : code?.code || 'N/A',
                        description: typeof code === 'object' ? code?.description || 'N/A' : 'N/A'
                    }))
                    : [],
                icd10Codes: Array.isArray(formData?.icd10_codes)
                    ? formData.icd10_codes.map((code: any) => ({
                        code: typeof code === 'string' ? code : code?.code || 'N/A',
                        description: typeof code === 'object' ? code?.description || 'N/A' : 'N/A'
                    }))
                    : [],
                procedureInfo: formData?.procedure_info || 'N/A',
                priorApplications: parseInt(formData?.prior_applications) || 0,
                anticipatedApplications: parseInt(formData?.anticipated_applications) || 0,
                facilityInfo: formData?.facility_name || 'N/A',
            },
            product: {
                name: formData?.selected_products?.[0]?.product?.name || 'N/A',
                sizes: formData?.selected_products?.map((p: any) => p?.size || 'Standard') || ['N/A'],
                quantity: parseInt(formData?.selected_products?.[0]?.quantity) || 1,
                aspPrice: parseFloat(formData?.selected_products?.[0]?.product?.price) || 0,
                discountedPrice: parseFloat(formData?.selected_products?.[0]?.product?.discounted_price) ||
                                parseFloat(formData?.selected_products?.[0]?.product?.price) || 0,
                coverageWarnings: formData?.coverage_warnings || [],
            },
            ivrForm: {
                status: formData?.docuseal_submission_id ? 'Completed' : 'Not Started',
                submissionDate: formData?.ivr_completed_at || 'N/A',
                documentLink: formData?.ivr_document_link || '',
            },
            orderForm: {
                status: formData?.order_form_status || 'Not Sent',
                submissionDate: formData?.order_form_completed_at || 'N/A',
                documentLink: formData?.order_form_link || '',
            },
        };
    };

    // Create order data with proper error handling
    const orderData = mapFormDataToOrderData(formData, validatedEpisodeData);

    // Show admin dashboard for Admin role
    if (userRole === 'Admin') {
        return <OrderDashboard />;
    }

    return (
        <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
            <div className="container mx-auto px-4 py-8 max-w-6xl">
                {/* Header */}
                <div className="flex justify-between items-start mb-8">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-900 mb-2">
                            {orderSubmitted ? 'Order Details' : 'Order Review & Summary'}
                        </h1>
                        <div className="flex items-center gap-4 text-sm text-muted-foreground">
                            <span>Order #{orderData.orderNumber}</span>
                            <span>•</span>
                            <span>Created {orderData.createdDate}</span>
                            <span>•</span>
                            <span>By {orderData.createdBy}</span>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <select
                            value={userRole}
                            onChange={(e) => setUserRole(e.target.value as any)}
                            className="px-3 py-1 border rounded-md text-sm"
                        >
                            <option value="Provider">Provider View</option>
                            <option value="OM">Order Manager View</option>
                            <option value="Admin">Admin View</option>
                        </select>
                        <div className="text-sm px-3 py-1 border rounded-md bg-muted/50">
                            {orderSubmitted ? "Pending Review" : "Draft"}
                        </div>
                    </div>
                </div>

                {/* Order Sections */}
                <PatientInsuranceSection
                    orderData={orderData}
                    isOpen={openSections.patient}
                    onToggle={toggleSection}
                />

                <ProductSection
                    orderData={orderData}
                    userRole={userRole}
                    isOpen={openSections.product}
                    onToggle={toggleSection}
                />

                <FormsSection
                    orderData={orderData}
                    isOpen={openSections.forms}
                    onToggle={toggleSection}
                />

                <ClinicalSection
                    orderData={orderData}
                    isOpen={openSections.clinical}
                    onToggle={toggleSection}
                />

                <ProviderSection
                    orderData={orderData}
                    isOpen={openSections.provider}
                    onToggle={toggleSection}
                />

                <SubmissionSection
                    orderData={orderData}
                    userRole={userRole}
                    orderSubmitted={orderSubmitted}
                    isOpen={openSections.submission}
                    onToggle={toggleSection}
                />

                {/* Submit Button at Bottom */}
                {!orderSubmitted && (
                    <div className="flex justify-center mt-8">
                        <Button
                            onClick={handleSubmitOrder}
                            disabled={!isOrderComplete()}
                            className="bg-primary hover:bg-primary/90 text-primary-foreground px-8 py-2"
                        >
                            Submit Order
                        </Button>
                    </div>
                )}

                {/* Modals */}
                <OrderModals
                    showSubmitModal={showSubmitModal}
                    showSuccessModal={showSuccessModal}
                    showNoteModal={showNoteModal}
                    confirmationChecked={confirmationChecked}
                    adminNote={adminNote}
                    onSubmitModalChange={setShowSubmitModal}
                    onSuccessModalChange={setShowSuccessModal}
                    onNoteModalChange={setShowNoteModal}
                    onConfirmationChange={setConfirmationChecked}
                    onAdminNoteChange={setAdminNote}
                    onConfirmSubmission={confirmSubmission}
                    onAddNote={handleAddNote}
                    onFinishSubmission={finishSubmission}
                />
            </div>
        </div>
    );
};

export default Index;
