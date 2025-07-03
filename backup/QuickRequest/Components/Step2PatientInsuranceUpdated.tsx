import { useState } from 'react';
import { FiCreditCard, FiCheck, FiInfo } from 'react-icons/fi';
import { cn } from '@/theme/glass-theme';
import GoogleAddressAutocompleteSimple from '@/Components/GoogleAddressAutocompleteSimple';
import GoogleAddressAutocompleteWithFallback from '@/Components/GoogleAddressAutocompleteWithFallback';
import PayerSearchInput from '@/Components/PayerSearchInput';
import FormInputWithIndicator from '@/Components/ui/FormInputWithIndicator';
import Select from '@/Components/ui/Select';
import DocumentUploadCard from '@/Components/DocumentUploadCard';
import { DocumentUpload } from '@/types/document-upload';

interface Step2Props {
  formData: any;
  updateFormData: (data: any) => void;
  errors: Record<string, string>;
  facilities?: Array<{
    id: number;
    name: string;
    address?: string;
  }>;
  providers?: Array<{
    id: number;
    name: string;
    credentials?: string;
    npi?: string;
  }>;
  currentUser?: {
    role?: string;
    id?: number;
  };
}

function Step2PatientInsurance({
  formData,
  updateFormData,
  errors,
  facilities = [],
  providers = [],
  currentUser
}: Step2Props) {
  const [autoFillSuccess, setAutoFillSuccess] = useState(false);
  const [showCaregiver, setShowCaregiver] = useState(!formData.patient_is_subscriber);
  const [showSecondaryCaregiver, setShowSecondaryCaregiver] = useState(!formData.secondary_patient_is_subscriber);
  const [saveToPatientResource, setSaveToPatientResource] = useState(false);

  const states = [
    'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
    'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
    'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
    'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
    'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY'
  ];

  // Shipping speed options
  const shippingOptions = [
    { value: '1st_am', label: '1st AM (before 9AM) - Next business day' },
    { value: 'early_next_day', label: 'Early Next Day (9AM-12PM)' },
    { value: 'standard_next_day', label: 'Standard Next Day' },
    { value: 'standard_2_day', label: 'Standard 2 Day' },
  ];

  // Plan type options
  const planTypes = [
    { value: 'ffs', label: 'FFS (Fee for Service)' },
    { value: 'hmo', label: 'HMO' },
    { value: 'ppo', label: 'PPO' },
    { value: 'pos', label: 'POS' },
    { value: 'epo', label: 'EPO' },
    { value: 'medicare_advantage', label: 'Medicare Advantage' },
    { value: 'other', label: 'Other' },
  ];

  const handleDocumentsChange = (documents: DocumentUpload[]) => {
    // Find insurance card upload
    const insuranceUpload = documents.find(doc => doc.type === 'insurance_card');
    
    if (insuranceUpload) {
      // Update form data with the files
      updateFormData({
        insurance_card_front: insuranceUpload.files.primary?.file,
        insurance_card_back: insuranceUpload.files.secondary?.file,
      });
    }
  };

  const handleInsuranceDataExtracted = (data: any) => {
    const updates: any = {};

    // Update insurance information from extracted data
    if (data.payer_name) updates.primary_insurance_name = data.payer_name;
    if (data.payer_id) updates.primary_member_id = data.payer_id;
    if (data.group_number) updates.primary_group_number = data.group_number;
    if (data.plan_type) updates.primary_plan_type = data.plan_type;
    
    updates.insurance_card_auto_filled = true;
    updateFormData(updates);
    setAutoFillSuccess(true);

    setTimeout(() => {
      setAutoFillSuccess(false);
    }, 5000);
  };

  const selectedProviderValue = formData.provider_id ? 
    `${formData.provider_id}|${providers.find(p => p.id === formData.provider_id)?.npi || ''}` : '';

  const selectedFacilityValue = formData.facility_id ? 
    `${formData.facility_id}|${facilities.find(f => f.id === formData.facility_id)?.address || ''}` : '';

  return (
    <div className="space-y-6">
      {/* Step Title */}
      <div>
        <h2 className="text-2xl font-bold">Step 2: Insurance Information</h2>
        <p className="mt-2 text-gray-600">
          Please provide the patient's insurance details and upload insurance cards
        </p>
      </div>

      {/* Service Details Section */}
      <div className="bg-white rounded-lg p-6 shadow-sm border border-gray-200">
        <h3 className="text-lg font-medium mb-4 flex items-center">
          <FiInfo className="mr-2 h-5 w-5 text-blue-500" />
          Service Details
        </h3>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Provider
            </label>
            <Select
              value={selectedProviderValue}
              onChange={(value) => {
                const [id, npi] = value.split('|');
                updateFormData({ 
                  provider_id: parseInt(id),
                  provider_npi: npi || ''
                });
              }}
              options={providers.map(provider => ({
                value: `${provider.id}|${provider.npi || ''}`,
                label: `${provider.name}${provider.credentials ? `, ${provider.credentials}` : ''}${provider.npi ? ` (NPI: ${provider.npi})` : ''}`
              }))}
              placeholder="Select provider"
              error={errors.provider_id}
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Service Location/Facility
            </label>
            <Select
              value={selectedFacilityValue}
              onChange={(value) => {
                const [id, address] = value.split('|');
                updateFormData({ 
                  facility_id: parseInt(id),
                  service_address: address || ''
                });
              }}
              options={facilities.map(facility => ({
                value: `${facility.id}|${facility.address || ''}`,
                label: facility.name
              }))}
              placeholder="Select facility"
              error={errors.facility_id}
            />
            {formData.facility_id && (
              <p className="mt-1 text-xs text-gray-500">
                {facilities.find(f => f.id === formData.facility_id)?.address}
              </p>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Expected Service Date <span className="text-red-500">*</span>
            </label>
            <input
              type="date"
              value={formData.expected_service_date || ''}
              onChange={(e) => updateFormData({ expected_service_date: e.target.value })}
              className={cn(
                "w-full px-3 py-2 border rounded-md",
                errors.expected_service_date ? "border-red-300" : "border-gray-300"
              )}
              min={new Date().toISOString().split('T')[0]}
            />
            {errors.expected_service_date && (
              <p className="mt-1 text-sm text-red-600">{errors.expected_service_date}</p>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Place of Service <span className="text-red-500">*</span>
            </label>
            <Select
              value={formData.place_of_service || ''}
              onChange={(value) => updateFormData({ place_of_service: value })}
              options={[
                { value: '11', label: 'Office' },
                { value: '12', label: 'Home' },
                { value: '13', label: 'Assisted Living Facility' },
                { value: '14', label: 'Group Home' },
                { value: '31', label: 'Skilled Nursing Facility' },
                { value: '32', label: 'Nursing Facility' },
              ]}
              placeholder="Select place of service"
              error={errors.place_of_service}
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Shipping Speed
            </label>
            <Select
              value={formData.shipping_speed || ''}
              onChange={(value) => updateFormData({ shipping_speed: value })}
              options={shippingOptions}
              placeholder="Select shipping speed"
            />
          </div>
        </div>
      </div>

      {/* Insurance Card Upload Section */}
      <DocumentUploadCard
        title="Insurance Card Upload"
        description="Upload front and back of insurance card for auto-fill"
        onDocumentsChange={handleDocumentsChange}
        onInsuranceDataExtracted={handleInsuranceDataExtracted}
        allowMultiple={false}
      />

      {/* Auto-fill Success Message */}
      {autoFillSuccess && (
        <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center">
          <FiCheck className="h-5 w-5 mr-2" />
          <span className="text-sm">Insurance information extracted successfully!</span>
        </div>
      )}

      {/* Save to Patient Resource Checkbox */}
      <div className="flex items-center">
        <input
          type="checkbox"
          id="save_to_patient"
          checked={saveToPatientResource}
          onChange={(e) => {
            setSaveToPatientResource(e.target.checked);
            updateFormData({ save_insurance_to_patient_resource: e.target.checked });
          }}
          className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
        />
        <label htmlFor="save_to_patient" className="ml-2 text-sm text-gray-700">
          Save insurance card to patient resource for future use
        </label>
      </div>

      {/* Primary Insurance Information */}
      <div className="bg-white rounded-lg p-6 shadow-sm border border-gray-200">
        <h3 className="text-lg font-medium mb-4 flex items-center">
          <FiCreditCard className="mr-2 h-5 w-5 text-blue-500" />
          Primary Insurance
        </h3>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div className="sm:col-span-2">
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Primary Insurance Company <span className="text-red-500">*</span>
            </label>
            <PayerSearchInput
              value={formData.primary_insurance_name || ''}
              onChange={(value) => updateFormData({ primary_insurance_name: value })}
              placeholder="Search insurance company..."
              error={errors.primary_insurance_name}
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Member ID <span className="text-red-500">*</span>
            </label>
            <FormInputWithIndicator
              value={formData.primary_member_id || ''}
              onChange={(value) => updateFormData({ primary_member_id: value })}
              placeholder="Member ID"
              error={errors.primary_member_id}
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Group Number
            </label>
            <input
              type="text"
              value={formData.primary_group_number || ''}
              onChange={(e) => updateFormData({ primary_group_number: e.target.value })}
              className="w-full px-3 py-2 border border-gray-300 rounded-md"
              placeholder="Group number"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Plan Type
            </label>
            <Select
              value={formData.primary_plan_type || ''}
              onChange={(value) => updateFormData({ primary_plan_type: value })}
              options={planTypes}
              placeholder="Select plan type"
            />
          </div>

          {/* Patient is Subscriber Checkbox */}
          <div className="sm:col-span-2">
            <div className="flex items-center">
              <input
                type="checkbox"
                id="patient_is_subscriber"
                checked={formData.patient_is_subscriber !== false}
                onChange={(e) => {
                  updateFormData({ patient_is_subscriber: e.target.checked });
                  setShowCaregiver(!e.target.checked);
                }}
                className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
              />
              <label htmlFor="patient_is_subscriber" className="ml-2 text-sm text-gray-700">
                Patient is the insurance subscriber
              </label>
            </div>
          </div>

          {/* Caregiver Information (shown when patient is not subscriber) */}
          {showCaregiver && (
            <>
              <div className="sm:col-span-2 border-t pt-4 mt-2">
                <h4 className="text-md font-medium text-gray-700 mb-3">
                  Primary Insurance Subscriber Information
                </h4>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Subscriber Name
                </label>
                <input
                  type="text"
                  value={formData.primary_subscriber_name || ''}
                  onChange={(e) => updateFormData({ primary_subscriber_name: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md"
                  placeholder="Subscriber name"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Relationship to Patient
                </label>
                <Select
                  value={formData.primary_subscriber_relationship || ''}
                  onChange={(value) => updateFormData({ primary_subscriber_relationship: value })}
                  options={[
                    { value: 'spouse', label: 'Spouse' },
                    { value: 'parent', label: 'Parent' },
                    { value: 'child', label: 'Child' },
                    { value: 'other', label: 'Other' },
                  ]}
                  placeholder="Select relationship"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Subscriber DOB
                </label>
                <input
                  type="date"
                  value={formData.primary_subscriber_dob || ''}
                  onChange={(e) => updateFormData({ primary_subscriber_dob: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md"
                />
              </div>
            </>
          )}
        </div>
      </div>

      {/* Secondary Insurance Information (Optional) */}
      <div className="bg-white rounded-lg p-6 shadow-sm border border-gray-200">
        <h3 className="text-lg font-medium mb-4">
          Secondary Insurance (Optional)
        </h3>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div className="sm:col-span-2">
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Secondary Insurance Company
            </label>
            <PayerSearchInput
              value={formData.secondary_insurance_name || ''}
              onChange={(value) => updateFormData({ secondary_insurance_name: value })}
              placeholder="Search insurance company..."
            />
          </div>

          {formData.secondary_insurance_name && (
            <>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Member ID
                </label>
                <input
                  type="text"
                  value={formData.secondary_member_id || ''}
                  onChange={(e) => updateFormData({ secondary_member_id: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md"
                  placeholder="Member ID"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Group Number
                </label>
                <input
                  type="text"
                  value={formData.secondary_group_number || ''}
                  onChange={(e) => updateFormData({ secondary_group_number: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md"
                  placeholder="Group number"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Plan Type
                </label>
                <Select
                  value={formData.secondary_plan_type || ''}
                  onChange={(value) => updateFormData({ secondary_plan_type: value })}
                  options={planTypes}
                  placeholder="Select plan type"
                />
              </div>

              {/* Secondary Patient is Subscriber Checkbox */}
              <div className="sm:col-span-2">
                <div className="flex items-center">
                  <input
                    type="checkbox"
                    id="secondary_patient_is_subscriber"
                    checked={formData.secondary_patient_is_subscriber !== false}
                    onChange={(e) => {
                      updateFormData({ secondary_patient_is_subscriber: e.target.checked });
                      setShowSecondaryCaregiver(!e.target.checked);
                    }}
                    className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                  />
                  <label htmlFor="secondary_patient_is_subscriber" className="ml-2 text-sm text-gray-700">
                    Patient is the insurance subscriber
                  </label>
                </div>
              </div>

              {/* Secondary Caregiver Information */}
              {showSecondaryCaregiver && (
                <>
                  <div className="sm:col-span-2 border-t pt-4 mt-2">
                    <h4 className="text-md font-medium text-gray-700 mb-3">
                      Secondary Insurance Subscriber Information
                    </h4>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Subscriber Name
                    </label>
                    <input
                      type="text"
                      value={formData.secondary_subscriber_name || ''}
                      onChange={(e) => updateFormData({ secondary_subscriber_name: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md"
                      placeholder="Subscriber name"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Relationship to Patient
                    </label>
                    <Select
                      value={formData.secondary_subscriber_relationship || ''}
                      onChange={(value) => updateFormData({ secondary_subscriber_relationship: value })}
                      options={[
                        { value: 'spouse', label: 'Spouse' },
                        { value: 'parent', label: 'Parent' },
                        { value: 'child', label: 'Child' },
                        { value: 'other', label: 'Other' },
                      ]}
                      placeholder="Select relationship"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Subscriber DOB
                    </label>
                    <input
                      type="date"
                      value={formData.secondary_subscriber_dob || ''}
                      onChange={(e) => updateFormData({ secondary_subscriber_dob: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md"
                    />
                  </div>
                </>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
}

export default Step2PatientInsurance;