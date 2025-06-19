import React, { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { 
  TruckIcon, 
  MapPinIcon, 
  ClockIcon,
  CheckCircleIcon,
  PencilIcon,
  ArrowPathIcon,
  BellIcon,
  SignalIcon,
  MicrophoneIcon,
  SparklesIcon
} from '@heroicons/react/24/outline';
import { themes } from '@/theme/glass-theme';
import { useTheme } from '@/contexts/ThemeContext';
import { useForm } from '@inertiajs/react';

interface TrackingManagerProps {
  order: {
    id: string;
    tracking_number?: string;
    tracking_carrier?: string;
    tracking_status?: string;
    estimated_delivery?: string;
    delivery_updates?: Array<{
      timestamp: string;
      status: string;
      location?: string;
    }>;
  };
  readOnly?: boolean;
}

const carrierOptions = [
  { value: 'ups', label: 'UPS', color: 'brown' },
  { value: 'fedex', label: 'FedEx', color: 'purple' },
  { value: 'usps', label: 'USPS', color: 'blue' },
  { value: 'dhl', label: 'DHL', color: 'yellow' },
  { value: 'other', label: 'Other', color: 'gray' }
];

const statusSteps = [
  { status: 'pending', label: 'Pending', icon: ClockIcon },
  { status: 'shipped', label: 'Shipped', icon: TruckIcon },
  { status: 'in_transit', label: 'In Transit', icon: MapPinIcon },
  { status: 'delivered', label: 'Delivered', icon: CheckCircleIcon }
];

const TrackingManager = ({ order, readOnly = false }: TrackingManagerProps) => {
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme
  }

  if (!order) return null;

  const [isEditing, setIsEditing] = useState(false);
  const [showRealTimeUpdate, setShowRealTimeUpdate] = useState(false);
  const [voiceEnabled, setVoiceEnabled] = useState(false);

  const { data, setData, put, processing } = useForm({
    tracking_number: order.tracking_number || '',
    tracking_carrier: order.tracking_carrier || '',
    tracking_status: order.tracking_status || 'pending'
  });

  // Simulate real-time tracking updates
  useEffect(() => {
    if (order.tracking_number && !readOnly) {
      const timer = setTimeout(() => {
        setShowRealTimeUpdate(true);
      }, 3000);
      return () => clearTimeout(timer);
    }
  }, [order.tracking_number, readOnly]);

  const handleVoiceUpdate = () => {
    if ('speechSynthesis' in window && order.tracking_number) {
      const statusText = order.tracking_status === 'delivered' ? 'has been delivered' :
                        order.tracking_status === 'in_transit' ? 'is in transit' :
                        order.tracking_status === 'shipped' ? 'has been shipped' :
                        'is pending shipment';
      
      const utterance = new SpeechSynthesisUtterance(
        `Order ${order.id} ${statusText}. Tracking number: ${order.tracking_number}`
      );
      speechSynthesis.speak(utterance);
    }
  };

  const handleSubmit = () => {
    put(route('orders.tracking.update', order.id), {
      onSuccess: () => {
        setIsEditing(false);
      }
    });
  };

  const getCurrentStepIndex = () => {
    return statusSteps.findIndex(step => step.status === (order.tracking_status || 'pending'));
  };

  const currentStepIndex = getCurrentStepIndex();

  return (
    <div className={`${t.glass.card} ${t.glass.border} rounded-2xl p-6`}>
      {/* Header with Voice Control */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <TruckIcon className="w-5 h-5 text-blue-500" />
          <h3 className={`${t.text.heading} text-lg font-semibold`}>Tracking Information</h3>
          {!readOnly && !isEditing && (
            <button
              onClick={() => setIsEditing(true)}
              className="p-1.5 rounded-lg hover:bg-white/5 transition-colors"
            >
              <PencilIcon className="w-4 h-4 text-gray-400" />
            </button>
          )}
        </div>
        <div className="flex items-center gap-2">
          {/* Real-time Status Indicator */}
          <motion.div
            animate={{ opacity: [0.5, 1, 0.5] }}
            transition={{ duration: 2, repeat: Infinity }}
            className="flex items-center gap-2"
          >
            <SignalIcon className="w-4 h-4 text-green-500" />
            <span className="text-xs text-green-500">Live</span>
          </motion.div>
          
          {/* Voice Control */}
          <button
            onClick={() => {
              setVoiceEnabled(!voiceEnabled);
              if (!voiceEnabled) handleVoiceUpdate();
            }}
            className={`p-2 rounded-lg transition-all ${
              voiceEnabled 
                ? 'bg-purple-500/20 text-purple-600 dark:text-purple-400' 
                : 'hover:bg-white/5'
            }`}
            aria-label="Toggle voice updates"
          >
            <MicrophoneIcon className="w-5 h-5" />
          </button>
        </div>
      </div>

      {/* Tracking Progress Bar */}
      <div className="mb-6">
        <div className="relative">
          <div className="absolute inset-0 flex items-center">
            <div className="w-full h-1 bg-gray-200 dark:bg-gray-700 rounded-full">
              <motion.div
                initial={{ width: 0 }}
                animate={{ width: `${(currentStepIndex + 1) / statusSteps.length * 100}%` }}
                transition={{ duration: 0.5 }}
                className="h-full bg-gradient-to-r from-blue-500 to-purple-500 rounded-full"
              />
            </div>
          </div>
          <div className="relative flex justify-between">
            {statusSteps.map((step, index) => {
              const Icon = step.icon;
              const isCompleted = index <= currentStepIndex;
              const isCurrent = index === currentStepIndex;
              
              return (
                <motion.div
                  key={step.status}
                  initial={{ scale: 0 }}
                  animate={{ scale: 1 }}
                  transition={{ delay: index * 0.1 }}
                  className="flex flex-col items-center"
                >
                  <div
                    className={`w-10 h-10 rounded-full flex items-center justify-center transition-all ${
                      isCompleted
                        ? isCurrent
                          ? 'bg-gradient-to-r from-blue-500 to-purple-500 text-white'
                          : 'bg-green-500 text-white'
                        : 'bg-gray-200 dark:bg-gray-700 text-gray-400'
                    }`}
                  >
                    <Icon className="w-5 h-5" />
                  </div>
                  <span className={`text-xs mt-2 ${
                    isCompleted ? t.text.primary : t.text.muted
                  }`}>
                    {step.label}
                  </span>
                </motion.div>
              );
            })}
          </div>
        </div>
      </div>

      {/* Main Content */}
      {isEditing && !readOnly ? (
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          className="space-y-4"
        >
          <div>
            <label className={`block ${t.text.secondary} text-sm mb-2`}>
              Tracking Number
            </label>
            <input
              type="text"
              value={data.tracking_number}
              onChange={(e) => setData('tracking_number', e.target.value)}
              className={`w-full px-4 py-2 ${t.glass.input} ${t.glass.border} rounded-lg`}
              placeholder="Enter tracking number"
            />
          </div>

          <div>
            <label className={`block ${t.text.secondary} text-sm mb-2`}>
              Carrier
            </label>
            <select
              value={data.tracking_carrier}
              onChange={(e) => setData('tracking_carrier', e.target.value)}
              className={`w-full px-4 py-2 ${t.glass.input} ${t.glass.border} rounded-lg`}
            >
              <option value="">Select carrier</option>
              {carrierOptions.map(carrier => (
                <option key={carrier.value} value={carrier.value}>
                  {carrier.label}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className={`block ${t.text.secondary} text-sm mb-2`}>
              Status
            </label>
            <select
              value={data.tracking_status}
              onChange={(e) => setData('tracking_status', e.target.value)}
              className={`w-full px-4 py-2 ${t.glass.input} ${t.glass.border} rounded-lg`}
            >
              {statusSteps.map(step => (
                <option key={step.status} value={step.status}>
                  {step.label}
                </option>
              ))}
            </select>
          </div>

          <div className="flex gap-3">
            <motion.button
              whileHover={{ scale: 1.02 }}
              whileTap={{ scale: 0.98 }}
              onClick={handleSubmit}
              disabled={processing}
              className={`flex-1 px-4 py-2 ${t.button.primary} rounded-lg`}
            >
              {processing ? (
                <ArrowPathIcon className="w-5 h-5 animate-spin mx-auto" />
              ) : (
                'Save Changes'
              )}
            </motion.button>
            <motion.button
              whileHover={{ scale: 1.02 }}
              whileTap={{ scale: 0.98 }}
              onClick={() => setIsEditing(false)}
              className={`px-4 py-2 ${t.button.secondary} rounded-lg`}
            >
              Cancel
            </motion.button>
          </div>
        </motion.div>
      ) : (
        <div className="space-y-4">
          {/* Tracking Details */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className={`p-4 ${t.glass.card} ${t.glass.border} rounded-lg`}>
              <div className="flex items-center gap-3 mb-2">
                <div className="w-10 h-10 rounded-full bg-blue-500/20 flex items-center justify-center">
                  <TruckIcon className="w-5 h-5 text-blue-500" />
                </div>
                <div>
                  <p className={`${t.text.muted} text-sm`}>Tracking Number</p>
                  <p className={`${t.text.primary} font-medium`}>
                    {order.tracking_number || 'Not assigned'}
                  </p>
                </div>
              </div>
            </div>

            <div className={`p-4 ${t.glass.card} ${t.glass.border} rounded-lg`}>
              <div className="flex items-center gap-3 mb-2">
                <div className="w-10 h-10 rounded-full bg-purple-500/20 flex items-center justify-center">
                  <SparklesIcon className="w-5 h-5 text-purple-500" />
                </div>
                <div>
                  <p className={`${t.text.muted} text-sm`}>Carrier</p>
                  <p className={`${t.text.primary} font-medium`}>
                    {carrierOptions.find(c => c.value === order.tracking_carrier)?.label || 'Not specified'}
                  </p>
                </div>
              </div>
            </div>
          </div>

          {/* Estimated Delivery */}
          {order.estimated_delivery && (
            <div className={`p-4 ${t.glass.card} ${t.glass.border} rounded-lg`}>
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <ClockIcon className="w-5 h-5 text-amber-500" />
                  <div>
                    <p className={`${t.text.muted} text-sm`}>Estimated Delivery</p>
                    <p className={`${t.text.primary} font-medium`}>
                      {new Date(order.estimated_delivery).toLocaleDateString()}
                    </p>
                  </div>
                </div>
                <motion.div
                  initial={{ scale: 0 }}
                  animate={{ scale: 1 }}
                  className="px-3 py-1 bg-amber-500/20 text-amber-600 dark:text-amber-400 rounded-full text-sm"
                >
                  On Schedule
                </motion.div>
              </div>
            </div>
          )}

          {/* Real-time Update Alert */}
          <AnimatePresence>
            {showRealTimeUpdate && (
              <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -20 }}
                className="p-4 bg-blue-500/10 border border-blue-500/20 rounded-xl"
              >
                <div className="flex items-start gap-3">
                  <BellIcon className="w-5 h-5 text-blue-500 mt-0.5" />
                  <div className="flex-1">
                    <p className="text-blue-600 dark:text-blue-400 font-medium">
                      Real-time Update
                    </p>
                    <p className={`${t.text.muted} text-sm mt-1`}>
                      Package scanned at distribution center. Delivery on track.
                    </p>
                  </div>
                  <button
                    onClick={() => setShowRealTimeUpdate(false)}
                    className="text-blue-500 hover:text-blue-600 transition-colors"
                  >
                    <span className="text-sm">Dismiss</span>
                  </button>
                </div>
              </motion.div>
            )}
          </AnimatePresence>

          {/* Track Package Button */}
          {order.tracking_number && (
            <motion.button
              whileHover={{ scale: 1.02 }}
              whileTap={{ scale: 0.98 }}
              className="w-full px-4 py-3 bg-gradient-to-r from-blue-500 to-purple-500 text-white rounded-lg font-medium"
            >
              Track Package in Real-Time
            </motion.button>
          )}
        </div>
      )}
    </div>
  );
};

export default TrackingManager;
