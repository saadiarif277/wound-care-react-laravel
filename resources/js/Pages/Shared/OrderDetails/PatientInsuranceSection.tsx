import React from 'react';
import { ChevronDown, ChevronUp, User, Calendar, Phone, MapPin, Shield, CreditCard } from 'lucide-react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface PatientInsuranceSectionProps {
  orderData: {
    orderNumber: string;
    createdDate: string;
    createdBy: string;
    patient: {
      name: string;
      display_id: string;
      dob: string;
      gender: string;
      phone: string;
      email: string;
      address: string;
    };
    insurance: {
      primary_name: string;
      primary_member_id: string;
      secondary_name?: string;
      secondary_member_id?: string;
      has_secondary?: boolean;
    };
    forms: {
      ivrStatus: string;
      orderFormStatus: string;
    };
    submission: {
      documents: any[];
    };
  };
  isOpen: boolean;
  onToggle: () => void;
}

const PatientInsuranceSection: React.FC<PatientInsuranceSectionProps> = ({
  orderData,
  isOpen,
  onToggle
}) => {
  const { theme } = useTheme();
  const t = themes[theme];

  const formatDate = (dateString: string) => {
    if (!dateString) return 'N/A';
    try {
      return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    } catch (error) {
      return dateString;
    }
  };

  const formatPhone = (phone: string) => {
    if (!phone) return 'N/A';
    // Basic phone number formatting
    const cleaned = phone.replace(/\D/g, '');
    if (cleaned.length === 10) {
      return `(${cleaned.slice(0, 3)}) ${cleaned.slice(3, 6)}-${cleaned.slice(6)}`;
    }
    return phone;
  };

  const getStatusColor = (status: string) => {
    switch (status?.toLowerCase()) {
      case 'completed':
      case 'approved':
        return 'bg-green-100 text-green-800';
      case 'pending':
      case 'in_progress':
        return 'bg-yellow-100 text-yellow-800';
      case 'rejected':
      case 'failed':
        return 'bg-red-100 text-red-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  return (
    <div className={cn(
      "rounded-2xl transition-all duration-300",
      t.glass.card,
      t.glass.border,
      t.shadows.glass
    )}>
      {/* Section Header */}
      <div 
        className={cn(
          "flex items-center justify-between p-6 cursor-pointer",
          t.glass.hover
        )}
        onClick={onToggle}
      >
        <div className="flex items-center gap-3">
          <div className={cn(
            "p-2 rounded-lg",
            theme === 'dark' ? 'bg-blue-500/20 text-blue-400' : 'bg-blue-50 text-blue-600'
          )}>
            <User className="h-5 w-5" />
          </div>
          <div>
            <h3 className={cn("text-lg font-semibold", t.text.primary)}>
              Patient & Insurance Information
            </h3>
            <p className={cn("text-sm", t.text.secondary)}>
              Patient details and insurance coverage
            </p>
          </div>
        </div>
        <button className={cn("p-2 rounded-lg transition-colors", t.glass.hover)}>
          {isOpen ? (
            <ChevronUp className="h-5 w-5" />
          ) : (
            <ChevronDown className="h-5 w-5" />
          )}
        </button>
      </div>

      {/* Section Content */}
      {isOpen && (
        <div className="px-6 pb-6">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Patient Information */}
            <div className={cn(
              "p-4 rounded-xl",
              theme === 'dark' ? 'bg-white/5 border border-white/10' : 'bg-gray-50 border border-gray-200'
            )}>
              <h4 className={cn("text-md font-semibold mb-4 flex items-center gap-2", t.text.primary)}>
                <User className="h-4 w-4" />
                Patient Information
              </h4>
              
              <div className="space-y-3">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label className={cn("text-sm font-medium", t.text.secondary)}>
                      Full Name
                    </label>
                    <p className={cn("text-sm font-semibold", t.text.primary)}>
                      {orderData.patient?.name || 'N/A'}
                    </p>
                  </div>
                  <div>
                    <label className={cn("text-sm font-medium", t.text.secondary)}>
                      Patient ID
                    </label>
                    <p className={cn("text-sm font-semibold", t.text.primary)}>
                      {orderData.patient?.display_id || 'N/A'}
                    </p>
                  </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label className={cn("text-sm font-medium flex items-center gap-1", t.text.secondary)}>
                      <Calendar className="h-3 w-3" />
                      Date of Birth
                    </label>
                    <p className={cn("text-sm font-semibold", t.text.primary)}>
                      {formatDate(orderData.patient?.dob)}
                    </p>
                  </div>
                  <div>
                    <label className={cn("text-sm font-medium", t.text.secondary)}>
                      Gender
                    </label>
                    <p className={cn("text-sm font-semibold", t.text.primary)}>
                      {orderData.patient?.gender || 'N/A'}
                    </p>
                  </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label className={cn("text-sm font-medium flex items-center gap-1", t.text.secondary)}>
                      <Phone className="h-3 w-3" />
                      Phone
                    </label>
                    <p className={cn("text-sm font-semibold", t.text.primary)}>
                      {formatPhone(orderData.patient?.phone)}
                    </p>
                  </div>
                  <div>
                    <label className={cn("text-sm font-medium", t.text.secondary)}>
                      Email
                    </label>
                    <p className={cn("text-sm font-semibold", t.text.primary)}>
                      {orderData.patient?.email || 'N/A'}
                    </p>
                  </div>
                </div>

                <div>
                  <label className={cn("text-sm font-medium flex items-center gap-1", t.text.secondary)}>
                    <MapPin className="h-3 w-3" />
                    Address
                  </label>
                  <p className={cn("text-sm font-semibold", t.text.primary)}>
                    {orderData.patient?.address || 'N/A'}
                  </p>
                </div>
              </div>
            </div>

            {/* Insurance Information */}
            <div className={cn(
              "p-4 rounded-xl",
              theme === 'dark' ? 'bg-white/5 border border-white/10' : 'bg-gray-50 border border-gray-200'
            )}>
              <h4 className={cn("text-md font-semibold mb-4 flex items-center gap-2", t.text.primary)}>
                <Shield className="h-4 w-4" />
                Insurance Information
              </h4>
              
              <div className="space-y-4">
                {/* Primary Insurance */}
                <div>
                  <label className={cn("text-sm font-medium flex items-center gap-1", t.text.secondary)}>
                    <CreditCard className="h-3 w-3" />
                    Primary Insurance
                  </label>
                  <div className="mt-1 space-y-1">
                    <p className={cn("text-sm font-semibold", t.text.primary)}>
                      {orderData.insurance?.primary_name || 'N/A'}
                    </p>
                    {orderData.insurance?.primary_member_id && (
                      <p className={cn("text-xs", t.text.secondary)}>
                        Member ID: {orderData.insurance?.primary_member_id || 'N/A'}
                      </p>
                    )}
                  </div>
                </div>

                {/* Secondary Insurance */}
                <div>
                  <label className={cn("text-sm font-medium flex items-center gap-1", t.text.secondary)}>
                    <CreditCard className="h-3 w-3" />
                    Secondary Insurance
                  </label>
                  <div className="mt-1 space-y-1">
                    {orderData.insurance?.has_secondary ? (
                      <>
                        <p className={cn("text-sm font-semibold", t.text.primary)}>
                          {orderData.insurance?.secondary_name || 'N/A'}
                        </p>
                        {orderData.insurance?.secondary_member_id && (
                          <p className={cn("text-xs", t.text.secondary)}>
                            Member ID: {orderData.insurance?.secondary_member_id || 'N/A'}
                          </p>
                        )}
                      </>
                    ) : (
                      <p className={cn("text-sm", t.text.secondary)}>
                        No secondary insurance
                      </p>
                    )}
                  </div>
                </div>

                {/* Form Status */}
                <div className="pt-2 border-t border-gray-200/50">
                  <label className={cn("text-sm font-medium", t.text.secondary)}>
                    Form Status
                  </label>
                  <div className="mt-2 flex flex-col gap-2">
                    <div className="flex items-center justify-between">
                      <span className={cn("text-xs", t.text.secondary)}>IVR Status</span>
                      <span className={cn(
                        "px-2 py-1 text-xs font-medium rounded-full",
                        getStatusColor(orderData.forms?.ivrStatus)
                      )}>
                        {orderData.forms?.ivrStatus || 'N/A'}
                      </span>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className={cn("text-xs", t.text.secondary)}>Order Form Status</span>
                      <span className={cn(
                        "px-2 py-1 text-xs font-medium rounded-full",
                        getStatusColor(orderData.forms?.orderFormStatus)
                      )}>
                        {orderData.forms?.orderFormStatus || 'N/A'}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default PatientInsuranceSection; 