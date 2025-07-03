import { FiClock, FiFileText, FiCheckCircle, FiArrowRight } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface Step8Props {
  formData: any;
  updateFormData: (data: any) => void;
  onNext: () => void;
}

export default function Step8OrderFormPending({
  formData,
  updateFormData,
  onNext
}: Step8Props) {
  // Theme context with fallback
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }

  // Check IVR status
  const ivrCompleted = !!formData.docuseal_submission_id && formData.docuseal_submission_id !== 'NO_IVR_REQUIRED';
  const ivrApproved = formData.ivr_status === 'approved';
  const orderFormSent = formData.order_form_status === 'sent';
  const orderFormSigned = formData.order_form_status === 'signed';

  const getStatusDisplay = () => {
    if (orderFormSigned) {
      return {
        icon: FiCheckCircle,
        iconColor: 'text-green-500',
        title: 'Order Form Completed',
        message: 'Your order form has been signed and is under final review.',
        showNext: true
      };
    }

    if (orderFormSent) {
      return {
        icon: FiFileText,
        iconColor: 'text-blue-500',
        title: 'Order Form Sent',
        message: 'The order form has been sent to you. Please check your email to complete it.',
        showNext: false
      };
    }

    if (ivrApproved) {
      return {
        icon: FiClock,
        iconColor: 'text-yellow-500',
        title: 'Awaiting Order Form',
        message: 'Your IVR has been approved. The admin will send you the order form shortly.',
        showNext: false
      };
    }

    if (ivrCompleted) {
      return {
        icon: FiClock,
        iconColor: 'text-blue-500',
        title: 'IVR Under Review',
        message: 'Your IVR form is being reviewed. Once approved, you will receive the order form.',
        showNext: false
      };
    }

    return {
      icon: FiFileText,
      iconColor: 'text-gray-500',
      title: 'Pending IVR Completion',
      message: 'Please complete the IVR form first before proceeding to the order form.',
      showNext: false
    };
  };

  const status = getStatusDisplay();
  const IconComponent = status.icon;

  return (
    <div className="space-y-6">
      {/* Title */}
      <div className="mb-6">
        <h2 className={cn("text-xl font-semibold", t.text.primary)}>
          Order Form Status
        </h2>
        <p className={cn("text-sm mt-1", t.text.secondary)}>
          Track the status of your order form submission
        </p>
      </div>

      {/* Status Card */}
      <div className={cn("text-center py-12", t.glass.card, "rounded-lg p-8")}>
        <IconComponent className={cn("h-16 w-16 mx-auto mb-4", status.iconColor)} />
        <h3 className={cn("text-xl font-medium mb-2", t.text.primary)}>
          {status.title}
        </h3>
        <p className={cn("text-sm max-w-md mx-auto", t.text.secondary)}>
          {status.message}
        </p>

        {/* Timeline */}
        <div className="mt-8 max-w-md mx-auto">
          <div className="space-y-4">
            {/* IVR Step */}
            <div className="flex items-center">
              <div className={cn(
                "w-8 h-8 rounded-full flex items-center justify-center",
                ivrCompleted 
                  ? theme === 'dark' ? 'bg-green-900/30 text-green-400' : 'bg-green-100 text-green-600'
                  : theme === 'dark' ? 'bg-gray-800 text-gray-400' : 'bg-gray-200 text-gray-600'
              )}>
                <FiCheckCircle className="h-5 w-5" />
              </div>
              <div className="ml-4 flex-1 text-left">
                <p className={cn("text-sm font-medium", t.text.primary)}>
                  IVR Form {ivrCompleted ? 'Submitted' : 'Pending'}
                </p>
                {ivrCompleted && (
                  <p className={cn("text-xs", t.text.secondary)}>
                    {formData.ivr_completed_at ? new Date(formData.ivr_completed_at).toLocaleDateString() : 'Completed'}
                  </p>
                )}
              </div>
            </div>

            {/* Connecting Line */}
            <div className="ml-4 h-8 w-0.5 bg-gray-300 dark:bg-gray-700"></div>

            {/* Admin Review Step */}
            <div className="flex items-center">
              <div className={cn(
                "w-8 h-8 rounded-full flex items-center justify-center",
                ivrApproved
                  ? theme === 'dark' ? 'bg-green-900/30 text-green-400' : 'bg-green-100 text-green-600'
                  : ivrCompleted
                  ? theme === 'dark' ? 'bg-blue-900/30 text-blue-400' : 'bg-blue-100 text-blue-600'
                  : theme === 'dark' ? 'bg-gray-800 text-gray-400' : 'bg-gray-200 text-gray-600'
              )}>
                <FiClock className="h-5 w-5" />
              </div>
              <div className="ml-4 flex-1 text-left">
                <p className={cn("text-sm font-medium", t.text.primary)}>
                  Admin Review {ivrApproved ? 'Approved' : ivrCompleted ? 'In Progress' : 'Waiting'}
                </p>
              </div>
            </div>

            {/* Connecting Line */}
            <div className="ml-4 h-8 w-0.5 bg-gray-300 dark:bg-gray-700"></div>

            {/* Order Form Step */}
            <div className="flex items-center">
              <div className={cn(
                "w-8 h-8 rounded-full flex items-center justify-center",
                orderFormSigned
                  ? theme === 'dark' ? 'bg-green-900/30 text-green-400' : 'bg-green-100 text-green-600'
                  : orderFormSent
                  ? theme === 'dark' ? 'bg-blue-900/30 text-blue-400' : 'bg-blue-100 text-blue-600'
                  : theme === 'dark' ? 'bg-gray-800 text-gray-400' : 'bg-gray-200 text-gray-600'
              )}>
                <FiFileText className="h-5 w-5" />
              </div>
              <div className="ml-4 flex-1 text-left">
                <p className={cn("text-sm font-medium", t.text.primary)}>
                  Order Form {orderFormSigned ? 'Signed' : orderFormSent ? 'Sent' : 'Pending'}
                </p>
                {orderFormSigned && formData.order_form_completed_at && (
                  <p className={cn("text-xs", t.text.secondary)}>
                    {new Date(formData.order_form_completed_at).toLocaleDateString()}
                  </p>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Next Button */}
        {status.showNext && (
          <button
            onClick={onNext}
            className={cn(
              "mt-8 inline-flex items-center px-6 py-3 text-sm font-medium rounded-lg transition-colors",
              theme === 'dark' 
                ? 'bg-blue-700 hover:bg-blue-600 text-white' 
                : 'bg-blue-600 hover:bg-blue-700 text-white'
            )}
          >
            Continue to Final Review
            <FiArrowRight className="ml-2 h-4 w-4" />
          </button>
        )}
      </div>

      {/* Info Box */}
      <div className={cn(
        "p-4 rounded-lg border",
        theme === 'dark' ? 'bg-blue-900/20 border-blue-800' : 'bg-blue-50 border-blue-200'
      )}>
        <div className="flex items-start">
          <FiClock className={cn(
            "h-5 w-5 mt-0.5 flex-shrink-0 mr-3",
            theme === 'dark' ? 'text-blue-400' : 'text-blue-600'
          )} />
          <div className="flex-1">
            <h4 className={cn(
              "text-sm font-medium",
              theme === 'dark' ? 'text-blue-300' : 'text-blue-900'
            )}>
              New Workflow Process
            </h4>
            <p className={cn(
              "text-sm mt-1",
              theme === 'dark' ? 'text-blue-400' : 'text-blue-700'
            )}>
              Order forms are now sent after IVR approval to ensure accurate information and compliance.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}