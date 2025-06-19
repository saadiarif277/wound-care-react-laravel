import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import IVREpisodeStatus from '@/Components/Admin/IVREpisodeStatus';
import TrackingManager from '@/Components/Admin/TrackingManager';
import ConfirmationDocuments from '@/Components/Admin/ConfirmationDocuments';
import AuditLog from '@/Components/Admin/AuditLog';
import {
  ArrowLeft,
  FileText,
  Clock,
  User,
  Building2,
  Package,
  CheckCircle,
  AlertTriangle,
  Activity,
  Users,
  Calendar,
  Heart,
  Send,
  Plus,
  ChevronDown,
  ChevronUp,
  Eye,
  Edit,
  Shield,
  Phone,
  Mail,
  MapPin,
  DollarSign,
  Stethoscope,
  FileCheck,
  Download,
  AlertCircle,
  Settings,
  RefreshCw,
  Info,
  ExternalLink,
  Bell,
  Clipboard,
  Star,
  Timer,
  Layers,
  Upload,
  X,
} from 'lucide-react';

interface Order {
  id: string;
  order_number: string;
  order_status: string;
  provider: {
    id: number;
    name: string;
    email: string;
    npi_number?: string;
  };
  facility: {
    id: number;
    name: string;
    city: string;
    state: string;
  };
  expected_service_date: string;
  submitted_at: string;
  total_order_value: number;
  action_required: boolean;
  products: Array<{
    id: number;
    name: string;
    sku: string;
    quantity: number;
    unit_price: number;
    total_price: number;
  }>;
}

interface Episode {
  id: string;
  patient_id: string;
  patient_name: string;
  patient_display_id: string;
  status: string;
  ivr_status: string;
  verification_date?: string;
  expiration_date?: string;
  manufacturer: {
    id: number;
    name: string;
    contact_email?: string;
    contact_phone?: string;
  };
  orders: Order[];
  docuseal: {
    status?: string;
    signed_documents?: Array<{ id: number; filename?: string; name?: string; url: string }>;
    audit_log_url?: string;
    last_synced_at?: string;
  };
  total_order_value: number;
  orders_count: number;
  action_required: boolean;
  audit_log: Array<{
    id: number;
    action: string;
    actor: string;
    timestamp: string;
    notes?: string;
  }>;
}

interface ShowEpisodeProps {
  episode: Episode;
  can_review_episode: boolean;
  can_manage_episode: boolean;
  can_send_to_manufacturer: boolean;
}

// Enhanced 2025 Healthcare Design Status Configuration - ASHLEY'S WORKFLOW
const statusConfig = {
  ready_for_review: {
    color: 'bg-blue-100 text-blue-800 border-blue-300',
    icon: Clock,
    label: 'Ready for Review',
    description: 'Provider completed IVR - awaiting admin review',
    priority: 'high',
    actionText: 'Review Provider IVR'
  },
  ivr_sent: {
    color: 'bg-yellow-100 text-yellow-800 border-yellow-300',
    icon: Send,
    label: 'IVR Sent',
    description: 'IVR documentation sent to manufacturer for verification',
    priority: 'medium',
    actionText: 'Awaiting Verification'
  },
  ivr_verified: {
    color: 'bg-green-100 text-green-800 border-green-300',
    icon: CheckCircle,
    label: 'IVR Verified',
    description: 'IVR verified and ready for manufacturer submission',
    priority: 'low',
    actionText: 'Ready to Submit'
  },
  sent_to_manufacturer: {
    color: 'bg-purple-100 text-purple-800 border-purple-300',
    icon: Package,
    label: 'Sent to Manufacturer',
    description: 'Episode submitted to manufacturer for processing and fulfillment',
    priority: 'medium',
    actionText: 'Awaiting Tracking'
  },
  tracking_added: {
    color: 'bg-indigo-100 text-indigo-800 border-indigo-300',
    icon: Package,
    label: 'Tracking Added',
    description: 'Tracking information added, shipment in progress',
    priority: 'low',
    actionText: 'In Transit'
  },
  completed: {
    color: 'bg-green-100 text-green-800 border-green-300',
    icon: CheckCircle,
    label: 'Completed',
    description: 'Episode fully processed and completed successfully',
    priority: 'completed',
    actionText: 'Completed'
  },
};

const ivrStatusConfig = {
  provider_completed: {
    color: 'bg-blue-100 text-blue-800 border-blue-300',
    icon: CheckCircle,
    label: 'Provider Completed',
    description: 'Provider has completed and signed IVR form'
  },
  admin_reviewed: {
    color: 'bg-green-100 text-green-800 border-green-300',
    icon: CheckCircle,
    label: 'Admin Reviewed',
    description: 'Admin has reviewed and approved provider IVR'
  },
  verified: {
    color: 'bg-green-100 text-green-800 border-green-300',
    icon: CheckCircle,
    label: 'Verified',
    description: 'IVR verified and valid for processing'
  },
  expired: {
    color: 'bg-red-100 text-red-800 border-red-300',
    icon: AlertTriangle,
    label: 'Expired',
    description: 'IVR has expired and requires renewal'
  },
};

const orderStatusConfig = {
  pending_ivr: {
    color: 'bg-gray-100 text-gray-800',
    icon: Clock,
    label: 'Pending IVR'
  },
  ivr_sent: {
    color: 'bg-blue-100 text-blue-800',
    icon: Send,
    label: 'IVR Sent'
  },
  ivr_confirmed: {
    color: 'bg-purple-100 text-purple-800',
    icon: FileText,
    label: 'IVR Confirmed'
  },
  approved: {
    color: 'bg-green-100 text-green-800',
    icon: CheckCircle,
    label: 'Approved'
  },
  sent_back: {
    color: 'bg-orange-100 text-orange-800',
    icon: AlertTriangle,
    label: 'Sent Back'
  },
  denied: {
    color: 'bg-red-100 text-red-800',
    icon: AlertTriangle,
    label: 'Denied'
  },
  submitted_to_manufacturer: {
    color: 'bg-purple-100 text-purple-800',
    icon: Package,
    label: 'Submitted to Manufacturer'
  },
  shipped: {
    color: 'bg-indigo-100 text-indigo-800',
    icon: Package,
    label: 'Shipped'
  },
  delivered: {
    color: 'bg-green-100 text-green-800',
    icon: CheckCircle,
    label: 'Delivered'
  },
};

const ShowEpisode: React.FC<ShowEpisodeProps> = ({
  episode,
  can_review_episode,
  can_manage_episode,
  can_send_to_manufacturer,
}) => {
  const [expandedSections, setExpandedSections] = useState({
    orders: true,
    episode_info: true,
    documents: true,
    audit: false,
  });
  const [showTrackingModal, setShowTrackingModal] = useState(false);

  const [lastRefresh, setLastRefresh] = useState(new Date());

  const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(amount);
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    });
  };

  const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    });
  };

  const toggleSection = (section: keyof typeof expandedSections) => {
    setExpandedSections(prev => ({
      ...prev,
      [section]: !prev[section],
    }));
  };

  const refreshData = () => {
    setLastRefresh(new Date());
    router.reload({ only: ['episode'] });
  };

  const handleReviewEpisode = () => {
    router.post(route('admin.episodes.review', episode.id));
  };

  const handleSendToManufacturer = () => {
    router.post(route('admin.episodes.send-to-manufacturer', episode.id));
  };

  const handleMarkCompleted = () => {
    router.post(route('admin.episodes.mark-completed', episode.id));
  };

  const currentStatusConfig = statusConfig[episode.status as keyof typeof statusConfig];
  const currentIvrStatusConfig = ivrStatusConfig[episode.ivr_status as keyof typeof ivrStatusConfig];

  return (
    <MainLayout>
      <Head title={`Episode ${episode.patient_display_id} - ${episode.manufacturer.name} | MSC Healthcare`} />

      <div className="min-h-screen bg-gray-50 p-6">
        {/* Enhanced Header with 2025 Healthcare Design Principles */}
        <div className="mb-6">
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between">
              <div className="flex items-center space-x-4 mb-4 lg:mb-0">
                <Link
                  href={route('admin.orders.index')}
                  className="flex items-center text-gray-600 hover:text-gray-900 transition-colors"
                >
                  <ArrowLeft className="w-5 h-5 mr-2" />
                  Back to Episodes
                </Link>

                <div className="h-6 w-px bg-gray-300"></div>

                <div className="flex items-center">
                  <Layers className="w-6 h-6 text-blue-600 mr-3" />
                  <div>
                    <h1 className="text-xl font-bold text-gray-900">
                      Episode: {episode.patient_display_id}
                    </h1>
                    <p className="text-sm text-gray-600">
                      {episode.manufacturer.name} • {episode.orders_count} order{episode.orders_count !== 1 ? 's' : ''}
                    </p>
                  </div>
                </div>
              </div>

              <div className="flex flex-col sm:flex-row gap-3">
                <button
                  onClick={refreshData}
                  className="flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"
                >
                  <RefreshCw className="w-4 h-4 mr-2" />
                  Refresh
                </button>

                <button className="flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                  <Download className="w-4 h-4 mr-2" />
                  Export
                </button>

                <button className="flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                  <Bell className="w-4 h-4 mr-2" />
                  Notify
                </button>
              </div>
            </div>

            {/* Episode Status Overview */}
            <div className="mt-6 flex flex-wrap gap-4 items-center">
              <div className={`inline-flex items-center px-4 py-2 rounded-lg border-2 ${currentStatusConfig?.color || 'bg-gray-100 text-gray-800 border-gray-300'}`}>
                {currentStatusConfig?.icon && React.createElement(currentStatusConfig.icon, { className: "w-4 h-4 mr-2" })}
                <span className="font-medium">{currentStatusConfig?.label || episode.status}</span>
              </div>

              <div className={`inline-flex items-center px-4 py-2 rounded-lg border-2 ${currentIvrStatusConfig?.color || 'bg-gray-100 text-gray-800 border-gray-300'}`}>
                {currentIvrStatusConfig?.icon && React.createElement(currentIvrStatusConfig.icon, { className: "w-4 h-4 mr-2" })}
                <span className="font-medium">{currentIvrStatusConfig?.label || episode.ivr_status}</span>
              </div>

              {episode.action_required && (
                <div className="inline-flex items-center px-4 py-2 rounded-lg border-2 bg-red-100 text-red-800 border-red-300">
                  <AlertCircle className="w-4 h-4 mr-2" />
                  <span className="font-medium">Action Required</span>
                </div>
              )}

              <div className="flex items-center text-sm text-gray-500 ml-auto">
                <Clock className="w-4 h-4 mr-1" />
                Last updated: {formatDateTime(lastRefresh.toISOString())}
              </div>
            </div>
          </div>
        </div>

        {/* Three-Column Layout - 2025 Healthcare Information Architecture */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

          {/* Left Column: Order Information */}
          <div className="lg:col-span-1 space-y-6">

            {/* Orders in Episode */}
            <div className="bg-white rounded-lg shadow-sm border border-gray-200">
              <div
                className="flex items-center justify-between p-6 border-b cursor-pointer hover:bg-gray-50 transition-colors"
                onClick={() => toggleSection('orders')}
              >
                <div className="flex items-center">
                  <Package className="w-5 h-5 text-blue-600 mr-3" />
                  <h3 className="text-lg font-semibold text-gray-900">
                    Orders ({episode.orders_count})
                  </h3>
                </div>
                {expandedSections.orders ?
                  <ChevronUp className="w-5 h-5 text-gray-400" /> :
                  <ChevronDown className="w-5 h-5 text-gray-400" />
                }
              </div>

              {expandedSections.orders && (
                <div className="p-6 space-y-4">
                  {episode.orders.map((order) => {
                    const orderConfig = orderStatusConfig[order.order_status as keyof typeof orderStatusConfig] ||
                      { color: 'bg-gray-100 text-gray-800', icon: FileText, label: order.order_status };

                    return (
                      <div key={order.id} className="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                        <div className="flex items-start justify-between mb-3">
                          <div>
                            <div className="flex items-center space-x-2 mb-2">
                              <Link
                                href={route('admin.orders.show', order.id)}
                                className="font-medium text-blue-600 hover:text-blue-800 transition-colors"
                              >
                                #{order.order_number}
                              </Link>
                              <ExternalLink className="w-3 h-3 text-gray-400" />
                            </div>
                            <p className="text-sm text-gray-600">
                              Provider: {order.provider.name}
                            </p>
                            <p className="text-sm text-gray-600">
                              Expected Service: {formatDate(order.expected_service_date)}
                            </p>
                          </div>

                          <div className="text-right">
                            <div className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${orderConfig.color}`}>
                              {React.createElement(orderConfig.icon, { className: "w-3 h-3 mr-1" })}
                              {orderConfig.label}
                            </div>
                            <p className="text-sm font-semibold text-gray-900 mt-2">
                              {formatCurrency(order.total_order_value)}
                            </p>
                          </div>
                        </div>

                        {order.products && order.products.length > 0 && (
                          <div className="border-t pt-3 mt-3">
                            <p className="text-xs text-gray-500 mb-2">Products:</p>
                            <div className="space-y-1">
                              {order.products.slice(0, 3).map((product) => (
                                <div key={product.id} className="flex justify-between text-xs">
                                  <span className="text-gray-700">{product.name} x{product.quantity}</span>
                                  <span className="text-gray-600">{formatCurrency(product.total_price)}</span>
                                </div>
                              ))}
                              {order.products.length > 3 && (
                                <p className="text-xs text-gray-500">
                                  +{order.products.length - 3} more products
                                </p>
                              )}
                            </div>
                          </div>
                        )}
                      </div>
                    );
                  })}

                  <div className="border-t pt-4 mt-4">
                    <div className="flex justify-between items-center">
                      <span className="font-semibold text-gray-900">Total Episode Value:</span>
                      <span className="text-lg font-bold text-blue-600">
                        {formatCurrency(episode.total_order_value)}
                      </span>
                    </div>
                  </div>
                </div>
              )}
            </div>

            {/* Provider Information Card */}
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <div className="flex items-center mb-4">
                <User className="w-5 h-5 text-blue-600 mr-3" />
                <h3 className="text-lg font-semibold text-gray-900">Provider Information</h3>
              </div>

              <div className="space-y-3">
                <div className="flex justify-between">
                  <span className="text-sm text-gray-600">Episode ID:</span>
                  <span className="text-sm font-medium text-gray-900">{episode.patient_display_id}</span>
                </div>

                {episode.orders && episode.orders.length > 0 && episode.orders[0].provider && (
                  <>
                    <div className="flex justify-between">
                      <span className="text-sm text-gray-600">Provider Name:</span>
                      <span className="text-sm font-medium text-gray-900">{episode.orders[0].provider.name}</span>
                    </div>

                    <div className="flex justify-between">
                      <span className="text-sm text-gray-600">Provider Email:</span>
                      <span className="text-sm text-gray-900">{episode.orders[0].provider.email}</span>
                    </div>

                    {episode.orders[0].provider.npi_number && (
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-600">NPI Number:</span>
                        <span className="text-sm font-mono text-gray-900">{episode.orders[0].provider.npi_number}</span>
                      </div>
                    )}

                    {episode.orders[0].facility && (
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-600">Facility:</span>
                        <span className="text-sm text-gray-900">{episode.orders[0].facility.name}</span>
                      </div>
                    )}
                  </>
                )}

                <div className="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-md">
                  <div className="flex items-center">
                    <Info className="w-4 h-4 text-blue-600 mr-2" />
                    <p className="text-sm font-medium text-blue-800">Provider-Generated IVR</p>
                  </div>
                  <p className="text-xs text-blue-700 mt-1">
                    This provider submitted their order with IVR already generated and ready for review.
                  </p>
                </div>
              </div>
            </div>

            {/* Manufacturer Information */}
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <div className="flex items-center mb-4">
                <Building2 className="w-5 h-5 text-blue-600 mr-3" />
                <h3 className="text-lg font-semibold text-gray-900">Manufacturer</h3>
              </div>

              <div className="space-y-3">
                <div className="flex justify-between">
                  <span className="text-sm text-gray-600">Company:</span>
                  <span className="text-sm font-medium text-gray-900">{episode.manufacturer.name}</span>
                </div>

                {episode.manufacturer.contact_email && (
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Email:</span>
                    <a
                      href={`mailto:${episode.manufacturer.contact_email}`}
                      className="text-sm text-blue-600 hover:text-blue-800 flex items-center"
                    >
                      <Mail className="w-3 h-3 mr-1" />
                      Contact
                    </a>
                  </div>
                )}

                {episode.manufacturer.contact_phone && (
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Phone:</span>
                    <a
                      href={`tel:${episode.manufacturer.contact_phone}`}
                      className="text-sm text-blue-600 hover:text-blue-800 flex items-center"
                    >
                      <Phone className="w-3 h-3 mr-1" />
                      Call
                    </a>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Middle Column: Episode Information */}
          <div className="lg:col-span-1 space-y-6">
            {/* Episode Metrics */}
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Episode Metrics</h3>
              <div className="grid grid-cols-2 gap-4">
                <div className="text-center">
                  <p className="text-2xl font-bold text-gray-900">{episode.orders_count}</p>
                  <p className="text-sm text-gray-500">Orders</p>
                </div>
                <div className="text-center">
                  <p className="text-2xl font-bold text-green-600">{formatCurrency(episode.total_order_value)}</p>
                  <p className="text-sm text-gray-500">Total Value</p>
                </div>
              </div>
              {episode.action_required && (
                <div className="mt-4 p-3 bg-orange-50 border border-orange-200 rounded-md">
                  <div className="flex items-center">
                    <AlertTriangle className="w-4 h-4 text-orange-600 mr-2" />
                    <p className="text-sm font-medium text-orange-800">Action Required</p>
                  </div>
                  <p className="text-xs text-orange-700 mt-1">This episode requires admin attention</p>
                </div>
              )}
            </div>

            {/* Episode Timeline & Status */}
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Episode Timeline</h3>
              <div className="space-y-4">
                {/* Current Status */}
                <div className="flex items-center justify-between p-3 bg-blue-50 rounded-md border border-blue-200">
                  <div className="flex items-center">
                    <div className="w-2 h-2 bg-blue-600 rounded-full mr-3"></div>
                    <span className="text-sm font-medium text-blue-900">Current Status</span>
                  </div>
                  <span className="text-sm text-blue-700">{currentStatusConfig?.label || episode.status}</span>
                </div>

                {/* IVR Status */}
                <div className="flex items-center justify-between p-3 bg-gray-50 rounded-md border border-gray-200">
                  <div className="flex items-center">
                    <div className="w-2 h-2 bg-gray-400 rounded-full mr-3"></div>
                    <span className="text-sm font-medium text-gray-700">IVR Status</span>
                  </div>
                  <span className="text-sm text-gray-600">{currentIvrStatusConfig?.label || episode.ivr_status}</span>
                </div>

                {/* Recent Activity */}
                {episode.audit_log && episode.audit_log.length > 0 && (
                  <div className="border-t pt-3">
                    <p className="text-xs font-medium text-gray-700 mb-2">Recent Activity</p>
                    <div className="space-y-2">
                      {episode.audit_log.slice(0, 3).map((log, index) => (
                        <div key={log.id} className="flex justify-between text-xs">
                          <span className="text-gray-600">{log.action}</span>
                          <span className="text-gray-500">{formatDateTime(log.timestamp)}</span>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            </div>

            {/* Verification and Expiration */}
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Verification and Expiration</h3>
              <div className="space-y-3">
                {episode.verification_date ? (
                  <div className="flex justify-between">
                    <span className="text-sm text-gray-600">Verification Date:</span>
                    <span className="text-sm font-medium text-gray-900">{formatDate(episode.verification_date)}</span>
                  </div>
                ) : (
                  <div className="flex justify-between">
                    <span className="text-sm text-gray-600">Verification Date:</span>
                    <span className="text-sm text-gray-500 italic">Not verified</span>
                  </div>
                )}

                {episode.expiration_date ? (
                  <div className="flex justify-between">
                    <span className="text-sm text-gray-600">Expiration Date:</span>
                    <span className="text-sm font-medium text-gray-900">{formatDate(episode.expiration_date)}</span>
                  </div>
                ) : (
                  <div className="flex justify-between">
                    <span className="text-sm text-gray-600">Expiration Date:</span>
                    <span className="text-sm text-gray-500 italic">Not set</span>
                  </div>
                )}

                {episode.expiration_date && new Date(episode.expiration_date) < new Date() && (
                  <div className="mt-3 p-3 bg-red-50 border border-red-200 rounded-md">
                    <div className="flex items-center">
                      <AlertCircle className="w-4 h-4 text-red-600 mr-2" />
                      <span className="text-sm font-medium text-red-800">Episode Expired</span>
                    </div>
                  </div>
                )}

                {episode.expiration_date && new Date(episode.expiration_date) > new Date() && new Date(episode.expiration_date) < new Date(Date.now() + 30 * 24 * 60 * 60 * 1000) && (
                  <div className="mt-3 p-3 bg-orange-50 border border-orange-200 rounded-md">
                    <div className="flex items-center">
                      <AlertTriangle className="w-4 h-4 text-orange-600 mr-2" />
                      <span className="text-sm font-medium text-orange-800">Expires Soon</span>
                    </div>
                    <p className="text-xs text-orange-700 mt-1">
                      Episode expires on {formatDate(episode.expiration_date)}
                    </p>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Right Column: Actions and Documents */}
          <div className="lg:col-span-1 space-y-6">
            {/* Episode Actions - ASHLEY'S WORKFLOW */}
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Episode Actions</h3>
              <div className="space-y-3">
                {/* ASHLEY'S REQUIREMENT: Review provider-generated IVR */}
                {can_review_episode && episode.status === 'ready_for_review' && (
                  <>
                    <button
                      onClick={() => router.post(route('admin.episodes.review', episode.id))}
                      className="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700"
                    >
                      <CheckCircle className="w-4 h-4 mr-2" />
                      Review Provider IVR
                    </button>
                    <div className="p-3 bg-blue-50 border border-blue-200 rounded-md">
                      <div className="flex items-center">
                        <Info className="w-4 h-4 text-blue-600 mr-2" />
                        <p className="text-sm font-medium text-blue-800">Provider Completed IVR</p>
                      </div>
                      <p className="text-xs text-blue-700 mt-1">
                        Provider has already generated and signed the IVR form. Review for accuracy and approve.
                      </p>
                    </div>
                  </>
                )}

                {/* Send to manufacturer after IVR review */}
                {can_send_to_manufacturer && episode.status === 'ivr_verified' && (
                  <button
                    onClick={() => router.post(route('admin.episodes.send-to-manufacturer', episode.id))}
                    className="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700"
                  >
                    <Send className="w-4 h-4 mr-2" />
                    Send Episode to Manufacturer
                  </button>
                )}

                {/* Add tracking information */}
                {can_manage_episode && episode.status === 'sent_to_manufacturer' && (
                  <button
                    onClick={() => setShowTrackingModal(true)}
                    className="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700"
                  >
                    <Package className="w-4 h-4 mr-2" />
                    Add Tracking Info
                  </button>
                )}

                {/* Mark as completed */}
                {can_manage_episode && episode.status === 'tracking_added' && (
                  <button
                    onClick={() => router.post(route('admin.episodes.mark-completed', episode.id))}
                    className="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-600 hover:bg-gray-700"
                  >
                    <CheckCircle className="w-4 h-4 mr-2" />
                    Mark Episode Completed
                  </button>
                )}

                {/* Completed status */}
                {episode.status === 'completed' && (
                  <div className="w-full p-3 bg-green-50 border border-green-200 rounded-lg text-center">
                    <CheckCircle className="w-5 h-5 text-green-600 mx-auto mb-1" />
                    <p className="text-sm text-green-700 font-medium">Episode Completed</p>
                    <p className="text-xs text-green-600 mt-1">
                      All orders processed and delivered
                    </p>
                  </div>
                )}

                {/* No actions available */}
                {!can_review_episode && !can_send_to_manufacturer && !can_manage_episode && (
                  <div className="w-full p-3 bg-gray-50 border border-gray-200 rounded-lg text-center">
                    <Info className="w-5 h-5 text-gray-400 mx-auto mb-1" />
                    <p className="text-sm text-gray-600">No actions available</p>
                  </div>
                )}
              </div>
            </div>

            {/* Documents */}
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-semibold text-gray-900">Documents</h3>
                <button
                  onClick={() => document.getElementById('file-upload')?.click()}
                  className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                >
                  <Upload className="w-4 h-4 mr-2" />
                  Upload
                </button>
              </div>

              {/* File Upload Input */}
              <input
                id="file-upload"
                type="file"
                multiple
                className="hidden"
                onChange={(e) => {
                  const files = Array.from(e.target.files || []);
                  files.forEach(file => {
                    console.log('Uploading file:', file.name);
                    // TODO: Implement file upload logic
                  });
                }}
              />

              {/* Document List */}
              <div className="space-y-3">
                {/* IVR Documents */}
                {episode.docuseal.signed_documents && episode.docuseal.signed_documents.length > 0 && (
                  <div>
                    <h4 className="text-sm font-medium text-gray-900 mb-2">IVR Documents</h4>
                    <div className="space-y-2">
                      {episode.docuseal.signed_documents.map((doc, index) => (
                        <div key={index} className="flex items-center justify-between p-2 bg-gray-50 rounded-md">
                          <div className="flex items-center">
                            <FileText className="w-4 h-4 text-gray-400 mr-2" />
                            <span className="text-sm text-gray-900">{doc.filename || doc.name || `Document ${index + 1}`}</span>
                          </div>
                          <div className="flex items-center space-x-2">
                            <a
                              href={doc.url}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="text-sm text-blue-600 hover:text-blue-800 flex items-center"
                            >
                              <Download className="w-3 h-3 mr-1" />
                              Download
                            </a>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {/* Audit Log */}
                {episode.docuseal.audit_log_url && (
                  <div>
                    <h4 className="text-sm font-medium text-gray-900 mb-2">Audit Trail</h4>
                    <div className="flex items-center justify-between p-2 bg-gray-50 rounded-md">
                      <div className="flex items-center">
                        <FileCheck className="w-4 h-4 text-gray-400 mr-2" />
                        <span className="text-sm text-gray-900">Document Audit Log</span>
                      </div>
                      <a
                        href={episode.docuseal.audit_log_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-sm text-blue-600 hover:text-blue-800 flex items-center"
                      >
                        <ExternalLink className="w-3 h-3 mr-1" />
                        View
                      </a>
                    </div>
                  </div>
                )}

                {/* Additional Documents */}
                <div>
                  <h4 className="text-sm font-medium text-gray-900 mb-2">Additional Documents</h4>
                  {/* TODO: List additional uploaded documents */}
                  <div className="text-sm text-gray-500 italic">
                    No additional documents uploaded
                  </div>
                </div>

                {/* Document Status Summary */}
                <div className="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-md">
                  <div className="flex items-center">
                    <Info className="w-4 h-4 text-blue-600 mr-2" />
                    <p className="text-sm font-medium text-blue-800">Document Status</p>
                  </div>
                  <div className="text-xs text-blue-700 mt-1">
                    <p>IVR Status: {episode.docuseal.status || 'Pending'}</p>
                    {episode.docuseal.last_synced_at && (
                      <p>Last Updated: {formatDateTime(episode.docuseal.last_synced_at)}</p>
                    )}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* ASHLEY'S WORKFLOW: IVR Status Display */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center">
            <FileCheck className="w-5 h-5 text-blue-600 mr-3" />
            IVR Status
          </h3>

          <div className="space-y-4">
            {/* Current IVR Status */}
            <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
              <div className="flex items-center">
                {episode.ivr_status === 'provider_completed' && (
                  <CheckCircle className="w-5 h-5 text-blue-600 mr-2" />
                )}
                {episode.ivr_status === 'admin_reviewed' && (
                  <CheckCircle className="w-5 h-5 text-green-600 mr-2" />
                )}
                <div>
                  <p className="text-sm font-medium text-gray-900">
                    {ivrStatusConfig[episode.ivr_status as keyof typeof ivrStatusConfig]?.label || 'Unknown Status'}
                  </p>
                  <p className="text-xs text-gray-500">
                    {ivrStatusConfig[episode.ivr_status as keyof typeof ivrStatusConfig]?.description}
                  </p>
                </div>
              </div>
              <div className={`px-3 py-1 rounded-full text-xs font-medium ${ivrStatusConfig[episode.ivr_status as keyof typeof ivrStatusConfig]?.color || 'bg-gray-100 text-gray-800'}`}>
                {episode.ivr_status === 'provider_completed' ? 'Provider Generated' : 'Admin Reviewed'}
              </div>
            </div>

            {/* IVR Frequency Information */}
            <div className="p-3 bg-blue-50 border border-blue-200 rounded-lg">
              <div className="flex items-center mb-2">
                <Info className="w-4 h-4 text-blue-600 mr-2" />
                <p className="text-sm font-medium text-blue-800">IVR Frequency Requirements</p>
              </div>
              <p className="text-xs text-blue-700">
                {episode.manufacturer.name} requires IVR verification {
                  episode.manufacturer.name === 'Acell' ? 'monthly' :
                  episode.manufacturer.name === 'Organogenesis' ? 'quarterly' : 'weekly'
                }.
                {episode.verification_date && (
                  <span className="ml-1">
                    Last verified: {formatDate(episode.verification_date)}
                  </span>
                )}
              </p>
            </div>

            {/* Expiration Warning */}
            {episode.expiration_date && (
              <div className="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                <div className="flex items-center mb-2">
                  <AlertTriangle className="w-4 h-4 text-yellow-600 mr-2" />
                  <p className="text-sm font-medium text-yellow-800">Expiration Notice</p>
                </div>
                <p className="text-xs text-yellow-700">
                  IVR expires on {formatDate(episode.expiration_date)}
                </p>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Tracking Modal */}
      {showTrackingModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-lg max-w-md w-full p-6">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-gray-900">Add Tracking Information</h3>
              <button
                onClick={() => setShowTrackingModal(false)}
                className="text-gray-400 hover:text-gray-600"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form
              onSubmit={(e) => {
                e.preventDefault();
                const formData = new FormData(e.target as HTMLFormElement);
                router.post(route('admin.episodes.update-tracking', episode.id), {
                  tracking_number: formData.get('tracking_number'),
                  carrier: formData.get('carrier'),
                  estimated_delivery: formData.get('estimated_delivery'),
                }, {
                  onSuccess: () => setShowTrackingModal(false)
                });
              }}
              className="space-y-4"
            >
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Tracking Number
                </label>
                <input
                  type="text"
                  name="tracking_number"
                  required
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Enter tracking number"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Carrier
                </label>
                <select
                  name="carrier"
                  required
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">Select carrier</option>
                  <option value="UPS">UPS</option>
                  <option value="FedEx">FedEx</option>
                  <option value="USPS">USPS</option>
                  <option value="DHL">DHL</option>
                  <option value="Other">Other</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Estimated Delivery (Optional)
                </label>
                <input
                  type="date"
                  name="estimated_delivery"
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div className="flex space-x-3 pt-4">
                <button
                  type="button"
                  onClick={() => setShowTrackingModal(false)}
                  className="flex-1 px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="flex-1 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                >
                  Add Tracking
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </MainLayout>
  );
};

export default ShowEpisode;
