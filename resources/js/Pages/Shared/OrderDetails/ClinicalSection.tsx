import React from 'react';
import { ChevronDown, ChevronUp, Activity, MapPin, Calendar, Stethoscope, FileText } from 'lucide-react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface ClinicalSectionProps {
  orderData: {
    orderNumber: string;
    createdDate: string;
    createdBy: string;
    patient: any;
    product: any;
    forms: {
      ivrStatus: string;
      orderFormStatus: string;
    };
    clinical: {
      woundType?: string;
      woundLocation?: string;
      location?: string;
      size?: string;
      depth?: string;
      diagnosisCodes?: string[];
      primaryDiagnosis?: string;
      secondaryDiagnosis?: string;
      cptCodes?: string[] | string;
      clinicalNotes?: string;
      woundDurationWeeks?: number;
      failedConservativeTreatment?: boolean;
      placeOfService?: string;
      serviceDate?: string;
      savedAt?: string;
    };
    provider: any;
    submission: {
      documents: any[];
    };
  };
  isOpen: boolean;
  onToggle: () => void;
}

const ClinicalSection: React.FC<ClinicalSectionProps> = ({
  orderData,
  isOpen,
  onToggle
}) => {
  const { theme } = useTheme();
  const colors = themes[theme];

  const renderField = (label: string, value: string | number | boolean | undefined, className?: string) => {
    let displayValue = 'N/A';
    
    if (typeof value === 'boolean') {
      displayValue = value ? 'Yes' : 'No';
    } else if (value !== undefined && value !== null && value !== '') {
      displayValue = String(value);
    }

    return (
      <div className={cn("flex justify-between items-center py-2", className)}>
        <span className="font-medium text-gray-700">{label}:</span>
        <span className="text-gray-900">{displayValue}</span>
      </div>
    );
  };

  const renderArrayField = (label: string, value: string[] | string | undefined) => {
    let displayValue = 'N/A';
    
    if (Array.isArray(value) && value.length > 0) {
      displayValue = value.filter(v => v && v.trim()).join(', ');
    } else if (typeof value === 'string' && value.trim()) {
      displayValue = value;
    }

    return renderField(label, displayValue);
  };

  const formatDate = (dateString: string | undefined) => {
    if (!dateString) return 'N/A';
    try {
      return new Date(dateString).toLocaleDateString();
    } catch {
      return dateString;
    }
  };

  return (
    <div className={cn(
      "rounded-lg border transition-all duration-200",
      colors.card,
      colors.border,
      "hover:shadow-lg"
    )}>
      <button
        onClick={onToggle}
        className={cn(
          "w-full p-4 flex items-center justify-between text-left transition-colors",
          colors.hover
        )}
      >
        <div className="flex items-center gap-3">
          <Activity className="w-5 h-5 text-red-600" />
          <h3 className="text-lg font-semibold text-gray-900">
            Clinical Information
          </h3>
        </div>
        {isOpen ? (
          <ChevronUp className="w-5 h-5 text-gray-400" />
        ) : (
          <ChevronDown className="w-5 h-5 text-gray-400" />
        )}
      </button>

      {isOpen && (
        <div className="p-4 pt-0 space-y-4">
          {/* Wound Information */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="space-y-2">
              <h4 className="font-semibold text-gray-800 flex items-center gap-2">
                <Activity className="w-4 h-4" />
                Wound Details
              </h4>
                              {renderField("Wound Type", orderData.clinical?.woundType)}
                {renderField("Wound Location", orderData.clinical?.woundLocation || orderData.clinical?.location)}
                {renderField("Wound Size", orderData.clinical?.size)}
                {renderField("Wound Depth", orderData.clinical?.depth)}
                {renderField("Duration (Weeks)", orderData.clinical?.woundDurationWeeks)}
            </div>

            <div className="space-y-2">
              <h4 className="font-semibold text-gray-800 flex items-center gap-2">
                <MapPin className="w-4 h-4" />
                Service Information
              </h4>
                              {renderField("Place of Service", orderData.clinical?.placeOfService)}
                {renderField("Service Date", formatDate(orderData.clinical?.serviceDate))}
                {renderField("Failed Conservative Treatment", orderData.clinical?.failedConservativeTreatment)}
            </div>
          </div>

          {/* Diagnosis Information */}
          <div className="space-y-2 pt-4 border-t border-gray-200">
            <h4 className="font-semibold text-gray-800 flex items-center gap-2">
              <Stethoscope className="w-4 h-4" />
              Diagnosis & Treatment Codes
            </h4>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                              {renderField("Primary Diagnosis", orderData.clinical?.primaryDiagnosis)}
                {renderField("Secondary Diagnosis", orderData.clinical?.secondaryDiagnosis)}
                {renderArrayField("Diagnosis Codes", orderData.clinical?.diagnosisCodes)}
                {renderArrayField("CPT Codes", orderData.clinical?.cptCodes)}
            </div>
          </div>

          {/* Clinical Notes */}
          {orderData.clinical?.clinicalNotes && (
            <div className="space-y-2 pt-4 border-t border-gray-200">
              <h4 className="font-semibold text-gray-800 flex items-center gap-2">
                <FileText className="w-4 h-4" />
                Clinical Notes
              </h4>
              <div className="bg-gray-50 p-3 rounded-lg">
                <p className="text-sm text-gray-700 whitespace-pre-wrap">{orderData.clinical?.clinicalNotes}</p>
              </div>
            </div>
          )}

          {/* Provider Information */}
          <div className="space-y-2 pt-4 border-t border-gray-200">
            <h4 className="font-semibold text-gray-800 flex items-center gap-2">
              <Stethoscope className="w-4 h-4" />
              Ordering Provider
            </h4>
            <div className="bg-blue-50 p-3 rounded-lg">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {renderField("Provider Name", orderData.provider?.name)}
                {renderField("NPI", orderData.provider?.npi)}
                {renderField("Email", orderData.provider?.email)}
                {renderField("Phone", orderData.provider?.phone)}
                {renderField("Specialty", orderData.provider?.specialty)}
              </div>
            </div>
          </div>

          {/* Timeline Information */}
          {orderData.clinical?.savedAt && (
            <div className="space-y-2 pt-4 border-t border-gray-200">
              <h4 className="font-semibold text-gray-800 flex items-center gap-2">
                <Calendar className="w-4 h-4" />
                Timeline
              </h4>
              <div className="bg-gray-50 p-3 rounded-lg">
                <div className="flex items-center justify-between">
                  <span className="text-sm text-gray-600">Clinical Data Saved:</span>
                  <span className="text-sm font-medium text-gray-900">
                    {formatDate(orderData.clinical?.savedAt)}
                  </span>
                </div>
              </div>
            </div>
          )}

          {/* Treatment Summary */}
          <div className="space-y-2 pt-4 border-t border-gray-200">
            <h4 className="font-semibold text-gray-800">Treatment Summary</h4>
            <div className="bg-gradient-to-r from-blue-50 to-purple-50 p-4 rounded-lg">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                <div>
                  <p className="text-sm text-gray-600">Wound Type</p>
                  <p className="font-semibold text-gray-900">{orderData.clinical?.woundType || 'N/A'}</p>
                </div>
                <div>
                  <p className="text-sm text-gray-600">Treatment Location</p>
                  <p className="font-semibold text-gray-900">{orderData.clinical?.woundLocation || orderData.clinical?.location || 'N/A'}</p>
                </div>
                <div>
                  <p className="text-sm text-gray-600">Conservative Treatment</p>
                  <p className="font-semibold text-gray-900">
                    {orderData.clinical?.failedConservativeTreatment ? 'Failed' : 'Not Failed'}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default ClinicalSection; 