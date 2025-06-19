import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Send,
  Mail,
  Plus,
  X,
  User,
  Building2,
  CheckCircle,
  AlertCircle,
  Package,
  FileText,
  Phone,
  Globe
} from 'lucide-react';
import { themes } from '@/theme/glass-theme';
import { useTheme } from '@/contexts/ThemeContext';

interface SendToManufacturerProps {
  order?: any;
  episode?: any;
  manufacturer?: {
    id: number;
    name: string;
    contact_email?: string;
    contact_phone?: string;
  };
}

const SendToManufacturer: React.FC<SendToManufacturerProps> = ({
  order,
  episode,
  manufacturer
}) => {
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme
  }

  const [emailRecipients, setEmailRecipients] = useState<string[]>([]);
  const [newRecipient, setNewRecipient] = useState('');
  const [showAddRecipient, setShowAddRecipient] = useState(false);
  const [notes, setNotes] = useState('');
  const [isSending, setIsSending] = useState(false);
  const [emailError, setEmailError] = useState('');

  // Initialize with manufacturer's default email
  useEffect(() => {
    const mfg = manufacturer || order?.manufacturer || episode?.manufacturer;
    if (mfg?.contact_email && !emailRecipients.includes(mfg.contact_email)) {
      setEmailRecipients([mfg.contact_email]);
    }
  }, [manufacturer, order, episode]);

  const validateEmail = (email: string): boolean => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  };

  const handleAddRecipient = () => {
    if (!newRecipient.trim()) {
      setEmailError('Please enter an email address');
      return;
    }

    if (!validateEmail(newRecipient)) {
      setEmailError('Please enter a valid email address');
      return;
    }

    if (emailRecipients.includes(newRecipient)) {
      setEmailError('This email is already in the list');
      return;
    }

    setEmailRecipients([...emailRecipients, newRecipient]);
    setNewRecipient('');
    setEmailError('');
    setShowAddRecipient(false);
  };

  const handleRemoveRecipient = (email: string) => {
    setEmailRecipients(emailRecipients.filter(r => r !== email));
  };

  const handleSendToManufacturer = () => {
    if (emailRecipients.length === 0) {
      setEmailError('Please add at least one recipient');
      return;
    }

    setIsSending(true);

    const endpoint = episode
      ? route('admin.episodes.send-to-manufacturer', episode.id)
      : route('admin.orders.send-to-manufacturer', order.id);

    router.post(endpoint, {
      recipients: emailRecipients,
      notes: notes,
      include_ivr: true,
      include_clinical_notes: true
    }, {
      onSuccess: () => {
        setIsSending(false);
      },
      onError: () => {
        setIsSending(false);
        setEmailError('Failed to send to manufacturer. Please try again.');
      }
    });
  };

  const mfg = manufacturer || order?.manufacturer || episode?.manufacturer;
  const canSend = episode ? episode.status === 'ivr_verified' : order?.order_status === 'approved';

  if (!canSend) {
    return null;
  }

  return (
    <div className={`${t.glass.card} ${t.glass.border} rounded-xl p-6`}>
      <div className="flex items-center justify-between mb-6">
        <h3 className={`text-lg font-semibold ${t.text.primary} flex items-center gap-2`}>
          <Send className="w-5 h-5 text-green-500" />
          Send to Manufacturer
        </h3>
        {mfg && (
          <div className="flex items-center gap-2">
            <Building2 className="w-4 h-4 text-gray-400" />
            <span className={`text-sm ${t.text.secondary}`}>{mfg.name}</span>
          </div>
        )}
      </div>

      {/* Manufacturer Contact Info */}
      {mfg && (
        <div className={`${t.glass.frost} rounded-lg p-4 mb-4`}>
          <div className="flex items-start gap-3">
            <Building2 className="w-5 h-5 text-blue-500 mt-0.5" />
            <div className="flex-1">
              <h4 className={`font-medium ${t.text.primary}`}>{mfg.name}</h4>
              <div className="mt-2 space-y-1">
                {mfg.contact_email && (
                  <div className="flex items-center gap-2">
                    <Mail className="w-4 h-4 text-gray-400" />
                    <span className={`text-sm ${t.text.secondary}`}>{mfg.contact_email}</span>
                  </div>
                )}
                {mfg.contact_phone && (
                  <div className="flex items-center gap-2">
                    <Phone className="w-4 h-4 text-gray-400" />
                    <span className={`text-sm ${t.text.secondary}`}>{mfg.contact_phone}</span>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Email Recipients Section */}
      <div className="space-y-4">
        <div>
          <label className={`block text-sm font-medium ${t.text.primary} mb-2`}>
            Email Recipients
          </label>

          {/* Recipients List */}
          <div className="space-y-2 mb-3">
            <AnimatePresence>
              {emailRecipients.map((email, idx) => (
                <motion.div
                  key={email}
                  initial={{ opacity: 0, x: -20 }}
                  animate={{ opacity: 1, x: 0 }}
                  exit={{ opacity: 0, x: 20 }}
                  className={`flex items-center justify-between ${t.glass.frost} rounded-lg px-3 py-2`}
                >
                  <div className="flex items-center gap-2">
                    <User className="w-4 h-4 text-gray-400" />
                    <span className={`text-sm ${t.text.primary}`}>{email}</span>
                    {idx === 0 && mfg?.contact_email === email && (
                      <span className="text-xs bg-blue-500/20 text-blue-500 px-2 py-0.5 rounded">Default</span>
                    )}
                  </div>
                  <button
                    onClick={() => handleRemoveRecipient(email)}
                    className="p-1 hover:bg-red-500/10 rounded transition-colors"
                    title="Remove recipient"
                  >
                    <X className="w-4 h-4 text-red-500" />
                  </button>
                </motion.div>
              ))}
            </AnimatePresence>
          </div>

          {/* Add Recipient */}
          {showAddRecipient ? (
            <motion.div
              initial={{ opacity: 0, y: -10 }}
              animate={{ opacity: 1, y: 0 }}
              className="flex gap-2"
            >
              <input
                type="email"
                value={newRecipient}
                onChange={(e) => {
                  setNewRecipient(e.target.value);
                  setEmailError('');
                }}
                onKeyPress={(e) => e.key === 'Enter' && handleAddRecipient()}
                placeholder="Enter email address..."
                className={`flex-1 px-3 py-2 ${t.glass.input} ${t.glass.border} rounded-lg text-sm`}
                autoFocus
              />
              <button
                onClick={handleAddRecipient}
                className={`px-3 py-2 ${t.button.primary} rounded-lg text-sm`}
              >
                Add
              </button>
              <button
                onClick={() => {
                  setShowAddRecipient(false);
                  setNewRecipient('');
                  setEmailError('');
                }}
                className={`px-3 py-2 ${t.button.ghost} rounded-lg text-sm`}
              >
                Cancel
              </button>
            </motion.div>
          ) : (
            <button
              onClick={() => setShowAddRecipient(true)}
              className={`w-full flex items-center justify-center gap-2 px-3 py-2 ${t.button.ghost} rounded-lg text-sm`}
            >
              <Plus className="w-4 h-4" />
              Add Recipient
            </button>
          )}

          {/* Error Message */}
          {emailError && (
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              className="mt-2 flex items-center gap-2 text-red-500 text-sm"
            >
              <AlertCircle className="w-4 h-4" />
              {emailError}
            </motion.div>
          )}
        </div>

        {/* Additional Notes */}
        <div>
          <label className={`block text-sm font-medium ${t.text.primary} mb-2`}>
            Additional Notes (Optional)
          </label>
          <textarea
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            rows={3}
            placeholder="Any special instructions for the manufacturer..."
            className={`w-full px-3 py-2 ${t.glass.input} ${t.glass.border} rounded-lg text-sm`}
          />
        </div>

        {/* What Will Be Sent */}
        <div className={`${t.glass.frost} rounded-lg p-4`}>
          <h4 className={`text-sm font-medium ${t.text.primary} mb-2`}>What will be sent:</h4>
          <div className="space-y-1.5">
            <div className="flex items-center gap-2">
              <CheckCircle className="w-4 h-4 text-green-500" />
              <span className={`text-sm ${t.text.secondary}`}>
                {episode ? `All orders in this episode (${episode.orders_count} orders)` : 'Order details and products'}
              </span>
            </div>
            <div className="flex items-center gap-2">
              <CheckCircle className="w-4 h-4 text-green-500" />
              <span className={`text-sm ${t.text.secondary}`}>IVR confirmation document</span>
            </div>
            <div className="flex items-center gap-2">
              <CheckCircle className="w-4 h-4 text-green-500" />
              <span className={`text-sm ${t.text.secondary}`}>Provider and facility information</span>
            </div>
            <div className="flex items-center gap-2">
              <CheckCircle className="w-4 h-4 text-green-500" />
              <span className={`text-sm ${t.text.secondary}`}>Clinical notes and wound details</span>
            </div>
          </div>
        </div>

        {/* Send Button */}
        <button
          onClick={handleSendToManufacturer}
          disabled={isSending || emailRecipients.length === 0}
          className={`w-full flex items-center justify-center gap-2 px-4 py-3 ${
            isSending || emailRecipients.length === 0
              ? 'bg-gray-300 dark:bg-gray-700 cursor-not-allowed'
              : t.button.primary
          } rounded-lg font-medium transition-all`}
        >
          {isSending ? (
            <>
              <div className="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent" />
              Sending...
            </>
          ) : (
            <>
              <Send className="w-5 h-5" />
              Send to Manufacturer
            </>
          )}
        </button>
      </div>
    </div>
  );
};

export default SendToManufacturer;
