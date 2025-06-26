

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

    // Create a unified order data object from the props
    const orderData = {
        orderNumber: validatedEpisodeData?.episode_id || 'N/A',
        createdDate: new Date().toLocaleDateString(),
        createdBy: formData?.provider_name || 'N/A',
        patient: {
            name: `${formData.patient_first_name} ${formData.patient_last_name}`,
            dob: formData.patient_dob,
            gender: formData.patient_gender,
            phone: 'N/A', // This data is not in formData
            address: 'N/A', // This data is not in formData
            insurance: {
                primary: `${formData.primary_insurance_name} - ${formData.primary_member_id}`,
                secondary: formData.has_secondary_insurance ? `${formData.secondary_insurance_name} - ${formData.secondary_member_id}` : 'N/A',
            },
        },
        product: {
            name: formData.selected_products?.[0]?.product?.name || 'N/A',
            code: formData.selected_products?.[0]?.product?.code || 'N/A',
            quantity: formData.selected_products?.[0]?.quantity || 0,
            size: formData.selected_products?.[0]?.size || 'N/A',
            category: 'N/A', // This data is not in formData
            manufacturer: formData.selected_products?.[0]?.product?.manufacturer || 'N/A',
            shippingInfo: {
                speed: formData.shipping_speed,
                address: 'N/A', // This data is not in formData
            },
        },
        forms: {
            consent: formData.prior_auth_permission,
            assignmentOfBenefits: true, // Assuming true, not in form
            medicalNecessity: formData.medical_necessity_established,
        },
        clinical: {
            woundType: formData.wound_types.join(', '),
            location: formData.wound_location,
            size: `${formData.wound_size_length} x ${formData.wound_size_width}cm`,
            cptCodes: formData.application_cpt_codes.join(', '),
            placeOfService: formData.place_of_service,
            failedConservativeTreatment: formData.failed_conservative_treatment,
        },
        provider: {
            name: 'N/A', // This data is not in formData, should come from currentUser prop
            npi: 'N/A',
            facility: formData.facility_id ? 'Facility Name' : 'N/A', // Map ID to name
        },
        submission: {
            informationAccurate: formData.information_accurate,
            documentationMaintained: formData.maintain_documentation,
            authorizePriorAuth: formData.authorize_prior_auth,
        },
    };

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
