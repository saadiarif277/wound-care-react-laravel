import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/Button';
import GlassCard from '@/Components/ui/GlassCard';
import {
  User,
  Mail,
  Phone,
  MapPin,
  Building,
  Shield,
  Package,
  DollarSign,
  FileText,
  Calendar,
  CheckCircle2,
  AlertTriangle,
  Clock,
  Edit,
  MoreVertical,
  TrendingUp,
  TrendingDown,
  CreditCard,
  Receipt,
  ShoppingCart,
  Activity,
  Download,
  Send,
  Ban,
  ChevronRight,
  Plus,
  X,
  Check
} from 'lucide-react';
import { formatDistanceToNow, format } from 'date-fns';
import AddProviderFacilityModal from '@/Components/ui/AddProviderFacilityModal';
import AddProductModal from '@/Components/ui/AddProductModal';
import Alert from '@/Components/Alert/Alert';
import { Can, useHasPermission } from '@/lib/permissions';

interface ProviderProfile {
  id: number;
  name: string;
  email: string;
  phone?: string;
  npi_number?: string;
  address?: {
    line1: string;
    line2?: string;
    city: string;
    state: string;
    zip: string;
  };
  profile: {
    verification_status: string;
    profile_completion_percentage: number;
    professional_bio?: string;
    specializations?: string[];
    languages_spoken?: string[];
    last_profile_update?: string;
  };
  current_organization?: {
    id: number;
    name: string;
    type: string;
  };
  credentials: Array<{
    id: string;
    type: string;
    name: string;
    number: string;
    expiration_date: string;
    verification_status: string;
    is_expired: boolean;
    expires_soon: boolean;
    issuing_state?: string;
  }>;
  facilities: Array<{
    id: number;
    name: string;
    type: string;
    address: string;
    is_primary: boolean;
  }>;
  products: Array<{
    id: number;
    name: string;
    sku: string;
    manufacturer: string;
    category: string;
    onboarded_at: string;
    onboarding_status: string;
    expiration_date?: string;
  }>;
  financial_summary: {
    total_outstanding: number;
    current_balance: number;
    past_due_amount: number;
    days_past_due: number;
    payment_terms: string;
    last_payment?: {
      date: string;
      amount: number;
      reference: string;
    };
    aging_buckets: {
      current: number;
      '30_days': number;
      '60_days': number;
      '90_days': number;
      'over_90': number;
    };
  };
  recent_orders: Array<{
    id: number;
    order_number: string;
    date: string;
    status: string;
    total_amount: number;
    items_count: number;
    payment_status: string;
    days_outstanding?: number;
  }>;
  payment_history: Array<{
    id: number;
    date: string;
    amount: number;
    method: string;
    reference: string;
    order_number: string;
    paid_to: 'msc' | 'manufacturer';
    posted_by: string;
  }>;
  activity_log: Array<{
    id: number;
    action: string;
    description: string;
    user: string;
    timestamp: string;
  }>;
  created_at: string;
  updated_at: string;
}

interface Props {
  provider: ProviderProfile;
  stats: {
    total_orders: number;
    total_revenue: number;
    avg_order_value: number;
    payment_performance_score: number;
  };
  availableFacilities: Array<{
    id: number;
    name: string;
    address: string;
  }>;
  flash?: {
    success?: string;
    error?: string;
    warning?: string;
  };
}

export default function ProviderShow({ provider, stats, availableFacilities, flash }: Props) {
  const { theme } = useTheme();
  const t = themes[theme];

  const [activeTab, setActiveTab] = useState('overview');
  const [showAddFacilityModal, setShowAddFacilityModal] = useState(false);
  const [showAddProductModal, setShowAddProductModal] = useState(false);
  const [showRecordPaymentModal, setShowRecordPaymentModal] = useState(false);

  // Check permissions
  const canManageProviders = useHasPermission('manage-providers');
  const canViewFinancials = useHasPermission('view-financials');
  const canManageFacilities = useHasPermission('manage-facilities');
  const canManageProducts = useHasPermission('manage-products');

  const tabs = [
    { id: 'overview', label: 'Overview', icon: User },
    { id: 'credentials', label: 'Credentials', icon: Shield },
    { id: 'facilities', label: 'Facilities', icon: Building },
    { id: 'products', label: 'Products', icon: Package },
    { id: 'financial', label: 'Financial', icon: DollarSign },
    { id: 'orders', label: 'Order History', icon: ShoppingCart },
    { id: 'activity', label: 'Activity Log', icon: Activity }
  ];

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'verified':
        return <Badge className="bg-green-100 text-green-800"><CheckCircle2 className="w-3 h-3 mr-1" />Verified</Badge>;
      case 'pending':
        return <Badge className="bg-yellow-100 text-yellow-800"><Clock className="w-3 h-3 mr-1" />Pending</Badge>;
      case 'rejected':
        return <Badge className="bg-red-100 text-red-800"><AlertTriangle className="w-3 h-3 mr-1" />Rejected</Badge>;
      default:
        return <Badge className="bg-gray-100 text-gray-800">{status}</Badge>;
    }
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(amount);
  };

  const getOrderStatusBadge = (status: string) => {
    const statusConfig = {
      pending: { color: 'bg-yellow-100 text-yellow-800', icon: Clock },
      processing: { color: 'bg-blue-100 text-blue-800', icon: Activity },
      shipped: { color: 'bg-purple-100 text-purple-800', icon: Package },
      delivered: { color: 'bg-green-100 text-green-800', icon: CheckCircle2 },
      cancelled: { color: 'bg-red-100 text-red-800', icon: X }
    };

    const config = statusConfig[status] || { color: 'bg-gray-100 text-gray-800', icon: FileText };
    const Icon = config.icon;

    return (
      <Badge className={config.color}>
        <Icon className="w-3 h-3 mr-1" />
        {status.charAt(0).toUpperCase() + status.slice(1)}
      </Badge>
    );
  };

  const getPaymentStatusBadge = (status: string, daysOutstanding?: number) => {
    if (status === 'paid') {
      return <Badge className="bg-green-100 text-green-800">Paid</Badge>;
    }
    if (daysOutstanding && daysOutstanding > 60) {
      return <Badge className="bg-red-100 text-red-800">Past Due ({daysOutstanding}d)</Badge>;
    }
    if (daysOutstanding && daysOutstanding > 30) {
      return <Badge className="bg-orange-100 text-orange-800">Due ({daysOutstanding}d)</Badge>;
    }
    return <Badge className="bg-yellow-100 text-yellow-800">Pending</Badge>;
  };

  const handleAddFacility = () => {
    // ... existing code ...
  };

  return (
    <MainLayout>
      <Head title={`Provider - ${provider.name}`} />

      {/* Flash Messages */}
      {flash?.success && (
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-4">
          <Alert type="success" dismissible>
            {flash.success}
          </Alert>
        </div>
      )}
      {flash?.error && (
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-4">
          <Alert type="error" dismissible>
            {flash.error}
          </Alert>
        </div>
      )}
      {flash?.warning && (
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-4">
          <Alert type="warning" dismissible>
            {flash.warning}
          </Alert>
        </div>
      )}

      <div className="space-y-6">
        {/* Header */}
        <div className="mb-8 text-center max-w-4xl mx-auto">
          <button
            onClick={() => router.visit(route('admin.providers.index'))}
            className={cn(
              "inline-flex items-center text-sm transition-colors mb-4",
              t.text.secondary,
              "hover:" + t.text.primary
            )}
          >
            ← Back to Providers
          </button>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-[#1925c3] to-[#c71719]">
            Provider Profile
          </h1>
          <p className={cn("mt-2 leading-normal", t.text.secondary)}>
            Complete provider information and management
          </p>
        </div>

        <div className="flex items-center justify-center gap-3 mb-8">
          <Button
            variant="secondary"
            onClick={() => window.print()}
            className={cn(
              "px-4 py-2 rounded-xl transition-all",
              theme === 'dark' ? t.button.secondary : 'bg-gray-100 hover:bg-gray-200 text-gray-700'
            )}
          >
            <Download className="w-4 h-4 mr-2" />
            Export Profile
          </Button>
          <Button
            onClick={() => router.visit(route('admin.providers.edit', provider.id))}
            className={cn(
              "px-4 py-2 rounded-xl transition-all",
              theme === 'dark' ? t.button.primary : 'bg-blue-600 text-white hover:bg-blue-700'
            )}
          >
            <Edit className="w-4 h-4 mr-2" />
            Edit Profile
          </Button>
          <div className="relative group">
            <Button variant="secondary" className={cn(
              "px-2",
              theme === 'dark' ? t.button.secondary : 'bg-gray-100 hover:bg-gray-200 text-gray-700'
            )}>
              <MoreVertical className="w-5 h-5" />
            </Button>
            <div className={cn(
              "absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 z-10 hidden group-hover:block",
              theme === 'dark' ? t.glass.card : 'bg-white border border-gray-200'
            )}>
              <button
                onClick={() => {
                  if (confirm(`Are you sure you want to deactivate ${provider.name}? This action cannot be undone.`)) {
                    router.delete(route('admin.providers.destroy', provider.id));
                  }
                }}
                className={cn(
                  "block w-full text-left px-4 py-2 text-sm transition-colors disabled:cursor-not-allowed",
                  theme === 'dark'
                    ? 'text-red-400 hover:bg-red-500/20 disabled:text-gray-500'
                    : 'text-red-700 hover:bg-red-50 disabled:text-gray-400'
                )}
                disabled={provider.financial_summary.total_outstanding > 0}
              >
                <Ban className="w-4 h-4 inline mr-2" />
                Deactivate Provider
              </button>
            </div>
          </div>
        </div>

        {/* Provider Info Header */}
        <GlassCard className="max-w-5xl mx-auto">
          <div className="flex items-start justify-between">
            <div className="flex items-start gap-4">
              <div className={cn(
                "h-16 w-16 rounded-full flex items-center justify-center",
                theme === 'dark' ? 'bg-blue-500/20' : 'bg-blue-500/10'
              )}>
                <User className="h-8 w-8 text-blue-500" />
              </div>
              <div>
                <h1 className={cn("text-2xl font-bold", t.text.primary)}>{provider.name}</h1>
                <div className={cn("mt-1 flex items-center gap-4 text-sm", t.text.secondary)}>
                  <span className="flex items-center gap-1">
                    <Mail className="w-4 h-4" />
                    {provider.email}
                  </span>
                  {provider.phone && (
                    <span className="flex items-center gap-1">
                      <Phone className="w-4 h-4" />
                      {provider.phone}
                    </span>
                  )}
                  {provider.npi_number && (
                    <span className="flex items-center gap-1">
                      <Shield className="w-4 h-4" />
                      NPI: {provider.npi_number}
                    </span>
                  )}
                </div>
                <div className="mt-2 flex items-center gap-3">
                  {provider.profile.verification_status === 'verified' ? (
                    <Badge className={cn(
                      "inline-flex items-center px-2 py-1 rounded-full text-xs font-medium",
                      theme === 'dark' ? 'bg-green-500/20 text-green-300 border-green-500/30' : 'bg-green-100 text-green-800'
                    )}>
                      <CheckCircle2 className="w-3 h-3 mr-1" />
                      Active
                    </Badge>
                  ) : (
                    getStatusBadge(provider.profile.verification_status)
                  )}
                  <Badge className={cn(
                    "inline-flex items-center px-2 py-1 rounded-full text-xs font-medium",
                    theme === 'dark' ? 'bg-blue-500/20 text-blue-300 border-blue-500/30' : 'bg-blue-100 text-blue-800'
                  )}>
                    {provider.profile.profile_completion_percentage || 100}% Profile Complete
                  </Badge>
                  {provider.current_organization && (
                    <Badge className={cn(
                      "inline-flex items-center px-2 py-1 rounded-full text-xs font-medium",
                      theme === 'dark' ? 'bg-blue-500/20 text-blue-300 border-blue-500/30' : 'bg-blue-100 text-blue-800'
                    )}>
                      <Building className="w-3 h-3 mr-1" />
                      {provider.current_organization.name}
                    </Badge>
                  )}
                </div>
              </div>
            </div>
            <div className="text-right">
              <div className={cn("text-sm", t.text.secondary)}>Outstanding Balance</div>
              <div className={cn("text-xl font-bold", t.text.primary)}>
                {formatCurrency(provider.financial_summary.total_outstanding)}
              </div>
              <div className="mt-2">
                {provider.financial_summary.past_due_amount > 0 ? (
                  <Badge className={cn(
                    "inline-flex items-center px-2 py-1 rounded-full text-xs font-medium",
                    theme === 'dark' ? 'bg-red-500/20 text-red-300 border-red-500/30' : 'bg-red-100 text-red-800'
                  )}>
                    <AlertTriangle className="w-3 h-3 mr-1" />
                    {formatCurrency(provider.financial_summary.past_due_amount)} Past Due
                  </Badge>
                ) : (
                  <Badge className={cn(
                    "inline-flex items-center px-2 py-1 rounded-full text-xs font-medium",
                    theme === 'dark' ? 'bg-green-500/20 text-green-300 border-green-500/30' : 'bg-green-100 text-green-800'
                  )}>
                    <CheckCircle2 className="w-3 h-3 mr-1" />
                    Account in Good Standing
                  </Badge>
                )}
              </div>
            </div>
          </div>

          {/* Quick Stats */}
          <div className={cn("mt-6 grid grid-cols-4 gap-4 pt-6 border-t", theme === 'dark' ? 'border-white/10' : 'border-gray-200')}>
            <div>
              <div className={cn("text-sm", t.text.muted)}>Total Orders</div>
              <div className={cn("text-2xl font-bold", t.text.primary)}>{stats.total_orders}</div>
            </div>
            <div>
              <div className={cn("text-sm", t.text.muted)}>Total Revenue</div>
              <div className={cn("text-2xl font-bold", t.text.primary)}>{formatCurrency(stats.total_revenue)}</div>
            </div>
            <div>
              <div className={cn("text-sm", t.text.muted)}>Avg Order Value</div>
              <div className={cn("text-2xl font-bold", t.text.primary)}>{formatCurrency(stats.avg_order_value)}</div>
            </div>
            <div>
              <div className={cn("text-sm", t.text.muted)}>Payment Score</div>
              <div className={cn("text-2xl font-bold", t.text.primary)}>{stats.payment_performance_score}%</div>
            </div>
          </div>
        </GlassCard>

        {/* Tabs Section */}
        <GlassCard className="max-w-5xl mx-auto">
          {/* Tabs */}
          <div className="mb-6">
            <nav className="flex space-x-4" aria-label="Tabs">
              {tabs.map((tab) => {
                const Icon = tab.icon;
                return (
                  <button
                    key={tab.id}
                    onClick={() => setActiveTab(tab.id)}
                    className={cn(
                      "px-3 py-2 font-medium text-sm rounded-lg flex items-center gap-2 transition-all",
                      activeTab === tab.id
                        ? theme === 'dark'
                          ? 'bg-blue-500/20 text-blue-300 border border-blue-500/30'
                          : 'bg-blue-100 text-blue-700 border border-blue-200'
                        : theme === 'dark'
                          ? t.text.secondary + ' hover:' + t.text.primary + ' hover:bg-white/5'
                          : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'
                    )}
                  >
                    <Icon className="w-4 h-4" />
                    {tab.label}
                  </button>
                );
              })}
            </nav>
          </div>

          {/* Tab Content */}
          <div>
            {activeTab === 'overview' && (
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Profile Information */}
                <Card>
                  <CardHeader>
                    <CardTitle>Profile Information</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <dl className="space-y-4">
                      {provider.address && (
                        <div>
                          <dt className="text-sm font-medium text-gray-500">Address</dt>
                          <dd className="mt-1 text-sm text-gray-900">
                            {provider.address.line1}<br />
                            {provider.address.line2 && <>{provider.address.line2}<br /></>}
                            {provider.address.city}, {provider.address.state} {provider.address.zip}
                          </dd>
                        </div>
                      )}
                      {provider.profile.specializations && provider.profile.specializations.length > 0 && (
                        <div>
                          <dt className="text-sm font-medium text-gray-500">Specializations</dt>
                          <dd className="mt-1">
                            <div className="flex flex-wrap gap-2">
                              {provider.profile.specializations.map((spec, index) => (
                                <Badge key={index} variant="secondary">{spec}</Badge>
                              ))}
                            </div>
                          </dd>
                        </div>
                      )}
                      {provider.profile.languages_spoken && provider.profile.languages_spoken.length > 0 && (
                        <div>
                          <dt className="text-sm font-medium text-gray-500">Languages</dt>
                          <dd className="mt-1 text-sm text-gray-900">
                            {provider.profile.languages_spoken.join(', ')}
                          </dd>
                        </div>
                      )}
                      <div>
                        <dt className="text-sm font-medium text-gray-500">Member Since</dt>
                        <dd className="mt-1 text-sm text-gray-900">
                          {format(new Date(provider.created_at), 'MMMM d, yyyy')}
                        </dd>
                      </div>
                    </dl>
                  </CardContent>
                </Card>

                {/* Financial Overview */}
                <Card>
                  <CardHeader>
                    <CardTitle>Financial Overview</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="space-y-4">
                      <div className="flex justify-between items-center">
                        <span className="text-sm text-gray-500">Outstanding Balance</span>
                        <span className="text-lg font-semibold">{formatCurrency(provider.financial_summary.total_outstanding)}</span>
                      </div>
                      <div className="flex justify-between items-center">
                        <span className="text-sm text-gray-500">Current (0-30 days)</span>
                        <span className="text-sm">{formatCurrency(provider.financial_summary.aging_buckets.current)}</span>
                      </div>
                      <div className="flex justify-between items-center">
                        <span className="text-sm text-gray-500">31-60 days</span>
                        <span className="text-sm">{formatCurrency(provider.financial_summary.aging_buckets['30_days'] + provider.financial_summary.aging_buckets['60_days'])}</span>
                      </div>
                      <div className="flex justify-between items-center">
                        <span className="text-sm text-gray-500">Over 60 days</span>
                        <span className="text-sm text-red-600 font-semibold">{formatCurrency(provider.financial_summary.aging_buckets['90_days'] + provider.financial_summary.aging_buckets['over_90'])}</span>
                      </div>
                      <div className="pt-4 border-t">
                        <div className="flex justify-between items-center">
                          <span className="text-sm text-gray-500">Payment Terms</span>
                          <span className="text-sm font-medium">{provider.financial_summary.payment_terms}</span>
                        </div>
                        {provider.financial_summary.last_payment && (
                          <div className="mt-2">
                            <span className="text-sm text-gray-500">Last Payment</span>
                            <p className="text-sm">
                              {formatCurrency(provider.financial_summary.last_payment.amount)} on {format(new Date(provider.financial_summary.last_payment.date), 'MMM d, yyyy')}
                            </p>
                          </div>
                        )}
                      </div>
                    </div>
                  </CardContent>
                </Card>

                {/* Recent Orders */}
                <Card className="lg:col-span-2">
                  <CardHeader>
                    <div className="flex justify-between items-center">
                      <CardTitle>Recent Orders</CardTitle>
                      <Button variant="secondary" onClick={() => setActiveTab('orders')}>
                        View All
                        <ChevronRight className="w-4 h-4 ml-1" />
                      </Button>
                    </div>
                  </CardHeader>
                  <CardContent>
                    <div className="overflow-x-auto">
                      <table className="min-w-full divide-y divide-gray-200">
                        <thead>
                          <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                          {provider.recent_orders.slice(0, 5).map((order) => (
                            <tr key={order.id}>
                              <td className="px-6 py-4 whitespace-nowrap text-sm">
                                <a href={route('product-requests.show', order.id)} className="text-red-600 hover:text-red-900">
                                  #{order.order_number}
                                </a>
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {format(new Date(order.date), 'MMM d, yyyy')}
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap">
                                {getOrderStatusBadge(order.status)}
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {formatCurrency(order.total_amount)}
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap">
                                {getPaymentStatusBadge(order.payment_status, order.days_outstanding)}
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </CardContent>
                </Card>
              </div>
            )}

            {activeTab === 'credentials' && (
              <Card>
                <CardHeader>
                  <div className="flex justify-between items-center">
                    <CardTitle>Professional Credentials</CardTitle>
                    <Button
                      onClick={() => router.visit(`/providers/credentials?provider_id=${provider.id}`)}
                      variant="secondary"
                    >
                      <Shield className="w-4 h-4 mr-2" />
                      Manage Credentials
                    </Button>
                  </div>
                </CardHeader>
                <CardContent>
                  {provider.credentials.length === 0 ? (
                    <div className="text-center py-8">
                      <Shield className="mx-auto h-12 w-12 text-gray-400 mb-4" />
                      <h3 className="text-lg font-medium text-gray-900 mb-2">No credentials on file</h3>
                      <p className="text-gray-600 mb-4">This provider hasn't added any professional credentials yet.</p>
                      <Button
                        onClick={() => router.visit(`/providers/credentials?provider_id=${provider.id}`)}
                      >
                        <Plus className="w-4 h-4 mr-2" />
                        Add Credentials
                      </Button>
                    </div>
                  ) : (
                    <div className="space-y-4">
                      {provider.credentials.map((credential) => (
                        <div key={credential.id} className="border rounded-lg p-4">
                          <div className="flex items-start justify-between">
                            <div>
                              <h4 className="font-medium">{credential.name}</h4>
                              <p className="text-sm text-gray-600">
                                {credential.type} • {credential.number}
                                {credential.issuing_state && ` • ${credential.issuing_state} License`}
                              </p>
                              {credential.expiration_date && (
                                <p className="text-sm text-gray-500 mt-1">
                                  Expires: {format(new Date(credential.expiration_date), 'MMM d, yyyy')}
                                </p>
                              )}
                            </div>
                            <div className="flex items-center gap-2">
                              {credential.is_expired && (
                                <Badge className="bg-red-100 text-red-800">Expired</Badge>
                              )}
                              {credential.expires_soon && !credential.is_expired && (
                                <Badge className="bg-orange-100 text-orange-800">Expires Soon</Badge>
                              )}
                              {getStatusBadge(credential.verification_status)}
                            </div>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </CardContent>
              </Card>
            )}

            {activeTab === 'facilities' && (
              <Card>
                <CardHeader>
                  <div className="flex justify-between items-center">
                    <CardTitle>Associated Facilities</CardTitle>
                    <Button onClick={() => setShowAddFacilityModal(true)}>
                      <Plus className="w-4 h-4 mr-2" />
                      Add Facility
                    </Button>
                  </div>
                </CardHeader>
                <CardContent>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {provider.facilities.map((facility) => (
                      <div key={facility.id} className="border rounded-lg p-4">
                        <div className="flex items-start justify-between">
                          <div>
                            <h4 className="font-medium">{facility.name}</h4>
                            <p className="text-sm text-gray-600">{facility.type}</p>
                            <p className="text-sm text-gray-500 mt-1">
                              <MapPin className="w-3 h-3 inline mr-1" />
                              {facility.address}
                            </p>
                          </div>
                          {facility.is_primary && (
                            <Badge className="bg-blue-100 text-blue-800">Primary</Badge>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                </CardContent>
              </Card>
            )}

            {activeTab === 'products' && (
              <Card>
                <CardHeader>
                  <div className="flex justify-between items-center">
                    <CardTitle>Onboarded Products</CardTitle>
                    <Button onClick={() => setShowAddProductModal(true)}>
                      <Plus className="w-4 h-4 mr-2" />
                      Add Product
                    </Button>
                  </div>
                </CardHeader>
                <CardContent>
                  <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                      <thead>
                        <tr>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Manufacturer</th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Onboarded</th>
                          <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-gray-200">
                        {provider.products.map((product) => (
                          <tr key={product.id}>
                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                              {product.name}
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                              {product.sku}
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                              {product.manufacturer}
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                              {product.category}
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap">
                              <Badge className={product.onboarding_status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}>
                                {product.onboarding_status}
                              </Badge>
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                              {format(new Date(product.onboarded_at), 'MMM d, yyyy')}
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                              <button className="text-red-600 hover:text-red-900">
                                <MoreVertical className="w-4 h-4" />
                              </button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </CardContent>
              </Card>
            )}

            {activeTab === 'financial' && (
              <div className="space-y-6">
                {/* Financial Summary */}
                <Card>
                  <CardHeader>
                    <div className="flex justify-between items-center">
                      <CardTitle>Account Summary</CardTitle>
                      <Button onClick={() => router.visit(route('admin.payments.index'))}>
                        <Plus className="w-4 h-4 mr-2" />
                        Record Payment
                      </Button>
                    </div>
                  </CardHeader>
                  <CardContent>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                      <div>
                        <h4 className="text-sm font-medium text-gray-500">Payment Information</h4>
                        <dl className="mt-2 space-y-2">
                          <div className="flex justify-between">
                            <dt className="text-sm text-gray-600">Payment Terms</dt>
                            <dd className="text-sm font-medium">{provider.financial_summary.payment_terms}</dd>
                          </div>
                          {provider.financial_summary.last_payment && (
                            <div className="flex justify-between">
                              <dt className="text-sm text-gray-600">Last Payment</dt>
                              <dd className="text-sm font-medium">
                                {formatCurrency(provider.financial_summary.last_payment.amount)} on {format(new Date(provider.financial_summary.last_payment.date), 'MMM d, yyyy')}
                              </dd>
                            </div>
                          )}
                          <div className="flex justify-between">
                            <dt className="text-sm text-gray-600">Days Past Due</dt>
                            <dd className={`text-sm font-medium ${provider.financial_summary.days_past_due > 60 ? 'text-red-600' : 'text-green-600'}`}>
                              {provider.financial_summary.days_past_due} days
                            </dd>
                          </div>
                        </dl>
                      </div>

                      <div>
                        <h4 className="text-sm font-medium text-gray-500">Outstanding Balance</h4>
                        <div className="mt-2">
                          <p className="text-3xl font-bold">{formatCurrency(provider.financial_summary.total_outstanding)}</p>
                          {provider.financial_summary.past_due_amount > 0 && (
                            <p className="text-sm text-red-600 mt-1">
                              {formatCurrency(provider.financial_summary.past_due_amount)} past due
                            </p>
                          )}
                        </div>
                      </div>

                      <div>
                        <h4 className="text-sm font-medium text-gray-500">Payment Performance</h4>
                        <div className="mt-2">
                          <div className="flex items-center">
                            <div className="flex-1 bg-gray-200 rounded-full h-8 mr-2">
                              <div
                                className="bg-green-500 h-8 rounded-full flex items-center justify-center text-white text-sm font-medium"
                                style={{ width: `${stats.payment_performance_score}%` }}
                              >
                                {stats.payment_performance_score}%
                              </div>
                            </div>
                          </div>
                          <p className="text-xs text-gray-500 mt-1">Based on payment history</p>
                        </div>
                      </div>
                    </div>
                  </CardContent>
                </Card>

                {/* Aging Report */}
                <Card>
                  <CardHeader>
                    <CardTitle>Aging Report</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="overflow-x-auto">
                      <table className="min-w-full">
                        <thead>
                          <tr className="border-b">
                            <th className="text-left py-2">Period</th>
                            <th className="text-right py-2">Amount</th>
                            <th className="text-right py-2">Percentage</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr className="border-b">
                            <td className="py-2">Current (0-30 days)</td>
                            <td className="text-right">{formatCurrency(provider.financial_summary.aging_buckets.current)}</td>
                            <td className="text-right">
                              {((provider.financial_summary.aging_buckets.current / provider.financial_summary.total_outstanding) * 100).toFixed(1)}%
                            </td>
                          </tr>
                          <tr className="border-b">
                            <td className="py-2">31-60 days</td>
                            <td className="text-right">{formatCurrency(provider.financial_summary.aging_buckets['30_days'] + provider.financial_summary.aging_buckets['60_days'])}</td>
                            <td className="text-right">
                              {(((provider.financial_summary.aging_buckets['30_days'] + provider.financial_summary.aging_buckets['60_days']) / provider.financial_summary.total_outstanding) * 100).toFixed(1)}%
                            </td>
                          </tr>
                          <tr className="border-b text-orange-600">
                            <td className="py-2">61-90 days</td>
                            <td className="text-right font-medium">{formatCurrency(provider.financial_summary.aging_buckets['90_days'])}</td>
                            <td className="text-right">
                              {((provider.financial_summary.aging_buckets['90_days'] / provider.financial_summary.total_outstanding) * 100).toFixed(1)}%
                            </td>
                          </tr>
                          <tr className="text-red-600">
                            <td className="py-2 font-medium">Over 90 days</td>
                            <td className="text-right font-medium">{formatCurrency(provider.financial_summary.aging_buckets['over_90'])}</td>
                            <td className="text-right">
                              {((provider.financial_summary.aging_buckets['over_90'] / provider.financial_summary.total_outstanding) * 100).toFixed(1)}%
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </CardContent>
                </Card>

                {/* Payment History */}
                <Card>
                  <CardHeader>
                    <CardTitle>Payment History</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="overflow-x-auto">
                      <table className="min-w-full divide-y divide-gray-200">
                        <thead>
                          <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid To</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Posted By</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                          {provider.payment_history.map((payment) => (
                            <tr key={payment.id}>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {format(new Date(payment.date), 'MMM d, yyyy')}
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {formatCurrency(payment.amount)}
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {payment.method}
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {payment.reference}
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <a href={route('product-requests.show', payment.order_number?.replace('#', ''))} className="text-red-600 hover:text-red-900">
                                  {payment.order_number}
                                </a>
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap">
                                <Badge className={payment.paid_to === 'msc' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'}>
                                  {payment.paid_to === 'msc' ? 'MSC' : 'Manufacturer'}
                                </Badge>
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {payment.posted_by}
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </CardContent>
                </Card>
              </div>
            )}

            {activeTab === 'orders' && (
              <Card>
                <CardHeader>
                  <CardTitle>Order History</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                      <thead>
                        <tr>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order #</th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                          <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-gray-200">
                        {provider.recent_orders.map((order) => (
                          <tr key={order.id}>
                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                              <a href={route('product-requests.show', order.id)} className="text-red-600 hover:text-red-900">
                                #{order.order_number}
                              </a>
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                              {format(new Date(order.date), 'MMM d, yyyy')}
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                              {order.items_count} items
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap">
                              {getOrderStatusBadge(order.status)}
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                              {formatCurrency(order.total_amount)}
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap">
                              {getPaymentStatusBadge(order.payment_status, order.days_outstanding)}
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                              <a href={route('product-requests.show', order.id)} className="text-red-600 hover:text-red-900">
                                View
                              </a>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </CardContent>
              </Card>
            )}

            {activeTab === 'activity' && (
              <Card>
                <CardHeader>
                  <CardTitle>Activity Log</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="flow-root">
                    <ul className="-mb-8">
                      {provider.activity_log.map((activity, index) => (
                        <li key={activity.id}>
                          <div className="relative pb-8">
                            {index !== provider.activity_log.length - 1 && (
                              <span className="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true" />
                            )}
                            <div className="relative flex space-x-3">
                              <div>
                                <span className="h-8 w-8 rounded-full bg-gray-400 flex items-center justify-center ring-8 ring-white">
                                  <Activity className="h-4 w-4 text-white" />
                                </span>
                              </div>
                              <div className="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                <div>
                                  <p className="text-sm text-gray-500">
                                    {activity.description} by <span className="font-medium text-gray-900">{activity.user}</span>
                                  </p>
                                </div>
                                <div className="whitespace-nowrap text-right text-sm text-gray-500">
                                  {formatDistanceToNow(new Date(activity.timestamp), { addSuffix: true })}
                                </div>
                              </div>
                            </div>
                          </div>
                        </li>
                      ))}
                    </ul>
                  </div>
                </CardContent>
              </Card>
            )}
          </div>
        </GlassCard>
      </div>
      {/* Modals */}
      {showAddFacilityModal && (
        <AddProviderFacilityModal
          isOpen={showAddFacilityModal}
          onClose={() => setShowAddFacilityModal(false)}
          providerId={provider.id}
          facilities={availableFacilities}
        />
      )}

      {showAddProductModal && (
        <AddProductModal
          isOpen={showAddProductModal}
          onClose={() => setShowAddProductModal(false)}
          providerId={provider.id}
        />
      )}
    </MainLayout>
  );
}
