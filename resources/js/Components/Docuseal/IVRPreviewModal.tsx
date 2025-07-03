
import * as React from 'react';
import { X, FileText, CheckCircle, AlertCircle, Eye } from 'lucide-react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface FieldCoverage {
  total_fields: number;
  filled_fields: number;
  missing_fields: string[];
  extracted_fields: string[];
  percentage: number;
  coverage_level: 'excellent' | 'good' | 'fair' | 'poor';
}

interface IVRPreviewModalProps {
  isOpen: boolean;
  onClose: () => void;
  coverage: FieldCoverage;
  formData: any;
  manufacturerName?: string;
}

const IVRPreviewModal: React.FC<IVRPreviewModalProps> = ({
  isOpen,
  onClose,
  coverage,
  formData,
  manufacturerName = 'Selected Manufacturer'
}) => {
  const { theme } = useTheme();
  const t = themes[theme];

  if (!isOpen) return null;

  // Get manufacturer-specific field requirements
  const getManufacturerFields = () => {
    // This would ideally fetch from the manufacturer configuration
    // For now, showing a comprehensive preview of common IVR fields

    return [
      { section: 'Patient Information', fields: [
        { label: 'Patient First Name', value: formData.patient_first_name || '', filled: !!formData.patient_first_name, required: true },
        { label: 'Patient Last Name', value: formData.patient_last_name || '', filled: !!formData.patient_last_name, required: true },
        { label: 'Date of Birth', value: formData.patient_dob || '', filled: !!formData.patient_dob, required: true },
        { label: 'Gender', value: formData.patient_gender || '', filled: !!formData.patient_gender, required: false },
        { label: 'Phone', value: formData.patient_phone || '', filled: !!formData.patient_phone, required: true },
        { label: 'Email', value: formData.patient_email || '', filled: !!formData.patient_email, required: false },
        { label: 'Address', value: `${formData.patient_address_line1 || ''} ${formData.patient_city || ''} ${formData.patient_state || ''}`.trim(), filled: !!(formData.patient_address_line1 && formData.patient_city), required: true },
        { label: 'ZIP Code', value: formData.patient_zip || '', filled: !!formData.patient_zip, required: true },
      ]},
      { section: 'Insurance Information', fields: [
        { label: 'Primary Insurance Name', value: formData.primary_insurance_name || '', filled: !!formData.primary_insurance_name, required: true },
        { label: 'Member ID', value: formData.primary_member_id || '', filled: !!formData.primary_member_id, required: true },
        { label: 'Plan Type', value: formData.primary_plan_type || '', filled: !!formData.primary_plan_type, required: true },
        { label: 'Payer Phone', value: formData.primary_payer_phone || '', filled: !!formData.primary_payer_phone, required: false },
        { label: 'Group Number', value: formData.insurance_group_number || '', filled: !!formData.insurance_group_number, required: false },
        { label: 'Secondary Insurance', value: formData.secondary_insurance_name || '', filled: !!formData.secondary_insurance_name, required: false },
      ]},
      { section: 'Provider Information', fields: [
        { label: 'Provider Name', value: formData.provider_name || '', filled: !!formData.provider_name, required: true },
        { label: 'NPI Number', value: formData.provider_npi || '', filled: !!formData.provider_npi, required: true },
        { label: 'Provider Credentials', value: formData.provider_credentials || '', filled: !!formData.provider_credentials, required: false },
        { label: 'Facility Name', value: formData.facility_name || '', filled: !!formData.facility_name, required: true },
        { label: 'Facility Address', value: formData.facility_address || '', filled: !!formData.facility_address, required: true },
        { label: 'Facility NPI', value: formData.facility_npi || '', filled: !!formData.facility_npi, required: false },
      ]},
      { section: 'Clinical Information', fields: [
        { label: 'Wound Location', value: formData.wound_location || '', filled: !!formData.wound_location, required: true },
        { label: 'Wound Length (cm)', value: formData.wound_size_length || '', filled: !!formData.wound_size_length, required: true },
        { label: 'Wound Width (cm)', value: formData.wound_size_width || '', filled: !!formData.wound_size_width, required: true },
        { label: 'Wound Depth (cm)', value: formData.wound_size_depth || '', filled: !!formData.wound_size_depth, required: false },
        { label: 'Wound Duration', value: formData.wound_duration || '', filled: !!formData.wound_duration, required: true },
        { label: 'Primary Diagnosis (ICD-10)', value: formData.yellow_diagnosis_code || '', filled: !!formData.yellow_diagnosis_code, required: true },
        { label: 'Secondary Diagnosis', value: formData.orange_diagnosis_code || '', filled: !!formData.orange_diagnosis_code, required: false },
        { label: 'Previous Treatments', value: formData.previous_treatments || '', filled: !!formData.previous_treatments, required: true },
      ]},
      { section: 'Administrative', fields: [
        { label: 'Today\'s Date', value: formData.todays_date || '', filled: !!formData.todays_date, required: true },
        { label: 'Signature Date', value: formData.signature_date || '', filled: !!formData.signature_date, required: true },
        { label: 'Physician Signature', value: formData.physician_signature || '[Electronic Signature]', filled: false, required: true },
        { label: 'Patient Authorization', value: formData.patient_signature || '[Electronic Signature]', filled: false, required: true },
      ]}
    ];
  };

  const sampleIVRFields = getManufacturerFields();

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div className={cn(
        "bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden",
        "border border-gray-200 dark:border-gray-700"
      )}>
        {/* Header */}
        <div className={cn(
          "flex items-center justify-between p-6 border-b",
          "border-gray-200 dark:border-gray-700"
        )}>
          <div className="flex items-center space-x-3">
            <div className="p-2 rounded-lg bg-blue-100 dark:bg-blue-900/30">
              <FileText className="w-6 h-6 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
              <h2 className={cn("text-xl font-bold", t.text.primary)}>
                IVR Form Preview
              </h2>
              <p className={cn("text-sm", t.text.secondary)}>
                {manufacturerName} • {coverage.percentage}% Complete
              </p>
            </div>
          </div>
          <button
            title="Close Preview"
            onClick={onClose}
            className={cn(
              "p-2 rounded-lg transition-colors",
              "hover:bg-gray-100 dark:hover:bg-gray-700"
            )}
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Content */}
        <div className="p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
          {/* Coverage Summary */}
          <div className={cn(
            "p-4 rounded-lg mb-6",
            coverage.coverage_level === 'excellent' ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800' :
            coverage.coverage_level === 'good' ? 'bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800' :
            coverage.coverage_level === 'fair' ? 'bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800' :
            'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800'
          )}>
            <div className="flex items-center space-x-2 mb-2">
              {coverage.coverage_level === 'excellent' ?
                <CheckCircle className="w-5 h-5 text-green-600" /> :
                <AlertCircle className="w-5 h-5 text-yellow-600" />
              }
              <h3 className="font-semibold">
                {coverage.filled_fields} of {coverage.total_fields} fields will be pre-filled
              </h3>
            </div>
            <p className="text-sm opacity-80">
              {coverage.extracted_fields.length} fields were automatically extracted from your documents
            </p>
          </div>

          {/* Form Preview */}
          <div className="space-y-6">
            {sampleIVRFields.map((section, sectionIndex) => (
              <div key={sectionIndex} className={cn(
                "border rounded-lg p-4",
                "border-gray-200 dark:border-gray-700"
              )}>
                <h3 className={cn("font-semibold mb-3", t.text.primary)}>
                  {section.section}
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  {section.fields.map((field, fieldIndex) => (
                    <div key={fieldIndex} className={cn(
                      "p-3 rounded border",
                      field.filled
                        ? "border-green-200 dark:border-green-800 bg-green-50/50 dark:bg-green-900/10"
                        : "border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800"
                    )}>
                      <div className="flex items-center justify-between mb-1">
                        <label className={cn("text-xs font-medium", t.text.secondary)}>
                          {field.label}
                          {field.required && <span className="text-red-500 ml-1">*</span>}
                        </label>
                        {field.filled && (
                          <CheckCircle className="w-3 h-3 text-green-500" />
                        )}
                      </div>
                      <div className={cn(
                        "text-sm",
                        field.filled ? t.text.primary : "text-gray-400 dark:text-gray-500"
                      )}>
                        {field.value || `[${field.label} will be filled manually]`}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>

          {/* Missing Fields Summary */}
          {coverage.missing_fields.length > 0 && (
            <div className={cn(
              "mt-6 p-4 rounded-lg border",
              "border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-900/20"
            )}>
              <h3 className="font-semibold text-yellow-800 dark:text-yellow-300 mb-2">
                Fields Requiring Manual Entry ({coverage.missing_fields.length})
              </h3>
              <div className="grid grid-cols-2 md:grid-cols-3 gap-2 text-sm">
                {coverage.missing_fields.slice(0, 12).map((field, index) => (
                  <div key={index} className="text-yellow-700 dark:text-yellow-400">
                    • {field.replace(/_/g, ' ')}
                  </div>
                ))}
                {coverage.missing_fields.length > 12 && (
                  <div className="text-yellow-600 dark:text-yellow-500">
                    + {coverage.missing_fields.length - 12} more
                  </div>
                )}
              </div>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className={cn(
          "flex items-center justify-between p-6 border-t",
          "border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50"
        )}>
          <div className="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400">
            <Eye className="w-4 h-4" />
            <span>This preview shows how your IVR will be pre-filled</span>
          </div>
          <button
            onClick={onClose}
            className={cn(
              "px-4 py-2 rounded-lg font-medium transition-colors",
              "bg-blue-600 hover:bg-blue-700 text-white"
            )}
          >
            Close Preview
          </button>
        </div>
      </div>
    </div>
  );
};

export default IVRPreviewModal;
