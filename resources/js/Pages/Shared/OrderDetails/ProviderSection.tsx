import React from 'react';
import { ChevronDown, ChevronUp, User, Building, Phone, Mail, MapPin, CreditCard } from 'lucide-react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface ProviderSectionProps {
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
    clinical: any;
    provider: {
      id?: number;
      name?: string;
      npi?: string;
      email?: string;
      phone?: string;
      specialty?: string;
      savedAt?: string;
    };
    facility: {
      id?: number;
      name?: string;
      address?: string;
      phone?: string;
      fax?: string;
      email?: string;
      npi?: string;
      taxId?: string;
      savedAt?: string;
    };
    submission: {
      documents: any[];
    };
  };
  isOpen: boolean;
  onToggle: () => void;
}

const ProviderSection: React.FC<ProviderSectionProps> = ({
  orderData,
  isOpen,
  onToggle
}) => {
  const { theme } = useTheme();
  const colors = themes[theme];

  const renderField = (label: string, value: string | number | undefined, className?: string) => {
    return (
      <div className={cn("flex justify-between items-center py-2", className)}>
        <span className="font-medium text-gray-700">{label}:</span>
        <span className="text-gray-900">{value || 'N/A'}</span>
      </div>
    );
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
          <User className="w-5 h-5 text-green-600" />
          <h3 className="text-lg font-semibold text-gray-900">
            Provider & Facility Information
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
          {/* Provider and Facility Information */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Provider Information */}
            <div className="space-y-2">
              <h4 className="font-semibold text-gray-800 flex items-center gap-2">
                <User className="w-4 h-4" />
                Provider Details
              </h4>
              <div className="bg-blue-50 p-3 rounded-lg space-y-2">
                {renderField("Provider Name", orderData.provider?.name)}
                {renderField("NPI Number", orderData.provider?.npi)}
                {renderField("Specialty", orderData.provider?.specialty)}
                
                {/* Contact Information */}
                <div className="pt-2 border-t border-blue-200">
                  <div className="flex items-center gap-2 mb-2">
                    <Mail className="w-4 h-4 text-blue-600" />
                    <span className="text-sm font-medium text-blue-800">Contact Information</span>
                  </div>
                  {renderField("Email", orderData.provider?.email)}
                  {renderField("Phone", orderData.provider?.phone)}
                </div>
              </div>
            </div>

            {/* Facility Information */}
            <div className="space-y-2">
              <h4 className="font-semibold text-gray-800 flex items-center gap-2">
                <Building className="w-4 h-4" />
                Facility Details
              </h4>
              <div className="bg-green-50 p-3 rounded-lg space-y-2">
                {renderField("Facility Name", orderData.facility?.name)}
                {renderField("NPI Number", orderData.facility?.npi)}
                {renderField("Tax ID", orderData.facility?.taxId)}
                
                {/* Address Information */}
                {orderData.facility?.address && (
                  <div className="pt-2 border-t border-green-200">
                    <div className="flex items-center gap-2 mb-2">
                      <MapPin className="w-4 h-4 text-green-600" />
                      <span className="text-sm font-medium text-green-800">Address</span>
                    </div>
                    <div className="bg-white p-2 rounded text-sm">
                      <p className="text-gray-900">{orderData.facility?.address || 'N/A'}</p>
                    </div>
                  </div>
                )}
                
                {/* Contact Information */}
                <div className="pt-2 border-t border-green-200">
                  <div className="flex items-center gap-2 mb-2">
                    <Phone className="w-4 h-4 text-green-600" />
                    <span className="text-sm font-medium text-green-800">Contact Information</span>
                  </div>
                  {renderField("Email", orderData.facility?.email)}
                  {renderField("Phone", orderData.facility?.phone)}
                  {renderField("Fax", orderData.facility?.fax)}
                </div>
              </div>
            </div>
          </div>

          {/* Provider-Facility Relationship */}
          <div className="space-y-2 pt-4 border-t border-gray-200">
            <h4 className="font-semibold text-gray-800">Provider-Facility Relationship</h4>
            <div className="bg-gradient-to-r from-blue-50 to-green-50 p-4 rounded-lg">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="text-center">
                  <div className="flex items-center justify-center gap-2 mb-2">
                    <User className="w-5 h-5 text-blue-600" />
                    <span className="font-medium text-blue-800">Ordering Provider</span>
                  </div>
                  <p className="text-sm text-gray-700">{orderData.provider?.name || 'N/A'}</p>
                  <p className="text-xs text-gray-500">NPI: {orderData.provider?.npi || 'N/A'}</p>
                </div>
                <div className="text-center">
                  <div className="flex items-center justify-center gap-2 mb-2">
                    <Building className="w-5 h-5 text-green-600" />
                    <span className="font-medium text-green-800">Service Facility</span>
                  </div>
                  <p className="text-sm text-gray-700">{orderData.facility?.name || 'N/A'}</p>
                  <p className="text-xs text-gray-500">NPI: {orderData.facility?.npi || 'N/A'}</p>
                </div>
              </div>
            </div>
          </div>

          {/* Billing Information */}
          <div className="space-y-2 pt-4 border-t border-gray-200">
            <h4 className="font-semibold text-gray-800 flex items-center gap-2">
              <CreditCard className="w-4 h-4" />
              Billing Information
            </h4>
            <div className="bg-gray-50 p-3 rounded-lg">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {renderField("Billing Provider", orderData.provider?.name)}
                {renderField("Billing Facility", orderData.facility?.name)}
                {renderField("Facility Tax ID", orderData.facility?.taxId)}
                {renderField("Provider NPI", orderData.provider?.npi)}
              </div>
            </div>
          </div>

          {/* Timeline Information */}
          <div className="space-y-2 pt-4 border-t border-gray-200">
            <h4 className="font-semibold text-gray-800">Data Timeline</h4>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {orderData.provider?.savedAt && (
                <div className="bg-blue-50 p-3 rounded-lg">
                  <p className="text-sm font-medium text-blue-800">Provider Data Saved</p>
                  <p className="text-xs text-blue-600">{formatDate(orderData.provider?.savedAt)}</p>
                </div>
              )}
              {orderData.facility?.savedAt && (
                <div className="bg-green-50 p-3 rounded-lg">
                  <p className="text-sm font-medium text-green-800">Facility Data Saved</p>
                  <p className="text-xs text-green-600">{formatDate(orderData.facility?.savedAt)}</p>
                </div>
              )}
            </div>
          </div>

          {/* Verification Status */}
          <div className="space-y-2 pt-4 border-t border-gray-200">
            <h4 className="font-semibold text-gray-800">Verification Status</h4>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="bg-blue-50 p-3 rounded-lg">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium text-blue-800">Provider Verification</span>
                  <span className="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">
                    {orderData.provider?.npi ? 'Verified' : 'Pending'}
                  </span>
                </div>
              </div>
              <div className="bg-green-50 p-3 rounded-lg">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium text-green-800">Facility Verification</span>
                  <span className="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">
                    {orderData.facility?.npi ? 'Verified' : 'Pending'}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default ProviderSection; 