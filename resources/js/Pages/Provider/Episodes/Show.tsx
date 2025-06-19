import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Button } from '@/Components/ui/button';
import {
  ArrowLeft,
  Heart,
  CheckCircle,
  Clock,
  Package,
  FileText,
  Truck,
  AlertTriangle,
  Calendar,
  DollarSign,
  User,
  Building2,
  Phone,
  Mail,
  Download,
  MessageCircle,
  Eye,
  ShieldCheck,
  Activity,
  AlertCircle,
  Copy,
  Check,
  ExternalLink,
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
}

interface ProviderEpisodeShowProps {
  episode: Episode;
  can_view_episode: boolean;
  can_view_tracking: boolean;
  can_view_documents: boolean;
}

// Clean status configuration
const statusConfig = {
  ready_for_review: {
    color: 'from-blue-500 to-blue-600',
    bgColor: 'bg-blue-50',
    textColor: 'text-blue-800',
    icon: Clock,
    title: 'Under Review',
    message: 'Episode is being reviewed by our clinical team',
    progress: 25,
  },
  ivr_verified: {
    color: 'from-green-500 to-emerald-500',
    bgColor: 'bg-green-50',
    textColor: 'text-green-800',
    icon: CheckCircle,
    title: 'Insurance Verified',
    message: 'Insurance verification completed successfully',
    progress: 50,
  },
  sent_to_manufacturer: {
    color: 'from-purple-500 to-indigo-500',
    bgColor: 'bg-purple-50',
    textColor: 'text-purple-800',
    icon: Package,
    title: 'In Production',
    message: 'Orders are being processed by manufacturer',
    progress: 75,
  },
  tracking_added: {
    color: 'from-indigo-500 to-blue-500',
    bgColor: 'bg-indigo-50',
    textColor: 'text-indigo-800',
    icon: Truck,
    title: 'Shipped',
    message: 'Orders have been shipped with tracking',
    progress: 90,
  },
  completed: {
    color: 'from-green-500 to-teal-500',
    bgColor: 'bg-green-50',
    textColor: 'text-green-800',
    icon: CheckCircle,
    title: 'Completed',
    message: 'All orders delivered successfully',
    progress: 100,
  },
};

const ivrStatusConfig = {
  pending: { icon: Clock, color: 'text-amber-600', label: 'Pending Verification' },
  verified: { icon: ShieldCheck, color: 'text-green-600', label: 'Insurance Verified' },
  expired: { icon: AlertTriangle, color: 'text-red-600', label: 'Verification Expired' },
};

const ProviderEpisodeShow: React.FC<ProviderEpisodeShowProps> = ({
  episode,
  can_view_episode,
  can_view_tracking,
  can_view_documents,
}) => {
  const [copied, setCopied] = useState(false);

  const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(amount || 0);
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  };

  const copyEpisodeId = async () => {
    await navigator.clipboard.writeText(episode.id);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  if (!can_view_episode) {
    return (
      <MainLayout title="Access Denied">
        <div className="min-h-screen flex items-center justify-center">
          <div className="text-center">
            <AlertTriangle className="w-16 h-16 text-red-500 mx-auto mb-4" />
            <h2 className="text-2xl font-semibold text-gray-900 mb-2">Access Denied</h2>
            <p className="text-gray-600 mb-6">You don't have permission to view this episode.</p>
            <Link href="/provider/orders">
              <Button>Return to Orders</Button>
            </Link>
          </div>
        </div>
      </MainLayout>
    );
  }

  const currentStatus = statusConfig[episode.status] || statusConfig.ready_for_review;
  const StatusIcon = currentStatus.icon;
  const ivrStatus = ivrStatusConfig[episode.ivr_status] || ivrStatusConfig.pending;
  const IvrIcon = ivrStatus.icon;

  return (
    <MainLayout title={`Episode: ${episode.patient_name || episode.patient_display_id}`}>
      <Head title={`Episode: ${episode.patient_name || episode.patient_display_id}`} />

      <div className="min-h-screen bg-gray-50">
        {/* Header */}
        <div className="bg-white border-b border-gray-200 sticky top-0 z-10">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="flex items-center justify-between h-16">
              <div className="flex items-center space-x-4">
                <Link
                  href="/provider/orders"
                  className="text-gray-500 hover:text-gray-700 transition-colors"
                >
                  <ArrowLeft className="w-5 h-5" />
                </Link>
                <div className="flex items-center space-x-3">
                  <Heart className="w-6 h-6 text-purple-600" />
                  <div>
                    <h1 className="text-xl font-semibold text-gray-900">
                      {episode.patient_name || episode.patient_display_id}
                    </h1>
                    <p className="text-sm text-gray-500">
                      Episode with {episode.manufacturer.name}
                    </p>
                  </div>
                </div>
              </div>
              <div className="flex items-center space-x-3">
                <button
                  onClick={copyEpisodeId}
                  className="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"
                >
                  {copied ? <Check className="w-4 h-4 mr-2 text-green-600" /> : <Copy className="w-4 h-4 mr-2" />}
                  {copied ? 'Copied!' : 'Copy Episode ID'}
                </button>
                <Button variant="outline">
                  <Download className="w-4 h-4 mr-2" />
                  Download
                </Button>
                <Button>
                  <MessageCircle className="w-4 h-4 mr-2" />
                  Get Help
                </Button>
              </div>
            </div>
          </div>
        </div>

        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          {/* Status Banner */}
          <div className={`${currentStatus.bgColor} border border-gray-200 rounded-xl mb-8 overflow-hidden`}>
            <div className="p-6">
              <div className="flex items-start justify-between">
                <div className="flex items-center space-x-4">
                  <div className={`w-12 h-12 bg-gradient-to-r ${currentStatus.color} rounded-lg flex items-center justify-center`}>
                    <StatusIcon className="w-6 h-6 text-white" />
                  </div>
                  <div>
                    <h2 className={`text-2xl font-bold ${currentStatus.textColor}`}>
                      {currentStatus.title}
                    </h2>
                    <p className="text-gray-700 mt-1">{currentStatus.message}</p>
                    {episode.action_required && (
                      <div className="mt-2">
                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 animate-pulse">
                          Action Required
                        </span>
                      </div>
                    )}
                  </div>
                </div>
                <div className="text-right">
                  <div className={`text-3xl font-bold ${currentStatus.textColor}`}>
                    {currentStatus.progress}%
                  </div>
                  <div className="text-sm text-gray-600">Complete</div>
                </div>
              </div>

              {/* Progress Bar */}
              <div className="mt-6">
                <div className="w-full bg-white/60 rounded-full h-2">
                  <div
                    className={`h-2 rounded-full bg-gradient-to-r ${currentStatus.color} transition-all duration-1000`}
                    style={{ width: `${currentStatus.progress}%` }}
                  />
                </div>
              </div>
            </div>
          </div>

          {/* Main Content */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {/* Primary Content */}
            <div className="lg:col-span-2 space-y-6">

              {/* Episode Overview */}
              <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div className="px-6 py-4 border-b border-gray-200">
                  <h3 className="text-lg font-semibold text-gray-900">Episode Overview</h3>
                </div>
                <div className="p-6">
                  <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div className="text-center">
                      <div className="flex items-center justify-center w-12 h-12 bg-purple-100 rounded-lg mx-auto mb-3">
                        <Package className="w-6 h-6 text-purple-600" />
                      </div>
                      <div className="text-2xl font-bold text-gray-900">{episode.orders_count}</div>
                      <div className="text-sm text-gray-500">Total Orders</div>
                    </div>
                    <div className="text-center">
                      <div className="flex items-center justify-center w-12 h-12 bg-green-100 rounded-lg mx-auto mb-3">
                        <DollarSign className="w-6 h-6 text-green-600" />
                      </div>
                      <div className="text-2xl font-bold text-gray-900">
                        {formatCurrency(episode.total_order_value)}
                      </div>
                      <div className="text-sm text-gray-500">Total Value</div>
                    </div>
                    <div className="text-center">
                      <div className="flex items-center justify-center w-12 h-12 bg-blue-100 rounded-lg mx-auto mb-3">
                        <Building2 className="w-6 h-6 text-blue-600" />
                      </div>
                      <div className="text-lg font-semibold text-gray-900">
                        {episode.manufacturer.name}
                      </div>
                      <div className="text-sm text-gray-500">Manufacturer</div>
                    </div>
                    <div className="text-center">
                      <div className="flex items-center justify-center w-12 h-12 bg-indigo-100 rounded-lg mx-auto mb-3">
                        <IvrIcon className={`w-6 h-6 ${ivrStatus.color}`} />
                      </div>
                      <div className="text-sm font-semibold text-gray-900">
                        {ivrStatus.label}
                      </div>
                      <div className="text-sm text-gray-500">Insurance Status</div>
                    </div>
                  </div>
                </div>
              </div>

              {/* Orders in Episode */}
              <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div className="px-6 py-4 border-b border-gray-200">
                  <h3 className="text-lg font-semibold text-gray-900">Orders in This Episode</h3>
                </div>
                <div className="divide-y divide-gray-200">
                  {episode.orders.map((order) => (
                    <div key={order.id} className="p-6 hover:bg-gray-50 transition-colors">
                      <div className="flex items-center justify-between">
                        <div className="flex-1">
                          <div className="flex items-center space-x-4">
                            <div>
                              <h4 className="font-semibold text-gray-900">#{order.order_number}</h4>
                              <p className="text-sm text-gray-500">
                                {formatDate(order.submitted_at)}
                              </p>
                            </div>
                            <div className="text-center">
                              <div className="text-lg font-bold text-gray-900">
                                {formatCurrency(order.total_order_value)}
                              </div>
                              <div className="text-xs text-gray-500">Order Value</div>
                            </div>
                            <div className="text-center">
                              <div className="text-lg font-bold text-gray-900">
                                {order.products?.length || 0}
                              </div>
                              <div className="text-xs text-gray-500">Products</div>
                            </div>
                          </div>
                        </div>
                        <div className="flex items-center space-x-3">
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.visit(`/provider/orders/${order.id}`)}
                          >
                            <Eye className="w-4 h-4 mr-2" />
                            View Order
                          </Button>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              {/* Documents */}
              {can_view_documents && episode.docuseal.signed_documents && episode.docuseal.signed_documents.length > 0 && (
                <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                  <div className="px-6 py-4 border-b border-gray-200">
                    <h3 className="text-lg font-semibold text-gray-900">Episode Documents</h3>
                  </div>
                  <div className="p-6">
                    <div className="space-y-3">
                      {episode.docuseal.signed_documents.map((doc) => (
                        <div
                          key={doc.id}
                          className="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                        >
                          <div className="flex items-center space-x-3">
                            <FileText className="w-5 h-5 text-gray-600" />
                            <span className="font-medium text-gray-900">
                              {doc.name || doc.filename || 'Document'}
                            </span>
                          </div>
                          <div className="flex items-center space-x-2">
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => window.open(doc.url, '_blank')}
                            >
                              <Eye className="w-4 h-4 mr-2" />
                              View
                            </Button>
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => {
                                const link = document.createElement('a');
                                link.href = doc.url;
                                link.download = doc.filename || 'document';
                                link.click();
                              }}
                            >
                              <Download className="w-4 h-4 mr-2" />
                              Download
                            </Button>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              )}
            </div>

            {/* Sidebar */}
            <div className="space-y-6">

              {/* Patient Information */}
              <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div className="px-4 py-3 border-b border-gray-200">
                  <h3 className="font-medium text-gray-900">Patient Information</h3>
                </div>
                <div className="p-4">
                  <div className="space-y-3">
                    <div>
                      <div className="text-sm text-gray-500">Patient ID</div>
                      <div className="font-medium text-gray-900">{episode.patient_display_id}</div>
                    </div>
                    {episode.patient_name && (
                      <div>
                        <div className="text-sm text-gray-500">Patient Name</div>
                        <div className="font-medium text-gray-900">{episode.patient_name}</div>
                      </div>
                    )}
                  </div>
                </div>
              </div>

              {/* Manufacturer Contact */}
              <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div className="px-4 py-3 border-b border-gray-200">
                  <h3 className="font-medium text-gray-900">Manufacturer</h3>
                </div>
                <div className="p-4">
                  <div className="space-y-3">
                    <div>
                      <div className="font-medium text-gray-900">{episode.manufacturer.name}</div>
                    </div>
                    {episode.manufacturer.contact_email && (
                      <div className="flex items-center space-x-2 text-sm">
                        <Mail className="w-4 h-4 text-gray-500" />
                        <span className="text-gray-900">{episode.manufacturer.contact_email}</span>
                      </div>
                    )}
                    {episode.manufacturer.contact_phone && (
                      <div className="flex items-center space-x-2 text-sm">
                        <Phone className="w-4 h-4 text-gray-500" />
                        <span className="text-gray-900">{episode.manufacturer.contact_phone}</span>
                      </div>
                    )}
                  </div>
                </div>
              </div>

              {/* Key Dates */}
              {(episode.verification_date || episode.expiration_date) && (
                <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                  <div className="px-4 py-3 border-b border-gray-200">
                    <h3 className="font-medium text-gray-900">Important Dates</h3>
                  </div>
                  <div className="p-4 space-y-3">
                    {episode.verification_date && (
                      <div>
                        <div className="text-sm text-gray-500">Verified</div>
                        <div className="font-medium text-gray-900">{formatDate(episode.verification_date)}</div>
                      </div>
                    )}
                    {episode.expiration_date && (
                      <div>
                        <div className="text-sm text-gray-500">Expires</div>
                        <div className={`font-medium ${new Date(episode.expiration_date) < new Date() ? 'text-red-600' : 'text-gray-900'}`}>
                          {formatDate(episode.expiration_date)}
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              )}

              {/* Contact Support */}
              <div className="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-200 p-4">
                <h3 className="font-semibold text-blue-900 mb-2">Need Help?</h3>
                <p className="text-sm text-blue-700 mb-4">
                  Our support team is here to help with any questions about your episode.
                </p>
                <div className="space-y-2">
                  <Button variant="outline" size="sm" className="w-full border-blue-300 text-blue-700 hover:bg-blue-100">
                    <Phone className="w-4 h-4 mr-2" />
                    Call Support
                  </Button>
                  <Button variant="outline" size="sm" className="w-full border-blue-300 text-blue-700 hover:bg-blue-100">
                    <Mail className="w-4 h-4 mr-2" />
                    Email Support
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
};

export default ProviderEpisodeShow;
