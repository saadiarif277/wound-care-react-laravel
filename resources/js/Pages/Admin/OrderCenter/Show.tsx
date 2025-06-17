import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import {
  ArrowLeft,
  FileText,
  Clock,
  User,
  Building2,
  Calendar,
  Package,
  DollarSign,
  Download,
  Send,
  CheckCircle,
  XCircle,
  AlertTriangle,
  MessageSquare,
  History,
  Paperclip,
  MapPin,
  Heart,
  Activity,
  FileImage,
  ChevronDown,
  ChevronUp,
} from 'lucide-react';

interface OrderDetail {
  id: string;
  order_number: string;
  patient_display_id: string;
  patient_fhir_id: string;
  order_status: 'pending_ivr' | 'ivr_sent' | 'ivr_confirmed' | 'approved' | 'sent_back' | 'denied' | 'submitted_to_manufacturer';

  provider: {
    id: number;
    name: string;
    email: string;
    npi_number?: string;
    phone?: string;
  };

  facility: {
    id: number;
    name: string;
    address?: string;
    city: string;
    state: string;
    zip?: string;
    phone?: string;
  };

  manufacturer: {
    id: number;
    name: string;
    contact_email?: string;
    contact_phone?: string;
    ivr_template_id?: string;
  };

  patient_info: {
    dob?: string;
    insurance_name?: string;
    insurance_id?: string;
    diagnosis_codes?: string[];
  };

  order_details: {
    products: Array<{
      id: number;
      name: string;
      sku: string;
      quantity: number;
      size?: string;
      unit_price: number;
      total_price: number;
    }>;
    wound_type?: string;
    wound_location?: string;
    wound_size?: string;
    wound_duration?: string;
  };

  documents: Array<{
    id: number;
    name: string;
    type: string;
    uploaded_at: string;
    url?: string;
  }>;

  action_history: Array<{
    id: number;
    action: string;
    actor: string;
    timestamp: string;
    notes?: string;
  }>;

  expected_service_date: string;
  submitted_at: string;
  total_order_value: number;
  ivr_generation_status?: string;
  ivr_skip_reason?: string;
  docuseal_generation_status?: string;
  docuseal_submission_id?: string;
}

interface OrderShowProps {
  order: OrderDetail;
  can_generate_ivr: boolean;
  can_approve: boolean;
  can_submit_to_manufacturer: boolean;
}

const OrderShow: React.FC<OrderShowProps> = ({
  order,
  can_generate_ivr,
  can_approve,
  can_submit_to_manufacturer,
}) => {
  const [showIvrModal, setShowIvrModal] = useState(false);
  const [ivrRequired, setIvrRequired] = useState(true);
  const [ivrJustification, setIvrJustification] = useState('');
  const [showActionModal, setShowActionModal] = useState<'approve' | 'send_back' | 'deny' | null>(null);
  const [actionNotes, setActionNotes] = useState('');
  const [expandedSections, setExpandedSections] = useState({
    documents: true,
    history: true,
    clinical: true,
  });

  const statusConfig = {
    draft: {
      color: 'bg-gray-100 text-gray-800 border-gray-300',
      icon: FileText,
      label: 'Draft'
    },
    submitted: {
      color: 'bg-yellow-100 text-yellow-800 border-yellow-300',
      icon: Clock,
      label: 'Submitted'
    },
    processing: {
      color: 'bg-blue-100 text-blue-800 border-blue-300',
      icon: Clock,
      label: 'Processing'
    },
    pending_ivr: {
      color: 'bg-gray-100 text-gray-800 border-gray-300',
      icon: Clock,
      label: 'Pending IVR'
    },
    ivr_sent: {
      color: 'bg-blue-100 text-blue-800 border-blue-300',
      icon: Send,
      label: 'IVR Sent'
    },
    ivr_confirmed: {
      color: 'bg-purple-100 text-purple-800 border-purple-300',
      icon: FileText,
      label: 'IVR Confirmed'
    },
    approved: {
      color: 'bg-green-100 text-green-800 border-green-300',
      icon: CheckCircle,
      label: 'Approved'
    },
    sent_back: {
      color: 'bg-orange-100 text-orange-800 border-orange-300',
      icon: AlertTriangle,
      label: 'Sent Back'
    },
    denied: {
      color: 'bg-red-100 text-red-800 border-red-300',
      icon: XCircle,
      label: 'Denied'
    },
    submitted_to_manufacturer: {
      color: 'bg-green-900 text-white border-green-900',
      icon: Package,
      label: 'Submitted to Manufacturer'
    },
    shipped: {
      color: 'bg-indigo-100 text-indigo-800 border-indigo-300',
      icon: Package,
      label: 'Shipped'
    },
    delivered: {
      color: 'bg-green-100 text-green-800 border-green-300',
      icon: CheckCircle,
      label: 'Delivered'
    },
    cancelled: {
      color: 'bg-gray-100 text-gray-800 border-gray-300',
      icon: XCircle,
      label: 'Cancelled'
    },
  };

  const handleGenerateIvr = () => {
    if (!ivrRequired && !ivrJustification.trim()) {
      alert('Please provide justification for skipping IVR');
      return;
    }

    router.post(`/admin/orders/${order.id}/generate-ivr`, {
      ivr_required: ivrRequired,
      justification: ivrJustification,
    }, {
      onSuccess: () => {
        setShowIvrModal(false);
        setIvrJustification('');
      }
    });
  };

  const handleOrderAction = (action: 'approve' | 'send_back' | 'deny') => {
    if ((action === 'send_back' || action === 'deny') && !actionNotes.trim()) {
      alert(`Please provide ${action === 'send_back' ? 'comments' : 'reason'} for this action`);
      return;
    }

    let endpoint = '';
    switch (action) {
      case 'approve':
        endpoint = `/admin/orders/${order.id}/approve`;
        break;
      case 'send_back':
        endpoint = `/admin/orders/${order.id}/send-back`;
        break;
      case 'deny':
        endpoint = `/admin/orders/${order.id}/deny`;
        break;
    }

    router.post(endpoint, {
      notes: actionNotes,
      notify_provider: true,
    }, {
      onSuccess: () => {
        setShowActionModal(null);
        setActionNotes('');
      }
    });
  };

  const handleSubmitToManufacturer = () => {
    if (confirm('Are you sure you want to submit this order to the manufacturer?')) {
      router.post(`/admin/orders/${order.id}/submit-to-manufacturer`, {});
    }
  };

  const toggleSection = (section: keyof typeof expandedSections) => {
    setExpandedSections(prev => ({
      ...prev,
      [section]: !prev[section]
    }));
  };

  const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(amount);
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const currentStatus = statusConfig[order.order_status] || {
    color: 'bg-gray-100 text-gray-800 border-gray-300',
    icon: FileText,
    label: order.order_status || 'Unknown'
  };
  const StatusIcon = currentStatus.icon;

  return (
    <MainLayout>
      <Head title={`Order ${order.order_number}`} />

      {/* Sticky Header */}
      <div className="sticky top-0 z-10 bg-white border-b border-gray-200 shadow-sm">
        <div className="px-4 sm:px-6 lg:px-8 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-4">
              <Link
                href="/admin/orders"
                className="text-gray-500 hover:text-gray-700"
              >
                <ArrowLeft className="h-5 w-5" />
              </Link>
              <div>
                <h1 className="text-xl font-bold text-gray-900">Order {order.order_number}</h1>
                <div className="flex items-center space-x-3 mt-1">
                  <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium border ${currentStatus.color}`}>
                    <StatusIcon className="w-4 h-4 mr-1.5" />
                    {currentStatus.label}
                  </span>
                  <span className="text-sm text-gray-500">
                    Submitted {formatDate(order.submitted_at)}
                  </span>
                </div>
              </div>
            </div>

            {/* Action Buttons */}
            <div className="flex items-center space-x-3">

                <button
                  onClick={() => setShowIvrModal(true)}
                  className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                >
                  <FileText className="w-4 h-4 mr-2" />
                  Generate IVR
                </button>


              {can_approve && order.order_status === 'ivr_confirmed' && (
                <>
                  <button
                    onClick={() => setShowActionModal('approve')}
                    className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700"
                  >
                    <CheckCircle className="w-4 h-4 mr-2" />
                    Approve
                  </button>
                  <button
                    onClick={() => setShowActionModal('send_back')}
                    className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-orange-600 hover:bg-orange-700"
                  >
                    <AlertTriangle className="w-4 h-4 mr-2" />
                    Send Back
                  </button>
                  <button
                    onClick={() => setShowActionModal('deny')}
                    className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700"
                  >
                    <XCircle className="w-4 h-4 mr-2" />
                    Deny
                  </button>
                </>
              )}

              {can_submit_to_manufacturer && order.order_status === 'approved' && (
                <button
                  onClick={handleSubmitToManufacturer}
                  className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700"
                >
                  <Send className="w-4 h-4 mr-2" />
                  Submit to Manufacturer
                </button>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Two Column Layout */}
      <div className="px-4 sm:px-6 lg:px-8 py-6">
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Left Column - Order Metadata */}
          <div className="space-y-6">
            {/* Provider & Facility Info */}
            <div className="bg-white rounded-lg border border-gray-200 p-6">
              <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
                <User className="w-5 h-5 mr-2 text-gray-500" />
                Provider Information
              </h3>
              <div className="space-y-3">
                <div>
                  <p className="text-sm font-medium text-gray-500">Provider</p>
                  <p className="text-sm text-gray-900">{order.provider?.name || 'Unknown Provider'}</p>
                  <p className="text-xs text-gray-500">{order.provider?.email || 'No email'}</p>
                  {order.provider?.npi_number && (
                    <p className="text-xs text-gray-500">NPI: {order.provider.npi_number}</p>
                  )}
                </div>
                <div className="pt-3 border-t border-gray-100">
                  <p className="text-sm font-medium text-gray-500">Facility</p>
                  <p className="text-sm text-gray-900">{order.facility?.name || 'Unknown Facility'}</p>
                  <p className="text-xs text-gray-500">{order.facility?.city || 'Unknown'}, {order.facility?.state || 'Unknown'}</p>
                  {order.facility?.phone && (
                    <p className="text-xs text-gray-500">Phone: {order.facility.phone}</p>
                  )}
                </div>
              </div>
            </div>

            {/* Patient Info */}
            <div className="bg-white rounded-lg border border-gray-200 p-6">
              <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
                <Heart className="w-5 h-5 mr-2 text-gray-500" />
                Patient Information
              </h3>
              <div className="space-y-3">
                <div>
                  <p className="text-sm font-medium text-gray-500">Patient ID</p>
                  <p className="text-sm text-gray-900">{order.patient_display_id}</p>
                </div>
                {order.patient_info?.insurance_name && (
                  <div>
                    <p className="text-sm font-medium text-gray-500">Insurance</p>
                    <p className="text-sm text-gray-900">{order.patient_info.insurance_name}</p>
                    {order.patient_info.insurance_id && (
                      <p className="text-xs text-gray-500">ID: {order.patient_info.insurance_id}</p>
                    )}
                  </div>
                )}
                {order.patient_info?.diagnosis_codes && order.patient_info.diagnosis_codes.length > 0 && (
                  <div>
                    <p className="text-sm font-medium text-gray-500">Diagnosis Codes</p>
                    <div className="mt-1 flex flex-wrap gap-1">
                      {order.patient_info.diagnosis_codes.map((code, index) => (
                        <span key={`diagnosis-${index}`} className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                          {code}
                        </span>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            </div>

            {/* Manufacturer Info */}
            <div className="bg-white rounded-lg border border-gray-200 p-6">
              <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
                <Building2 className="w-5 h-5 mr-2 text-gray-500" />
                Manufacturer Information
              </h3>
              <div className="space-y-3">
                <div>
                  <p className="text-sm font-medium text-gray-500">Name</p>
                  <p className="text-sm text-gray-900">{order.manufacturer?.name || 'Unknown Manufacturer'}</p>
                </div>
                {order.manufacturer?.contact_email && (
                  <div>
                    <p className="text-sm font-medium text-gray-500">Contact Email</p>
                    <p className="text-sm text-gray-900">{order.manufacturer.contact_email}</p>
                  </div>
                )}
                {order.manufacturer?.ivr_template_id && (
                  <div>
                    <p className="text-sm font-medium text-gray-500">IVR Template</p>
                    <p className="text-sm text-gray-900">Template #{order.manufacturer.ivr_template_id}</p>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Right Column - Order Details */}
          <div className="lg:col-span-2 space-y-6">
            {/* Order Summary */}
            <div className="bg-white rounded-lg border border-gray-200 p-6">
              <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
                <Package className="w-5 h-5 mr-2 text-gray-500" />
                Order Details
              </h3>

              {/* Products */}
              <div className="space-y-4">
                <div>
                  <h4 className="text-sm font-medium text-gray-700 mb-2">Products Requested</h4>
                  <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                      <thead className="bg-gray-50">
                        <tr>
                          <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                          <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                          <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                          <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                          <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        </tr>
                      </thead>
                      <tbody className="bg-white divide-y divide-gray-200">
                        {order.order_details?.products?.map((product) => (
                          <tr key={product.id}>
                            <td className="px-4 py-2 text-sm text-gray-900">{product.name}</td>
                            <td className="px-4 py-2 text-sm text-gray-500">{product.sku}</td>
                            <td className="px-4 py-2 text-sm text-gray-900">{product.quantity}</td>
                            <td className="px-4 py-2 text-sm text-gray-500">{product.size || '-'}</td>
                            <td className="px-4 py-2 text-sm text-gray-900 text-right">{formatCurrency(product.total_price)}</td>
                          </tr>
                        )) || (
                          <tr>
                            <td colSpan={5} className="px-4 py-2 text-sm text-gray-500 text-center">No products found</td>
                          </tr>
                        )}
                      </tbody>
                      <tfoot className="bg-gray-50">
                        <tr>
                          <td colSpan={4} className="px-4 py-2 text-sm font-medium text-gray-900">Total Order Value</td>
                          <td className="px-4 py-2 text-sm font-bold text-gray-900 text-right">{formatCurrency(order.total_order_value)}</td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>

                {/* Clinical Information */}
                <div className="pt-4 border-t border-gray-200">
                  <button
                    onClick={() => toggleSection('clinical')}
                    className="flex items-center justify-between w-full text-left"
                  >
                    <h4 className="text-sm font-medium text-gray-700 flex items-center">
                      <Activity className="w-4 h-4 mr-2 text-gray-500" />
                      Clinical Information
                    </h4>
                    {expandedSections.clinical ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                  </button>

                  {expandedSections.clinical && (
                    <div className="mt-3 grid grid-cols-2 gap-4">
                      {order.order_details.wound_type && (
                        <div>
                          <p className="text-xs font-medium text-gray-500">Wound Type</p>
                          <p className="text-sm text-gray-900">{order.order_details.wound_type}</p>
                        </div>
                      )}
                      {order.order_details.wound_location && (
                        <div>
                          <p className="text-xs font-medium text-gray-500">Location</p>
                          <p className="text-sm text-gray-900">{order.order_details.wound_location}</p>
                        </div>
                      )}
                      {order.order_details.wound_size && (
                        <div>
                          <p className="text-xs font-medium text-gray-500">Size</p>
                          <p className="text-sm text-gray-900">{order.order_details.wound_size}</p>
                        </div>
                      )}
                      {order.order_details.wound_duration && (
                        <div>
                          <p className="text-xs font-medium text-gray-500">Duration</p>
                          <p className="text-sm text-gray-900">{order.order_details.wound_duration}</p>
                        </div>
                      )}
                      <div>
                        <p className="text-xs font-medium text-gray-500">Expected Service Date</p>
                        <p className="text-sm text-gray-900">{formatDate(order.expected_service_date)}</p>
                      </div>
                    </div>
                  )}
                </div>
              </div>
            </div>

            {/* Supporting Documents */}
            <div className="bg-white rounded-lg border border-gray-200 p-6">
              <button
                onClick={() => toggleSection('documents')}
                className="flex items-center justify-between w-full text-left mb-4"
              >
                <h3 className="text-lg font-medium text-gray-900 flex items-center">
                  <Paperclip className="w-5 h-5 mr-2 text-gray-500" />
                  Supporting Documents ({order.documents?.length || 0})
                </h3>
                {expandedSections.documents ? <ChevronUp className="w-5 h-5" /> : <ChevronDown className="w-5 h-5" />}
              </button>

              {expandedSections.documents && (
                <div className="space-y-2">
                  {order.documents?.length > 0 ? (
                    order.documents.map((doc) => (
                      <div key={doc.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div className="flex items-center space-x-3">
                          <FileImage className="w-5 h-5 text-gray-400" />
                          <div>
                            <p className="text-sm font-medium text-gray-900">{doc.name}</p>
                            <p className="text-xs text-gray-500">Uploaded {formatDate(doc.uploaded_at)}</p>
                          </div>
                        </div>
                        <button className="text-blue-600 hover:text-blue-800 text-sm font-medium">
                          <Download className="w-4 h-4" />
                        </button>
                      </div>
                    ))
                  ) : (
                    <p className="text-sm text-gray-500 text-center py-4">No documents uploaded</p>
                  )}
                </div>
              )}
            </div>

            {/* Action History */}
            <div className="bg-white rounded-lg border border-gray-200 p-6">
              <button
                onClick={() => toggleSection('history')}
                className="flex items-center justify-between w-full text-left mb-4"
              >
                <h3 className="text-lg font-medium text-gray-900 flex items-center">
                  <History className="w-5 h-5 mr-2 text-gray-500" />
                  Action History
                </h3>
                {expandedSections.history ? <ChevronUp className="w-5 h-5" /> : <ChevronDown className="w-5 h-5" />}
              </button>

              {expandedSections.history && (
                <div className="space-y-3">
                  {order.action_history?.length > 0 ? (
                    order.action_history.map((action) => (
                      <div key={action.id} className="flex space-x-3">
                        <div className="flex-shrink-0">
                          <div className="h-8 w-8 bg-gray-100 rounded-full flex items-center justify-center">
                            <Clock className="w-4 h-4 text-gray-500" />
                          </div>
                        </div>
                        <div className="flex-1">
                          <p className="text-sm text-gray-900">
                            <span className="font-medium">{action.actor}</span> {action.action}
                          </p>
                          {action.notes && (
                            <p className="text-sm text-gray-600 mt-1">{action.notes}</p>
                          )}
                          <p className="text-xs text-gray-500 mt-1">{formatDate(action.timestamp)}</p>
                        </div>
                      </div>
                    ))
                  ) : (
                    <p className="text-sm text-gray-500 text-center py-4">No actions recorded yet</p>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* IVR Generation Modal */}
      {showIvrModal && (
        <div className="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-lg max-w-md w-full p-6">
            <h3 className="text-lg font-medium text-gray-900 mb-4">Generate IVR Document</h3>

            <div className="mb-4">
              <p className="text-sm text-gray-600 mb-4">
                Does this order require an IVR confirmation from the manufacturer?
              </p>

              <div className="space-y-3">
                <label className="flex items-center">
                  <input
                    type="radio"
                    checked={ivrRequired}
                    onChange={() => setIvrRequired(true)}
                    className="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300"
                  />
                  <span className="ml-2 text-sm text-gray-700">IVR Required (default)</span>
                </label>

                <label className="flex items-center">
                  <input
                    type="radio"
                    checked={!ivrRequired}
                    onChange={() => setIvrRequired(false)}
                    className="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300"
                  />
                  <span className="ml-2 text-sm text-gray-700">IVR Not Required</span>
                </label>
              </div>
            </div>

            {!ivrRequired && (
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Justification (Required)
                </label>
                <textarea
                  value={ivrJustification}
                  onChange={(e) => setIvrJustification(e.target.value)}
                  rows={3}
                  className="w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm"
                  placeholder="Please explain why IVR is not required for this order..."
                />
              </div>
            )}

            <div className="flex justify-end space-x-3">
              <button
                onClick={() => {
                  setShowIvrModal(false);
                  setIvrJustification('');
                  setIvrRequired(true);
                }}
                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
              >
                Cancel
              </button>
              <button
                onClick={handleGenerateIvr}
                className="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700"
              >
                {ivrRequired ? 'Generate IVR' : 'Skip IVR & Continue'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Action Modals (Approve/Send Back/Deny) */}
      {showActionModal && (
        <div className="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-lg max-w-md w-full p-6">
            <h3 className="text-lg font-medium text-gray-900 mb-4">
              {showActionModal === 'approve' && 'Approve Order'}
              {showActionModal === 'send_back' && 'Send Order Back'}
              {showActionModal === 'deny' && 'Deny Order'}
            </h3>

            <div className="mb-4">
              <label className="block text-sm font-medium text-gray-700 mb-1">
                {showActionModal === 'approve' && 'Approval Notes (Optional)'}
                {showActionModal === 'send_back' && 'Comments (Required)'}
                {showActionModal === 'deny' && 'Reason for Denial (Required)'}
              </label>
              <textarea
                value={actionNotes}
                onChange={(e) => setActionNotes(e.target.value)}
                rows={4}
                className="w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm"
                placeholder={
                  showActionModal === 'approve'
                    ? 'Add any notes about the approval...'
                    : showActionModal === 'send_back'
                    ? 'Explain what needs to be corrected...'
                    : 'Provide reason for denying this order...'
                }
              />
            </div>

            {showActionModal === 'approve' && (
              <div className="mb-4 p-3 bg-blue-50 rounded-md">
                <p className="text-sm text-blue-800">
                  This order will be submitted to <strong>{order.manufacturer?.name || 'Unknown Manufacturer'}</strong> after approval.
                </p>
              </div>
            )}

            <div className="flex justify-end space-x-3">
              <button
                onClick={() => {
                  setShowActionModal(null);
                  setActionNotes('');
                }}
                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
              >
                Cancel
              </button>
              <button
                onClick={() => handleOrderAction(showActionModal)}
                className={`px-4 py-2 text-sm font-medium text-white border border-transparent rounded-md
                  ${showActionModal === 'approve' ? 'bg-green-600 hover:bg-green-700' : ''}
                  ${showActionModal === 'send_back' ? 'bg-orange-600 hover:bg-orange-700' : ''}
                  ${showActionModal === 'deny' ? 'bg-red-600 hover:bg-red-700' : ''}
                `}
              >
                Confirm {showActionModal === 'approve' ? 'Approval' : showActionModal === 'send_back' ? 'Send Back' : 'Denial'}
              </button>
            </div>
          </div>
        </div>
      )}
    </MainLayout>
  );
};

export default OrderShow;
