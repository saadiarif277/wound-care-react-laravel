import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
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
  Calendar,
  Heart,
  Mail,
  Phone,
  Pill,
  Stethoscope,
  Eye,
  Download,
  MessageCircle,
  Bell,
  Info,
  ShieldCheck,
  Truck,
  DollarSign,
  MapPin,
  ChevronDown,
  ChevronUp,
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

const statusConfig = {
  ready_for_review: {
    color: 'bg-blue-100 text-blue-800 border-blue-300',
    icon: Clock,
    label: 'Ready for Review',
    description: 'Episode is being reviewed by MSC admin team'
  },
  ivr_sent: {
    color: 'bg-yellow-100 text-yellow-800 border-yellow-300',
    icon: FileText,
    label: 'IVR Sent',
    description: 'Insurance verification request has been sent to manufacturer'
  },
  ivr_verified: {
    color: 'bg-green-100 text-green-800 border-green-300',
    icon: CheckCircle,
    label: 'IVR Verified',
    description: 'Insurance verification has been confirmed'
  },
  sent_to_manufacturer: {
    color: 'bg-purple-100 text-purple-800 border-purple-300',
    icon: Package,
    label: 'Processing',
    description: 'Orders are being processed by manufacturer'
  },
  tracking_added: {
    color: 'bg-indigo-100 text-indigo-800 border-indigo-300',
    icon: Truck,
    label: 'Shipped',
    description: 'Orders have been shipped and tracking information is available'
  },
  completed: {
    color: 'bg-green-100 text-green-800 border-green-300',
    icon: CheckCircle,
    label: 'Completed',
    description: 'All orders in this episode have been successfully delivered'
  },
};

const ivrStatusConfig = {
  pending: {
    color: 'bg-gray-100 text-gray-800 border-gray-300',
    icon: Clock,
    label: 'Pending IVR',
    description: 'Waiting for insurance verification'
  },
  verified: {
    color: 'bg-green-100 text-green-800 border-green-300',
    icon: ShieldCheck,
    label: 'Verified',
    description: 'Insurance verification completed successfully'
  },
  expired: {
    color: 'bg-red-100 text-red-800 border-red-300',
    icon: AlertTriangle,
    label: 'Expired',
    description: 'Insurance verification has expired and needs renewal'
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
    icon: FileText,
    label: 'IVR Sent'
  },
  ivr_confirmed: {
    color: 'bg-purple-100 text-purple-800',
    icon: CheckCircle,
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
    label: 'Processing'
  },
  shipped: {
    color: 'bg-indigo-100 text-indigo-800',
    icon: Truck,
    label: 'Shipped'
  },
  delivered: {
    color: 'bg-green-100 text-green-800',
    icon: CheckCircle,
    label: 'Delivered'
  },
};

const ProviderEpisodeShow: React.FC<ProviderEpisodeShowProps> = ({
  episode,
  can_view_episode,
  can_view_tracking,
  can_view_documents,
}) => {
  const [expandedSections, setExpandedSections] = useState({
    timeline: true,
    orders: true,
    documents: true,
    communication: false,
  });

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
    });
  };

  const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const toggleSection = (section: keyof typeof expandedSections) => {
    setExpandedSections(prev => ({
      ...prev,
      [section]: !prev[section]
    }));
  };

  const currentStatus = statusConfig[episode.status as keyof typeof statusConfig] || {
    color: 'bg-gray-100 text-gray-800 border-gray-300',
    icon: FileText,
    label: episode.status || 'Unknown',
    description: 'Episode status is being updated'
  };
  const StatusIcon = currentStatus.icon;

  const currentIvrStatus = ivrStatusConfig[episode.ivr_status as keyof typeof ivrStatusConfig] || {
    color: 'bg-gray-100 text-gray-800 border-gray-300',
    icon: Clock,
    label: episode.ivr_status || 'Unknown',
    description: 'IVR status is being updated'
  };
  const IvrStatusIcon = currentIvrStatus.icon;

  const getOrderStatusBadge = (status: string) => {
    const config = orderStatusConfig[status as keyof typeof orderStatusConfig];
    if (!config) return <Badge variant="secondary">{status}</Badge>;

    const Icon = config.icon;
    return (
      <Badge className={`${config.color} flex items-center space-x-1`}>
        <Icon className="w-3 h-3" />
        <span>{config.label}</span>
      </Badge>
    );
  };

  if (!can_view_episode) {
    return (
      <MainLayout title="Access Denied">
        <div className="py-12 text-center">
          <AlertTriangle className="w-16 h-16 text-red-500 mx-auto mb-4" />
          <h2 className="text-2xl font-bold text-gray-900 mb-2">Access Denied</h2>
          <p className="text-gray-600 mb-4">You don't have permission to view this episode.</p>
          <Link href="/provider/orders">
            <Button>Return to Orders</Button>
          </Link>
        </div>
      </MainLayout>
    );
  }

  return (
    <MainLayout title={`Episode: ${episode.patient_name || episode.patient_display_id}`}>
      <Head title={`Episode: ${episode.patient_name || episode.patient_display_id}`} />

      <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* Header */}
        <div className="mb-8">
          <div className="flex items-center space-x-4 mb-4">
            <Link href="/provider/orders" className="text-gray-500 hover:text-gray-700">
              <ArrowLeft className="w-6 h-6" />
            </Link>
            <div className="flex-1">
              <h1 className="text-3xl font-bold text-gray-900 flex items-center space-x-3">
                <Heart className="w-8 h-8 text-blue-600" />
                <span>Patient Episode</span>
                <Badge variant="outline" className="text-xs">
                  Provider View
                </Badge>
              </h1>
              <p className="mt-2 text-sm text-gray-600">
                View episode details, order status, and communication history
              </p>
            </div>
          </div>

          {/* Episode Status Alert */}
          <Card className={`border-2 ${currentStatus.color.split(' ')[0]}-200 ${currentStatus.color.split(' ')[0]}-50`}>
            <CardContent className="p-4">
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                  <StatusIcon className="w-6 h-6" />
                  <div>
                    <h3 className="font-semibold text-lg">{currentStatus.label}</h3>
                    <p className="text-sm text-gray-600">{currentStatus.description}</p>
                  </div>
                </div>
                {episode.action_required && (
                  <Badge variant="destructive" className="animate-pulse">
                    Action Required
                  </Badge>
                )}
              </div>
            </CardContent>
          </Card>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Left Column - Episode Overview */}
          <div className="space-y-6">
            {/* Patient & Episode Information */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <User className="w-5 h-5 text-blue-600" />
                  <span>Patient Episode</span>
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div>
                  <label className="text-sm font-medium text-gray-500">Patient</label>
                  <p className="text-lg font-semibold text-gray-900">
                    {episode.patient_name || episode.patient_display_id}
                  </p>
                  <p className="text-sm text-gray-600">ID: {episode.patient_display_id}</p>
                </div>

                <div>
                  <label className="text-sm font-medium text-gray-500">Manufacturer</label>
                  <div className="flex items-center space-x-2">
                    <Building2 className="w-4 h-4 text-gray-400" />
                    <p className="text-lg font-semibold text-gray-900">{episode.manufacturer.name}</p>
                  </div>
                  {episode.manufacturer.contact_email && (
                    <div className="flex items-center space-x-2 mt-1">
                      <Mail className="w-3 h-3 text-gray-400" />
                      <p className="text-sm text-gray-600">{episode.manufacturer.contact_email}</p>
                    </div>
                  )}
                  {episode.manufacturer.contact_phone && (
                    <div className="flex items-center space-x-2 mt-1">
                      <Phone className="w-3 h-3 text-gray-400" />
                      <p className="text-sm text-gray-600">{episode.manufacturer.contact_phone}</p>
                    </div>
                  )}
                </div>

                <div className="grid grid-cols-2 gap-4 pt-4 border-t">
                  <div>
                    <label className="text-sm font-medium text-gray-500">Total Orders</label>
                    <p className="text-2xl font-bold text-blue-600">{episode.orders_count}</p>
                  </div>
                  <div>
                    <label className="text-sm font-medium text-gray-500">Total Value</label>
                    <p className="text-2xl font-bold text-green-600">{formatCurrency(episode.total_order_value)}</p>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* IVR Status */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <ShieldCheck className="w-5 h-5 text-green-600" />
                  <span>Insurance Verification</span>
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex items-center space-x-3">
                  <IvrStatusIcon className="w-6 h-6" />
                  <div>
                    <Badge className={`${currentIvrStatus.color} text-sm px-3 py-1`}>
                      {currentIvrStatus.label}
                    </Badge>
                    <p className="text-sm text-gray-600 mt-1">{currentIvrStatus.description}</p>
                  </div>
                </div>

                {episode.verification_date && (
                  <div>
                    <label className="text-sm font-medium text-gray-500">Verified Date</label>
                    <p className="text-sm text-gray-900">{formatDate(episode.verification_date)}</p>
                  </div>
                )}

                {episode.expiration_date && (
                  <div>
                    <label className="text-sm font-medium text-gray-500">Expiration Date</label>
                    <p className="text-sm text-gray-900">{formatDate(episode.expiration_date)}</p>
                    {new Date(episode.expiration_date) < new Date() && (
                      <Badge variant="destructive" className="text-xs mt-1">
                        Expired
                      </Badge>
                    )}
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Quick Actions */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <Activity className="w-5 h-5 text-purple-600" />
                  <span>Quick Actions</span>
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <Button variant="outline" className="w-full justify-start" size="sm">
                  <MessageCircle className="w-4 h-4 mr-2" />
                  Contact Support
                </Button>
                <Button variant="outline" className="w-full justify-start" size="sm">
                  <Download className="w-4 h-4 mr-2" />
                  Download Summary
                </Button>
                <Button variant="outline" className="w-full justify-start" size="sm">
                  <Bell className="w-4 h-4 mr-2" />
                  Set Notifications
                </Button>
              </CardContent>
            </Card>
          </div>

          {/* Middle Column - Orders */}
          <div className="space-y-6">
            <Card>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <CardTitle className="flex items-center space-x-2">
                    <Package className="w-5 h-5 text-indigo-600" />
                    <span>Orders in Episode ({episode.orders_count})</span>
                  </CardTitle>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => toggleSection('orders')}
                  >
                    {expandedSections.orders ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                  </Button>
                </div>
              </CardHeader>
              {expandedSections.orders && (
                <CardContent className="space-y-4">
                  {episode.orders.map((order) => (
                    <Card key={order.id} className="border border-gray-200">
                      <CardContent className="p-4">
                        <div className="space-y-3">
                          {/* Order Header */}
                          <div className="flex items-center justify-between">
                            <div>
                              <h4 className="font-semibold text-gray-900">#{order.order_number}</h4>
                              <p className="text-sm text-gray-600">
                                Submitted: {formatDate(order.submitted_at)}
                              </p>
                            </div>
                            {getOrderStatusBadge(order.order_status)}
                          </div>

                          {/* Order Details */}
                          <div className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                              <label className="font-medium text-gray-500">Service Date</label>
                              <p className="text-gray-900">{formatDate(order.expected_service_date)}</p>
                            </div>
                            <div>
                              <label className="font-medium text-gray-500">Order Value</label>
                              <p className="text-gray-900 font-semibold">{formatCurrency(order.total_order_value)}</p>
                            </div>
                          </div>

                          {/* Products */}
                          {order.products && order.products.length > 0 && (
                            <div>
                              <label className="text-sm font-medium text-gray-500">Products</label>
                              <div className="mt-1 space-y-1">
                                {order.products.map((product) => (
                                  <div key={product.id} className="flex items-center justify-between text-sm">
                                    <div className="flex items-center space-x-2">
                                      <Pill className="w-3 h-3 text-gray-400" />
                                      <span className="text-gray-900">{product.name}</span>
                                      <span className="text-gray-500">({product.sku})</span>
                                    </div>
                                    <div className="text-gray-700">
                                      Qty: {product.quantity} × {formatCurrency(product.unit_price)}
                                    </div>
                                  </div>
                                ))}
                              </div>
                            </div>
                          )}

                          {/* Facility Information */}
                          <div className="pt-2 border-t text-sm">
                            <div className="flex items-center space-x-2">
                              <MapPin className="w-3 h-3 text-gray-400" />
                              <span className="text-gray-600">
                                {order.facility.name} - {order.facility.city}, {order.facility.state}
                              </span>
                            </div>
                          </div>

                          {order.action_required && (
                            <div className="pt-2">
                              <Badge variant="destructive" className="text-xs">
                                Action Required
                              </Badge>
                            </div>
                          )}
                        </div>
                      </CardContent>
                    </Card>
                  ))}
                </CardContent>
              )}
            </Card>
          </div>

          {/* Right Column - Documents & Communication */}
          <div className="space-y-6">
            {/* Documents */}
            {can_view_documents && (
              <Card>
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <CardTitle className="flex items-center space-x-2">
                      <FileText className="w-5 h-5 text-green-600" />
                      <span>Documents</span>
                    </CardTitle>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => toggleSection('documents')}
                    >
                      {expandedSections.documents ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                    </Button>
                  </div>
                </CardHeader>
                {expandedSections.documents && (
                  <CardContent className="space-y-4">
                    {episode.docuseal.status && (
                      <div className="p-3 bg-blue-50 rounded-lg">
                        <div className="flex items-center space-x-2">
                          <Info className="w-4 h-4 text-blue-600" />
                          <span className="text-sm font-medium text-blue-900">
                            Document Status: {episode.docuseal.status}
                          </span>
                        </div>
                        {episode.docuseal.last_synced_at && (
                          <p className="text-xs text-blue-700 mt-1">
                            Last updated: {formatDateTime(episode.docuseal.last_synced_at)}
                          </p>
                        )}
                      </div>
                    )}

                    {episode.docuseal.signed_documents && episode.docuseal.signed_documents.length > 0 ? (
                      <div className="space-y-2">
                        {episode.docuseal.signed_documents.map((doc) => (
                          <div key={doc.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div className="flex items-center space-x-2">
                              <FileText className="w-4 h-4 text-gray-600" />
                              <span className="text-sm font-medium text-gray-900">
                                {doc.name || doc.filename || 'Document'}
                              </span>
                            </div>
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => window.open(doc.url, '_blank')}
                            >
                              <ExternalLink className="w-3 h-3" />
                            </Button>
                          </div>
                        ))}
                      </div>
                    ) : (
                      <div className="text-center py-6 text-gray-500">
                        <FileText className="w-8 h-8 mx-auto mb-2 text-gray-400" />
                        <p className="text-sm">No documents available yet</p>
                      </div>
                    )}

                    {episode.docuseal.audit_log_url && (
                      <Button
                        variant="outline"
                        className="w-full"
                        onClick={() => window.open(episode.docuseal.audit_log_url, '_blank')}
                      >
                        <Eye className="w-4 h-4 mr-2" />
                        View Audit Log
                      </Button>
                    )}
                  </CardContent>
                )}
              </Card>
            )}

            {/* Episode Timeline */}
            <Card>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <CardTitle className="flex items-center space-x-2">
                    <Clock className="w-5 h-5 text-orange-600" />
                    <span>Episode Timeline</span>
                  </CardTitle>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => toggleSection('timeline')}
                  >
                    {expandedSections.timeline ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                  </Button>
                </div>
              </CardHeader>
              {expandedSections.timeline && (
                <CardContent>
                  <div className="space-y-4">
                    {/* Timeline items would be generated based on episode history */}
                    <div className="flex items-start space-x-3">
                      <div className="flex-shrink-0 w-2 h-2 bg-green-500 rounded-full mt-2"></div>
                      <div>
                        <p className="text-sm font-medium text-gray-900">Episode Created</p>
                        <p className="text-xs text-gray-500">First order submitted to episode</p>
                      </div>
                    </div>

                    {episode.verification_date && (
                      <div className="flex items-start space-x-3">
                        <div className="flex-shrink-0 w-2 h-2 bg-blue-500 rounded-full mt-2"></div>
                        <div>
                          <p className="text-sm font-medium text-gray-900">IVR Verified</p>
                          <p className="text-xs text-gray-500">{formatDate(episode.verification_date)}</p>
                        </div>
                      </div>
                    )}

                    <div className="text-center py-4 text-sm text-gray-500">
                      More timeline events will appear as the episode progresses
                    </div>
                  </div>
                </CardContent>
              )}
            </Card>

            {/* Communication Hub */}
            <Card>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <CardTitle className="flex items-center space-x-2">
                    <MessageCircle className="w-5 h-5 text-purple-600" />
                    <span>Communication</span>
                  </CardTitle>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => toggleSection('communication')}
                  >
                    {expandedSections.communication ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                  </Button>
                </div>
              </CardHeader>
              {expandedSections.communication && (
                <CardContent className="space-y-4">
                  <div className="text-center py-6 text-gray-500">
                    <MessageCircle className="w-8 h-8 mx-auto mb-2 text-gray-400" />
                    <p className="text-sm">No messages yet</p>
                    <p className="text-xs">Communication history will appear here</p>
                  </div>

                  <Button variant="outline" className="w-full">
                    <MessageCircle className="w-4 h-4 mr-2" />
                    Contact MSC Support
                  </Button>
                </CardContent>
              )}
            </Card>
          </div>
        </div>
      </div>
    </MainLayout>
  );
};

export default ProviderEpisodeShow;
