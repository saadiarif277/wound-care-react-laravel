import { useState } from 'react';
import { IvrFieldPreview } from '@/Components/IVR/IvrFieldPreview';

interface ReviewAndSubmitStepProps {
    formData: any;
    onSubmit: (data: any) => void;
}

const ReviewAndSubmitStep = ({ formData, onSubmit }: ReviewAndSubmitStepProps) => {
    const [loading, setLoading] = useState(false);
    
    const getManufacturerFromProduct = (product: any) => {
        // Map product Q-codes to manufacturer keys
        const manufacturerMap: Record<string, string> = {
            'Q4154': 'ACZ_Distribution',
            'Q4250': 'MedLife',
            'Q4290': 'Extremity_Care',
            'Q4121': 'BioWerX',
            'Q4134': 'BioWound',
            'Q4222': 'Advanced_Health',
            'Q4220': 'Centurion',
            'Q4252': 'Skye_Biologics',
            'Q4217': 'Total_Ancillary_Forms'
        };
        return manufacturerMap[product.q_code] || product.manufacturer?.replace(/\s+/g, '_');
    };
    
    const handleSubmit = async () => {
        setLoading(true);
        try {
            // Get manufacturer from selected product
            const manufacturer = getManufacturerFromProduct(formData.selected_products[0]);
            
            // Fetch manufacturer configuration
            const response = await fetch(`/api/v1/ivr/manufacturers/${manufacturer}/fields`);
            const config = await response.json();
            
            if (config.success) {
                // Create PDF submission with prefill data
                const submissionResponse = await fetch('/api/v1/pdf/generate-ivr', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        episode_id: formData.episode_id,
                        manufacturer_id: manufacturer,
                        prefill_data: {
                            // Patient Information
                            patient_first_name: formData.patient_first_name,
                            patient_last_name: formData.patient_last_name,
                            patient_dob: formData.patient_dob,
                            patient_member_id: formData.patient_member_id,
                            patient_address: formData.patient_address_line1,
                            patient_city: formData.patient_city,
                            patient_state: formData.patient_state,
                            patient_zip: formData.patient_zip,
                            patient_phone: formData.patient_phone,
                            
                            // Provider Information
                            provider_name: formData.provider_name,
                            provider_npi: formData.provider_npi,
                            facility_name: formData.facility_name,
                            facility_npi: formData.facility_npi,
                            facility_address: formData.facility_address,
                            facility_city: formData.facility_city,
                            facility_state: formData.facility_state,
                            facility_zip: formData.facility_zip,
                            facility_phone: formData.facility_phone,
                            
                            // Clinical Information
                            wound_type: formData.wound_type,
                            wound_location: formData.wound_location,
                            wound_size: `${formData.wound_size_length || 0} x ${formData.wound_size_width || 0} cm`,
                            wound_onset_date: formData.wound_onset_date,
                            failed_conservative_treatment: formData.failed_conservative_treatment ? 'Yes' : 'No',
                            
                            // Insurance Information
                            primary_insurance_name: formData.primary_insurance_name,
                            primary_member_id: formData.primary_member_id,
                            primary_group_number: formData.primary_group_number,
                            primary_payer_phone: formData.primary_payer_phone,
                            
                            // Product Information
                            product_name: formData.selected_products?.[0]?.name || formData.selected_products?.[0]?.product?.name,
                            product_code: formData.selected_products?.[0]?.code || formData.selected_products?.[0]?.product?.code,
                            product_size: formData.selected_products?.[0]?.size,
                            quantity: formData.selected_products?.[0]?.quantity,
                            
                            // Order Information
                            expected_service_date: formData.expected_service_date,
                            ordering_physician: formData.provider_name,
                            
                            // Manufacturer-specific fields
                            ...formData.manufacturer_fields
                        }
                    })
                });
                
                const submissionData = await submissionResponse.json();
                
                if (submissionData.success && submissionData.data) {
                    // Handle PDF response
                    const pdfDocument = submissionData.data;
                    
                    // If PDF was generated successfully, complete the submission
                    if (pdfDocument.status === 'generated') {
                        formData.pdf_document_id = pdfDocument.document_id;
                        formData.ivr_sent_at = new Date().toISOString();
                        
                        // Submit the complete order
                        onSubmit(formData);
                    } else {
                        console.error('PDF generation failed:', pdfDocument.status);
                    }
                } else {
                    console.error('Failed to create PDF submission:', submissionData.message || submissionData.error);
                }
            } else {
                console.error('Failed to load manufacturer configuration');
            }
        } catch (error) {
            console.error('Error loading manufacturer config:', error);
        } finally {
            setLoading(false);
        }
    };
    
    
    return (
        <div className="space-y-6">
            <h2 className="text-2xl font-bold">Review & Submit</h2>
            
            {/* Show IVR field preview */}
            {formData.selected_products?.length > 0 && (
                <IvrFieldPreview 
                    formData={formData}
                    manufacturer={getManufacturerFromProduct(formData.selected_products[0])}
                />
            )}
            
            {/* Order summary */}
            <div className="bg-gray-50 p-6 rounded-lg">
                <h3 className="font-semibold mb-3">Order Summary</h3>
                <dl className="space-y-2">
                    <div className="flex justify-between">
                        <dt>Patient ID:</dt>
                        <dd>{formData.patient_display_id}</dd>
                    </div>
                    <div className="flex justify-between">
                        <dt>Provider:</dt>
                        <dd>{formData.provider_name}</dd>
                    </div>
                    <div className="flex justify-between">
                        <dt>Product:</dt>
                        <dd>{formData.selected_products?.[0]?.name || formData.selected_products?.[0]?.product?.name}</dd>
                    </div>
                    <div className="flex justify-between">
                        <dt>Insurance:</dt>
                        <dd>{formData.primary_insurance_name}</dd>
                    </div>
                    <div className="flex justify-between">
                        <dt>Service Date:</dt>
                        <dd>{formData.expected_service_date}</dd>
                    </div>
                </dl>
            </div>
            
            <div className="flex justify-end">
                <button
                    onClick={handleSubmit}
                    disabled={loading}
                    className="px-6 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 disabled:opacity-50"
                >
                    {loading ? 'Generating PDF...' : 'Generate IVR Form'}
                </button>
            </div>
        </div>
    );
};

export default ReviewAndSubmitStep;
