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
  AlertCircle,
  Layers,
  Timer,
  Star,
  Sparkles,
  HelpCircle,
  FileCheck,
  Users,
  Play,
  Pause,
  Copy,
  Share2,
  BookOpen,
  Shield,
  Zap,
  Target,
  TrendingUp,
  ChevronRight,
  RefreshCw,
} from 'lucide-react';

// Enhanced 2025 healthcare status configuration with patient-friendly messaging
const statusConfig = {
  ready_for_review: {
    color: 'bg-gradient-to-r from-blue-50 to-indigo-50 text-blue-800 border-blue-200',
    icon: Clock,
    label: 'Under Review',
    description: 'Your order is being carefully reviewed by our clinical team',
    patientFriendly: 'We are reviewing your order details and will contact you soon with next steps',
    progressPercent: 10,
    estimatedTime: '1-2 business days',
    nextStep: 'Clinical review completion'
  },
  ivr_sent: {
    color: 'bg-gradient-to-r from-yellow-50 to-amber-50 text-yellow-800 border-yellow-200',
    icon: FileText,
    label: 'Documentation Processing',
    description: 'Insurance verification documents are being processed',
    patientFriendly: 'Insurance paperwork has been submitted and is being processed by your insurance provider',
    progressPercent: 35,
    estimatedTime: '3-5 business days',
    nextStep: 'Insurance verification'
  },
  ivr_verified: {
    color: 'bg-gradient-to-r from-green-50 to-emerald-50 text-green-800 border-green-200',
    icon: CheckCircle,
    label: 'Approved & Ready',
    description: 'Insurance verified and order approved for processing',
    patientFriendly: 'Great news! Your insurance has been verified and your order is approved for processing',
    progressPercent: 60,
    estimatedTime: '1-2 business days',
    nextStep: 'Order preparation'
  },
  sent_to_manufacturer: {
    color: 'bg-gradient-to-r from-purple-50 to-violet-50 text-purple-800 border-purple-200',
    icon: Package,
    label: 'In Production',
    description: 'Order is being prepared by the manufacturer',
    patientFriendly: 'Your order is now being carefully prepared by our manufacturing partner',
    progressPercent: 80,
    estimatedTime: '5-7 business days',
    nextStep: 'Quality control & shipping'
  },
  tracking_added: {
    color: 'bg-gradient-to-r from-indigo-50 to-blue-50 text-indigo-800 border-indigo-200',
    icon: Truck,
    label: 'Shipped',
    description: 'Order shipped with tracking information available',
    patientFriendly: 'Your order has been shipped and is on its way to you',
    progressPercent: 90,
    estimatedTime: '2-3 business days',
    nextStep: 'Delivery'
  },
  completed: {
    color: 'bg-gradient-to-r from-green-50 to-teal-50 text-green-800 border-green-200',
    icon: CheckCircle,
    label: 'Delivered',
    description: 'Order has been successfully completed',
    patientFriendly: 'Your order has been completed successfully',
    progressPercent: 100,
    estimatedTime: 'Complete',
    nextStep: 'Follow-up care'
  },
};

// Enhanced IVR status with better patient communication
const ivrStatusConfig = {
  pending: {
    color: 'bg-gradient-to-r from-gray-50 to-slate-50 text-gray-800 border-gray-200',
    icon: Clock,
    label: 'Verification Pending',
    description: 'Insurance verification is being processed',
    patientMessage: 'We are working with your insurance provider to verify coverage'
  },
  verified: {
    color: 'bg-gradient-to-r from-green-50 to-emerald-50 text-green-800 border-green-200',
    icon: ShieldCheck,
    label: 'Coverage Verified',
    description: 'Insurance coverage has been confirmed',
    patientMessage: 'Your insurance coverage has been verified and approved'
  },
  expired: {
    color: 'bg-gradient-to-r from-red-50 to-rose-50 text-red-800 border-red-200',
    icon: AlertTriangle,
    label: 'Verification Expired',
    description: 'Insurance verification needs to be renewed',
    patientMessage: 'Please contact us to update your insurance verification'
  },
};

const orderStatusConfig = {
  pending_ivr: { color: 'bg-gray-100 text-gray-800', icon: Clock, label: 'Pending IVR' },
  ivr_sent: { color: 'bg-blue-100 text-blue-800', icon: FileText, label: 'IVR Sent' },
  ivr_confirmed: { color: 'bg-purple-100 text-purple-800', icon: CheckCircle, label: 'IVR Confirmed' },
  approved: { color: 'bg-green-100 text-green-800', icon: CheckCircle, label: 'Approved' },
  sent_back: { color: 'bg-orange-100 text-orange-800', icon: AlertTriangle, label: 'Sent Back' },
  denied: { color: 'bg-red-100 text-red-800', icon: AlertTriangle, label: 'Denied' },
  submitted_to_manufacturer: { color: 'bg-purple-100 text-purple-800', icon: Package, label: 'Processing' },
  shipped: { color: 'bg-indigo-100 text-indigo-800', icon: Truck, label: 'Shipped' },
  delivered: { color: 'bg-green-100 text-green-800', icon: CheckCircle, label: 'Delivered' },
};

const ProviderOrderShow = ({ order }) => {
  const [expandedSections, setExpandedSections] = useState({
    timeline: true,
    episode: true,
    communication: false,
    support: false,
  });

  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(amount);
  };

  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  };

  const formatDateTime = (dateString) => {
    return new Date(dateString).toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    });
  };

  const toggleSection = (section) => {
    setExpandedSections(prev => ({
      ...prev,
      [section]: !prev[section]
    }));
  };

  const getOrderStatusBadge = (status) => {
    const config = statusConfig[status as keyof typeof statusConfig];
    if (!config) return <Badge variant="secondary">{status}</Badge>;
    const Icon = config.icon;
    return (
      <Badge className={`${config.color} flex items-center space-x-2 px-3 py-1.5 rounded-xl shadow-sm border`}>
        <Icon className="w-4 h-4" />
        <span className="font-medium">{config.label}</span>
      </Badge>
    );
  };

  const copyOrderNumber = () => {
    navigator.clipboard.writeText(order.order_number);
    // Add toast notification here
  };

  if (!order) return <div>Loading...</div>;

  // Enhanced episode detection and display
  const episode = order.ivr_episode;
  const hasEpisode = !!episode;

  const currentStatus = statusConfig[order.order_status as keyof typeof statusConfig] ||
    statusConfig[episode?.status as keyof typeof statusConfig] ||
    {
      color: 'bg-gray-100 text-gray-800 border-gray-300',
      icon: FileText,
      label: order.order_status,
      description: 'Order status',
      patientFriendly: 'Your order is being processed',
      progressPercent: 0,
      estimatedTime: 'Calculating...',
      nextStep: 'Processing'
    };

  const currentIvrStatus = episode?.ivr_status ?
    ivrStatusConfig[episode.ivr_status as keyof typeof ivrStatusConfig] : null;

  return (
    <MainLayout title={`Order #${order.order_number}`}>
      <Head title={`Order #${order.order_number}`} />

      <div className="min-h-screen bg-gradient-to-br from-blue-50/30 via-white to-indigo-50/20 py-6">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Enhanced Header with breadcrumb and actions */}
          <div className="mb-8">
            <div className="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg border border-gray-200/50 p-6 relative overflow-hidden">
              {/* Subtle background gradient */}
              <div className="absolute inset-0 opacity-5">
                <div className="absolute inset-0 bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600"></div>
              </div>

              <div className="relative">
                {/* Breadcrumb navigation */}
                <div className="flex items-center space-x-2 text-sm text-gray-500 mb-4">
                  <Link href="/provider/orders" className="hover:text-blue-600 transition-colors flex items-center">
                    <ArrowLeft className="w-4 h-4 mr-1" />
                    Orders
                  </Link>
                  <ChevronRight className="w-4 h-4" />
                  <span className="text-gray-900 font-medium">Order #{order.order_number}</span>
                </div>

                <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                  <div className="flex items-center space-x-4 mb-4 lg:mb-0">
                    <div className="p-3 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl shadow-lg">
                      <Stethoscope className="w-6 h-6 text-white" />
                    </div>
                    <div>
                      <h1 className="text-3xl font-bold bg-gradient-to-r from-gray-900 via-blue-900 to-indigo-900 bg-clip-text text-transparent">
                        Order Details
                      </h1>
                      <div className="flex items-center space-x-3 mt-2">
                        <Badge variant="outline" className="text-xs bg-blue-50 text-blue-700 border-blue-200">
                          Provider View
                        </Badge>
                        {hasEpisode && (
                          <Badge variant="outline" className="text-xs bg-purple-50 text-purple-700 border-purple-200">
                            <Heart className="w-3 h-3 mr-1" />
                            Episode-Based Care
                          </Badge>
                        )}
                      </div>
                    </div>
                  </div>

                  {/* Action buttons */}
                  <div className="flex flex-col sm:flex-row gap-3">
                    <Button
                      variant="outline"
                      onClick={copyOrderNumber}
                      className="bg-white/80 hover:bg-white transition-all duration-200"
                    >
                      <Copy className="w-4 h-4 mr-2" />
                      Copy Order #
                    </Button>

                    <Button
                      variant="outline"
                      className="bg-white/80 hover:bg-white transition-all duration-200"
                    >
                      <Download className="w-4 h-4 mr-2" />
                      Download Summary
                    </Button>

                    <Button
                      className="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 transition-all duration-300 shadow-lg"
                    >
                      <MessageCircle className="w-4 h-4 mr-2" />
                      Contact Support
                    </Button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Enhanced Progress Timeline */}
          <div className="mb-8">
            <Card className="bg-white/80 backdrop-blur-sm border-gray-200/50 shadow-lg">
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <Activity className="w-5 h-5 text-blue-600" />
                  <span>Order Progress</span>
                </CardTitle>
              </CardHeader>
              <CardContent>
                {/* Status overview with enhanced visual design */}
                <div className="mb-6 p-4 rounded-xl bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200">
                  <div className="flex items-center justify-between mb-3">
                    <div className="flex items-center space-x-3">
                      {React.createElement(currentStatus.icon, {
                        className: "w-6 h-6 text-blue-600"
                      })}
                      <div>
                        <h3 className="font-semibold text-gray-900">{currentStatus.label}</h3>
                        <p className="text-sm text-gray-600">{currentStatus.patientFriendly}</p>
                      </div>
                    </div>
                    <div className="text-right">
                      <div className="text-2xl font-bold text-blue-600">{currentStatus.progressPercent}%</div>
                      <div className="text-xs text-gray-500">Complete</div>
                    </div>
                  </div>

                  {/* Enhanced progress bar */}
                  <div className="mb-3">
                    <div className="w-full bg-gray-200 rounded-full h-2.5">
                      <div
                        className="bg-gradient-to-r from-blue-500 to-indigo-500 h-2.5 rounded-full transition-all duration-1000 shadow-sm"
                        style={{ width: `${currentStatus.progressPercent}%` }}
                      ></div>
                    </div>
                  </div>

                  <div className="flex justify-between text-sm">
                    <div className="flex items-center space-x-1 text-gray-600">
                      <Timer className="w-3 h-3" />
                      <span>Est. Time: {currentStatus.estimatedTime}</span>
                    </div>
                    <div className="flex items-center space-x-1 text-gray-600">
                      <Target className="w-3 h-3" />
                      <span>Next: {currentStatus.nextStep}</span>
                    </div>
                  </div>
                </div>

                {/* IVR Status if applicable */}
                {currentIvrStatus && (
                  <div className="p-4 rounded-xl bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200">
                    <div className="flex items-center space-x-3">
                      {React.createElement(currentIvrStatus.icon, {
                        className: "w-5 h-5 text-green-600"
                      })}
                      <div>
                        <h4 className="font-medium text-green-900">{currentIvrStatus.label}</h4>
                        <p className="text-sm text-green-700">{currentIvrStatus.patientMessage}</p>
                      </div>
                    </div>
                  </div>
                )}
              </CardContent>
            </Card>
          </div>

          {/* Three-column layout with enhanced design */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {/* Left Column - Order Information */}
            <div className="space-y-6">
              <Card className="bg-white/80 backdrop-blur-sm border-gray-200/50 shadow-lg">
                <CardHeader>
                  <CardTitle className="flex items-center space-x-2">
                    <Package className="w-5 h-5 text-blue-600" />
                    <span>Order Information</span>
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="p-3 bg-blue-50 rounded-lg">
                    <label className="text-sm font-medium text-blue-700">Order Number</label>
                    <div className="flex items-center justify-between">
                      <p className="text-lg font-bold text-blue-900">#{order.order_number}</p>
                      <button
                        onClick={copyOrderNumber}
                        className="p-1 hover:bg-blue-100 rounded transition-colors"
                        title="Copy order number"
                      >
                        <Copy className="w-4 h-4 text-blue-600" />
                      </button>
                    </div>
                  </div>

                  <div>
                    <label className="text-sm font-medium text-gray-500">Current Status</label>
                    <div className="mt-1">
                      {getOrderStatusBadge(order.order_status)}
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="p-3 bg-green-50 rounded-lg">
                      <label className="text-sm font-medium text-green-700">Order Value</label>
                      <p className="text-lg font-bold text-green-800">
                        {formatCurrency(order.total_order_value || 0)}
                      </p>
                    </div>
                    <div className="p-3 bg-purple-50 rounded-lg">
                      <label className="text-sm font-medium text-purple-700">Service Date</label>
                      <p className="text-sm font-semibold text-purple-800">
                        {order.expected_service_date ? formatDate(order.expected_service_date) : 'TBD'}
                      </p>
                    </div>
                  </div>

                  <div className="p-3 bg-gray-50 rounded-lg">
                    <label className="text-sm font-medium text-gray-700">Submitted</label>
                    <p className="text-sm text-gray-900">{formatDateTime(order.submitted_at)}</p>
                  </div>
                </CardContent>
              </Card>

              {/* Enhanced Provider Information */}
              <Card className="bg-white/80 backdrop-blur-sm border-gray-200/50 shadow-lg">
                <CardHeader>
                  <CardTitle className="flex items-center space-x-2">
                    <User className="w-5 h-5 text-green-600" />
                    <span>Provider Information</span>
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  {order.provider && (
                    <>
                      <div className="p-3 bg-green-50 rounded-lg">
                        <label className="text-sm font-medium text-green-700">Provider</label>
                        <p className="text-sm font-semibold text-green-900">{order.provider.name}</p>
                      </div>
                      <div className="flex items-center space-x-2">
                        <Mail className="w-4 h-4 text-gray-500" />
                        <div className="flex-1">
                          <label className="text-sm font-medium text-gray-500">Email</label>
                          <p className="text-sm text-gray-900">{order.provider.email}</p>
                        </div>
                      </div>
                      {order.provider.npi_number && (
                        <div className="flex items-center space-x-2">
                          <Shield className="w-4 h-4 text-gray-500" />
                          <div className="flex-1">
                            <label className="text-sm font-medium text-gray-500">NPI Number</label>
                            <p className="text-sm text-gray-900">{order.provider.npi_number}</p>
                          </div>
                        </div>
                      )}
                    </>
                  )}
                </CardContent>
              </Card>
            </div>

            {/* Middle Column - Episode Information (Enhanced) */}
            <div className="space-y-6">
              {hasEpisode ? (
                <Card className="bg-white/80 backdrop-blur-sm border-gray-200/50 shadow-lg">
                  <CardHeader>
                    <CardTitle className="flex items-center justify-between">
                      <div className="flex items-center space-x-2">
                        <Heart className="w-5 h-5 text-purple-600" />
                        <span>Episode Information</span>
                      </div>
                      <Badge className="bg-purple-100 text-purple-700 border-purple-300">
                        <Layers className="w-3 h-3 mr-1" />
                        Episode Care
                      </Badge>
                    </CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    {/* Enhanced episode explanation */}
                    <div className="p-4 rounded-xl bg-gradient-to-r from-purple-50 to-indigo-50 border border-purple-200">
                      <div className="flex items-start space-x-3 mb-3">
                        <Info className="w-5 h-5 text-purple-600 mt-0.5" />
                        <div>
                          <h4 className="font-semibold text-purple-900 mb-1">
                            Coordinated Episode Care
                          </h4>
                          <p className="text-sm text-purple-700 leading-relaxed">
                            This order is part of a comprehensive care episode where multiple orders
                            for the same patient and manufacturer are coordinated together for optimal
                            care delivery and efficiency.
                          </p>
                        </div>
                      </div>

                      <div className="grid grid-cols-2 gap-3 mt-3">
                        <div className="text-center p-2 bg-white/70 rounded-lg">
                          <div className="text-lg font-bold text-purple-600">
                            {episode.orders?.length || 1}
                          </div>
                          <div className="text-xs text-purple-700">Orders in Episode</div>
                        </div>
                        <div className="text-center p-2 bg-white/70 rounded-lg">
                          <div className="text-lg font-bold text-purple-600">
                            {formatCurrency(episode.total_order_value || order.total_order_value || 0)}
                          </div>
                          <div className="text-xs text-purple-700">Episode Value</div>
                        </div>
                      </div>
                    </div>

                    {/* Episode status details */}
                    <div className="space-y-3">
                      <div>
                        <label className="text-sm font-medium text-gray-500">Episode Status</label>
                        <div className="mt-1">
                          {(() => {
                            const config = statusConfig[episode.status] || statusConfig.ready_for_review;
                            const Icon = config.icon;
                            return (
                              <Badge className={`${config.color} flex items-center space-x-2 px-3 py-1.5 rounded-xl shadow-sm border`}>
                                <Icon className="w-4 h-4" />
                                <span className="font-medium">{config.label}</span>
                              </Badge>
                            );
                          })()}
                        </div>
                        <p className="text-xs text-gray-600 mt-1">
                          {statusConfig[episode.status]?.description || 'Episode is being processed'}
                        </p>
                      </div>

                      {currentIvrStatus && (
                        <div>
                          <label className="text-sm font-medium text-gray-500">Insurance Status</label>
                          <div className="mt-1">
                            <Badge className={`${currentIvrStatus.color} flex items-center space-x-2 px-3 py-1.5 rounded-xl shadow-sm border`}>
                              {React.createElement(currentIvrStatus.icon, { className: "w-4 h-4" })}
                              <span className="font-medium">{currentIvrStatus.label}</span>
                            </Badge>
                          </div>
                          <p className="text-xs text-gray-600 mt-1">
                            {currentIvrStatus.description}
                          </p>
                        </div>
                      )}
                    </div>

                    {/* Related orders in episode */}
                    {episode.orders && episode.orders.length > 1 && (
                      <div>
                        <div className="flex items-center justify-between mb-2">
                          <label className="text-sm font-medium text-gray-500">Related Orders</label>
                          <Badge variant="outline" className="text-xs">
                            {episode.orders.length} orders
                          </Badge>
                        </div>
                        <div className="space-y-2 max-h-32 overflow-y-auto">
                          {episode.orders.map((relatedOrder) => (
                            <div key={relatedOrder.id}
                                 className="flex items-center justify-between text-sm p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                              <div className="flex items-center space-x-2">
                                <Package className="w-3 h-3 text-gray-500" />
                                <span className="font-medium">#{relatedOrder.order_number}</span>
                              </div>
                              {getOrderStatusBadge(relatedOrder.status)}
                            </div>
                          ))}
                        </div>
                      </div>
                    )}

                    {/* Action to view full episode */}
                    <Button
                      variant="outline"
                      className="w-full bg-purple-50 hover:bg-purple-100 border-purple-200 text-purple-700 hover:text-purple-800"
                      onClick={() => window.location.href = `/provider/episodes/${episode.id}`}
                    >
                      <Eye className="w-4 h-4 mr-2" />
                      View Complete Episode
                      <ChevronRight className="w-4 h-4 ml-2" />
                    </Button>
                  </CardContent>
                </Card>
              ) : (
                <Card className="bg-white/80 backdrop-blur-sm border-gray-200/50 shadow-lg">
                  <CardHeader>
                    <CardTitle className="flex items-center space-x-2">
                      <Package className="w-5 h-5 text-gray-600" />
                      <span>Individual Order</span>
                    </CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="text-center py-8">
                      <div className="p-4 bg-gray-50 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                        <Package className="w-8 h-8 text-gray-400" />
                      </div>
                      <h3 className="font-medium text-gray-900 mb-2">Standalone Order</h3>
                      <p className="text-sm text-gray-600 mb-4">
                        This is an individual order not part of an episode workflow
                      </p>
                      <Button variant="outline" size="sm" className="text-gray-600">
                        <BookOpen className="w-4 h-4 mr-2" />
                        Learn About Episodes
                      </Button>
                    </div>
                  </CardContent>
                </Card>
              )}

              {/* Enhanced Facility Information */}
              <Card className="bg-white/80 backdrop-blur-sm border-gray-200/50 shadow-lg">
                <CardHeader>
                  <CardTitle className="flex items-center space-x-2">
                    <Building2 className="w-5 h-5 text-indigo-600" />
                    <span>Facility Information</span>
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  {order.facility && (
                    <>
                      <div className="p-3 bg-indigo-50 rounded-lg">
                        <label className="text-sm font-medium text-indigo-700">Facility</label>
                        <p className="text-sm font-semibold text-indigo-900">{order.facility.name}</p>
                      </div>
                      <div className="flex items-center space-x-2">
                        <MapPin className="w-4 h-4 text-gray-500" />
                        <div className="flex-1">
                          <label className="text-sm font-medium text-gray-500">Location</label>
                          <p className="text-sm text-gray-900">{order.facility.city}, {order.facility.state}</p>
                        </div>
                      </div>
                    </>
                  )}
                </CardContent>
              </Card>
            </div>

            {/* Right Column - Documents, Actions & Support */}
            <div className="space-y-6">
              {/* Enhanced Documents Section */}
              <Card className="bg-white/80 backdrop-blur-sm border-gray-200/50 shadow-lg">
                <CardHeader>
                  <CardTitle className="flex items-center justify-between">
                    <div className="flex items-center space-x-2">
                      <FileText className="w-5 h-5 text-green-600" />
                      <span>Documents & Files</span>
                    </div>
                    <Button variant="ghost" size="sm">
                      <RefreshCw className="w-4 h-4" />
                    </Button>
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  {order.docuseal && order.docuseal.status ? (
                    <div className="space-y-4">
                      <div className="p-3 rounded-lg bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200">
                        <div className="flex items-center space-x-2 mb-2">
                          <FileCheck className="w-4 h-4 text-blue-600" />
                          <span className="text-sm font-medium text-blue-900">
                            Document Status: {order.docuseal.status}
                          </span>
                        </div>
                        <p className="text-xs text-blue-700">
                          All required documentation is being processed
                        </p>
                      </div>

                      {order.docuseal.signed_documents && order.docuseal.signed_documents.length > 0 && (
                        <div className="space-y-2">
                          <h4 className="text-sm font-medium text-gray-700">Available Documents</h4>
                          {order.docuseal.signed_documents.map((doc) => (
                            <div key={doc.id} className="group flex items-center justify-between p-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                              <div className="flex items-center space-x-3">
                                <div className="p-2 bg-blue-100 rounded-lg group-hover:bg-blue-200 transition-colors">
                                  <FileText className="w-4 h-4 text-blue-600" />
                                </div>
                                <div>
                                  <p className="text-sm font-medium text-gray-900">
                                    {doc.name || doc.filename || 'Document'}
                                  </p>
                                  <p className="text-xs text-gray-500">PDF Document</p>
                                </div>
                              </div>
                              <div className="flex items-center space-x-2">
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  onClick={() => window.open(doc.url, '_blank')}
                                  className="hover:bg-blue-50"
                                >
                                  <Eye className="w-3 h-3" />
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
                                  className="hover:bg-blue-50"
                                >
                                  <Download className="w-3 h-3" />
                                </Button>
                              </div>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>
                  ) : (
                    <div className="text-center py-8">
                      <div className="p-4 bg-gray-50 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                        <FileText className="w-8 h-8 text-gray-400" />
                      </div>
                      <h3 className="font-medium text-gray-900 mb-2">No Documents Yet</h3>
                      <p className="text-sm text-gray-600 mb-4">
                        Documents will appear here as they become available
                      </p>
                      <Button variant="outline" size="sm" className="text-gray-600">
                        <Bell className="w-4 h-4 mr-2" />
                        Notify When Ready
                      </Button>
                    </div>
                  )}
                </CardContent>
              </Card>

              {/* Quick Actions */}
              <Card className="bg-white/80 backdrop-blur-sm border-gray-200/50 shadow-lg">
                <CardHeader>
                  <CardTitle className="flex items-center space-x-2">
                    <Zap className="w-5 h-5 text-yellow-600" />
                    <span>Quick Actions</span>
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  <Button
                    variant="outline"
                    className="w-full justify-start bg-blue-50 hover:bg-blue-100 border-blue-200 text-blue-700"
                  >
                    <Phone className="w-4 h-4 mr-3" />
                    Call Support
                  </Button>

                  <Button
                    variant="outline"
                    className="w-full justify-start bg-green-50 hover:bg-green-100 border-green-200 text-green-700"
                  >
                    <Mail className="w-4 h-4 mr-3" />
                    Email Updates
                  </Button>

                  <Button
                    variant="outline"
                    className="w-full justify-start bg-purple-50 hover:bg-purple-100 border-purple-200 text-purple-700"
                  >
                    <Share2 className="w-4 h-4 mr-3" />
                    Share Order Info
                  </Button>

                  <Button
                    variant="outline"
                    className="w-full justify-start bg-orange-50 hover:bg-orange-100 border-orange-200 text-orange-700"
                  >
                    <HelpCircle className="w-4 h-4 mr-3" />
                    Get Help
                  </Button>
                </CardContent>
              </Card>

              {/* Support Information */}
              <Card className="bg-white/80 backdrop-blur-sm border-gray-200/50 shadow-lg">
                <CardHeader>
                  <CardTitle className="flex items-center space-x-2">
                    <Users className="w-5 h-5 text-indigo-600" />
                    <span>Support & Resources</span>
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="p-3 bg-indigo-50 rounded-lg">
                    <h4 className="font-medium text-indigo-900 mb-2">Need Assistance?</h4>
                    <p className="text-sm text-indigo-700 mb-3">
                      Our support team is here to help with any questions about your order.
                    </p>
                    <div className="space-y-2 text-sm">
                      <div className="flex items-center space-x-2 text-indigo-600">
                        <Phone className="w-3 h-3" />
                        <span>1-800-MSC-HELP</span>
                      </div>
                      <div className="flex items-center space-x-2 text-indigo-600">
                        <Mail className="w-3 h-3" />
                        <span>support@mschealthcare.com</span>
                      </div>
                      <div className="flex items-center space-x-2 text-indigo-600">
                        <Clock className="w-3 h-3" />
                        <span>Mon-Fri 8AM-6PM EST</span>
                      </div>
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      className="text-xs bg-yellow-50 hover:bg-yellow-100 border-yellow-200 text-yellow-700"
                    >
                      <Star className="w-3 h-3 mr-1" />
                      Rate Experience
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      className="text-xs bg-gray-50 hover:bg-gray-100 border-gray-200 text-gray-700"
                    >
                      <BookOpen className="w-3 h-3 mr-1" />
                      User Guide
                    </Button>
                  </div>
                </CardContent>
              </Card>
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
}

export default ProviderOrderShow;
