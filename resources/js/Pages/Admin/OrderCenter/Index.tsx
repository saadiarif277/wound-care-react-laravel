import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import {
  Search,
  Eye,
  Clock,
  CheckCircle,
  XCircle,
  Calendar,
  User,
  Building2,
  Package,
  DollarSign,
  ArrowLeft,
  RefreshCw,
} from 'lucide-react';
import { Button } from '@/Components/Button';
import { PatientInsuranceSection } from '@/Pages/QuickRequest/Orders/order/PatientInsuranceSection';
import { ProductSection } from '@/Pages/QuickRequest/Orders/order/ProductSection';
import { FormsSection } from '@/Pages/QuickRequest/Orders/order/FormsSection';
import { ClinicalSection } from '@/Pages/QuickRequest/Orders/order/ClinicalSection';
import { ProviderSection } from '@/Pages/QuickRequest/Orders/order/ProviderSection';
import { SubmissionSection } from '@/Pages/QuickRequest/Orders/order/SubmissionSection';
import { OrderModals } from '@/Pages/QuickRequest/Orders/order/OrderModals';
import OrderDetails from './OrderDetails';

interface Order {
  id: string;
  order_number: string;
  patient_name: string;
  patient_display_id: string;
  provider_name: string;
  facility_name: string;
  manufacturer_name: string;
  product_name: string;
  order_status: string;
  ivr_status: string;
  order_form_status: string;
  total_order_value: number;
  created_at: string;
  action_required: boolean;
}

interface OrderCenterIndexProps {
  orders: {
    data: Order[];
    current_page: number;
    last_page: number;
    total: number;
  };
  statusCounts: Record<string, number>;
  filters: Record<string, string>;
}

const OrderCenterIndex: React.FC<OrderCenterIndexProps> = ({
  orders,
  statusCounts,
  filters,
}) => {
  const [searchTerm, setSearchTerm] = useState(filters.search || '');
  const [selectedOrder, setSelectedOrder] = useState<Order | null>(null);
  const [userRole, setUserRole] = useState<'Provider' | 'OM' | 'Admin'>('Admin');
  const [openSections, setOpenSections] = useState<Record<string, boolean>>({
    patient: true,
    product: true,
    forms: true,
    clinical: true,
    provider: true,
    submission: true,
  });
  const [showSubmitModal, setShowSubmitModal] = useState(false);
  const [showSuccessModal, setShowSuccessModal] = useState(false);
  const [showNoteModal, setShowNoteModal] = useState(false);
  const [confirmationChecked, setConfirmationChecked] = useState(false);
  const [adminNote, setAdminNote] = useState('');
  const [orderSubmitted, setOrderSubmitted] = useState(false);

  // Theme setup with proper fallbacks
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme] || themes.dark;
  } catch (e) {
    t = themes.dark;
  }

  if (!t) {
    t = themes.dark;
  }

  const handleSearch = () => {
    router.get(route('admin.orders.index'), {
      search: searchTerm,
    }, {
      preserveState: true,
      replace: true,
    });
  };

  const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(amount);
  };

  const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  };

  const getStatusColor = (status: string): string => {
    switch (status.toLowerCase()) {
      case 'pending':
      case 'pending ivr':
      case 'pending_ivr':
      case 'draft':
        return 'bg-yellow-100 text-yellow-800';
      case 'ivr sent':
      case 'ivr verified':
      case 'sent':
      case 'verified':
      case 'submitted to manufacturer':
        return 'bg-blue-100 text-blue-800';
      case 'approved':
      case 'confirmed by manufacturer':
        return 'bg-green-100 text-green-800';
      case 'denied':
      case 'rejected':
      case 'canceled':
        return 'bg-red-100 text-red-800';
      case 'completed':
        return 'bg-emerald-100 text-emerald-800';
      case 'n/a':
        return 'bg-gray-100 text-gray-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  const handleViewOrderDetails = (order: Order) => {
    setSelectedOrder(order);
  };

  const handleBackToList = () => {
    setSelectedOrder(null);
  };

  const toggleSection = (section: string) => {
    setOpenSections(prev => ({
      ...prev,
      [section]: !prev[section]
    }));
  };

  const isOrderComplete = () => {
    // Add your order completion logic here
    return true;
  };

  const handleSubmitOrder = () => {
    setShowSubmitModal(true);
  };

  const confirmSubmission = () => {
    setShowSubmitModal(false);
    setOrderSubmitted(true);
    setShowSuccessModal(true);
  };

  const handleAddNote = () => {
    setShowNoteModal(false);
    // Handle adding note logic
  };

  const finishSubmission = () => {
    setShowSuccessModal(false);
    handleBackToList();
  };

  // If viewing order details, use the new OrderDetails component
  if (selectedOrder) {
    return <OrderDetails order={selectedOrder} onBack={handleBackToList} />;
  }

  // Main orders table view
  return (
    <MainLayout>
      <Head title="Order Center - Admin" />

      <div className={cn("min-h-screen", t?.background?.base || "bg-gray-50")}>
        <div className="max-w-7xl mx-auto p-6">
          {/* Header */}
          <div className="mb-8">
            <div className="flex justify-between items-start">
              <div>
                <h1 className={cn("text-3xl font-bold mb-2", t?.text?.primary || "text-gray-900")}>
                  Order Center
                </h1>
                <p className={cn(t?.text?.secondary || "text-gray-600")}>
                  Manage orders from the Quick Request workflow
                </p>
              </div>
              <div className="flex items-center gap-3">
                <button
                  onClick={() => router.get(route('admin.orders.index'))}
                  className={cn(
                    "px-4 py-2 rounded-lg font-medium flex items-center gap-2",
                    t?.button?.secondary?.base || "bg-gray-200 hover:bg-gray-300 text-gray-700"
                  )}
                >
                  <RefreshCw className="h-4 w-4" />
                  Refresh
                </button>
              </div>
            </div>

            {/* Stats Cards */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
              <div className={cn("p-4 rounded-xl", t?.glass?.card || "bg-white shadow-lg")}>
                <div className="flex items-center justify-between">
                  <div>
                    <p className={cn("text-sm font-medium", t?.text?.muted || "text-gray-500")}>Total Orders</p>
                    <p className={cn("text-2xl font-bold", t?.text?.primary || "text-gray-900")}>{orders.total}</p>
                  </div>
                </div>
              </div>

              <div className={cn("p-4 rounded-xl", t?.glass?.card || "bg-white shadow-lg")}>
                <div className="flex items-center justify-between">
                  <div>
                    <p className={cn("text-sm font-medium", t?.text?.muted || "text-gray-500")}>Pending Review</p>
                    <p className="text-2xl font-bold text-yellow-600">{statusCounts.pending || 0}</p>
                  </div>
                  <Clock className="h-8 w-8 text-yellow-600" />
                </div>
              </div>

              <div className={cn("p-4 rounded-xl", t?.glass?.card || "bg-white shadow-lg")}>
                <div className="flex items-center justify-between">
                  <div>
                    <p className={cn("text-sm font-medium", t?.text?.muted || "text-gray-500")}>Approved</p>
                    <p className="text-2xl font-bold text-green-600">{statusCounts.approved || 0}</p>
                  </div>
                  <CheckCircle className="h-8 w-8 text-green-600" />
                </div>
              </div>

              <div className={cn("p-4 rounded-xl", t?.glass?.card || "bg-white shadow-lg")}>
                <div className="flex items-center justify-between">
                  <div>
                    <p className={cn("text-sm font-medium", t?.text?.muted || "text-gray-500")}>Action Required</p>
                    <p className="text-2xl font-bold text-red-600">{statusCounts.action_required || 0}</p>
                  </div>
                  <XCircle className="h-8 w-8 text-red-600" />
                </div>
              </div>
            </div>
          </div>

          {/* Search */}
          <div className={cn("mb-6 p-4 rounded-xl", t?.glass?.card || "bg-white shadow-lg")}>
            <div className="flex gap-4">
              <div className="flex-1">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                  <input
                    type="text"
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    onKeyPress={(e) => e.key === 'Enter' && handleSearch()}
                    placeholder="Search orders, patients, providers..."
                    className={cn(
                      "w-full pl-10 pr-4 py-2 rounded-lg border",
                      t?.input?.base || "border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                    )}
                  />
                </div>
              </div>
              <button
                onClick={handleSearch}
                className={cn(
                  "px-4 py-2 rounded-lg font-medium",
                  t?.button?.primary?.base || "bg-blue-600 hover:bg-blue-700 text-white"
                )}
              >
                Search
              </button>
            </div>
          </div>

          {/* Orders Table */}
          <div className={cn("rounded-xl overflow-hidden", t?.glass?.card || "bg-white shadow-lg")}>
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className={cn("border-b", t?.glass?.border || "border-gray-200")}>
                  <tr>
                    <th className={cn("px-6 py-4 text-left text-sm font-medium", t?.text?.primary || "text-gray-900")}>
                      Order Details
                    </th>
                    <th className={cn("px-6 py-4 text-left text-sm font-medium", t?.text?.primary || "text-gray-900")}>
                      Patient
                    </th>
                    <th className={cn("px-6 py-4 text-left text-sm font-medium", t?.text?.primary || "text-gray-900")}>
                      Provider
                    </th>
                    <th className={cn("px-6 py-4 text-left text-sm font-medium", t?.text?.primary || "text-gray-900")}>
                      Product
                    </th>
                    <th className={cn("px-6 py-4 text-left text-sm font-medium", t?.text?.primary || "text-gray-900")}>
                      IVR Status
                    </th>
                    <th className={cn("px-6 py-4 text-left text-sm font-medium", t?.text?.primary || "text-gray-900")}>
                      Order Status
                    </th>
                    <th className={cn("px-6 py-4 text-left text-sm font-medium", t?.text?.primary || "text-gray-900")}>
                      Total Value
                    </th>
                    <th className={cn("px-6 py-4 text-left text-sm font-medium", t?.text?.primary || "text-gray-900")}>
                      Created
                    </th>
                    <th className={cn("px-6 py-4 text-left text-sm font-medium", t?.text?.primary || "text-gray-900")}>
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {orders.data.map((order) => (
                    <tr key={order.id} className={cn("border-b", t?.glass?.border || "border-gray-200")}>
                      <td className="px-6 py-4">
                        <div>
                          <div className="flex items-center gap-2">
                            <span className={cn("font-medium", t?.text?.primary || "text-gray-900")}>
                              {order.order_number}
                            </span>
                            {order.action_required && (
                              <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                Action Required
                              </span>
                            )}
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div>
                          <div className={cn("font-medium", t?.text?.primary || "text-gray-900")}>
                            {order.patient_name}
                          </div>
                          <div className={cn("text-sm", t?.text?.muted || "text-gray-500")}>
                            {order.patient_display_id}
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div>
                          <div className={cn("font-medium", t?.text?.primary || "text-gray-900")}>
                            {order.provider_name}
                          </div>
                          <div className={cn("text-sm", t?.text?.muted || "text-gray-500")}>
                            {order.facility_name}
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div>
                          <div className={cn("font-medium", t?.text?.primary || "text-gray-900")}>
                            {order.product_name}
                          </div>
                          <div className={cn("text-sm", t?.text?.muted || "text-gray-500")}>
                            {order.manufacturer_name}
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <span className={cn(
                          "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium",
                          getStatusColor(order.ivr_status || 'pending_ivr')
                        )}>
                          {(order.ivr_status || 'pending_ivr').toUpperCase()}
                        </span>
                      </td>
                      <td className="px-6 py-4">
                        <span className={cn(
                          "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium",
                          getStatusColor(order.order_form_status || 'Pending')
                        )}>
                          {(order.order_form_status || 'Pending').toUpperCase()}
                        </span>
                      </td>
                      <td className="px-6 py-4">
                        <span className={cn("font-medium", t?.text?.primary || "text-gray-900")}>
                          {formatCurrency(order.total_order_value)}
                        </span>
                      </td>
                      <td className="px-6 py-4">
                        <span className={cn("text-sm", t?.text?.secondary || "text-gray-600")}>
                          {formatDate(order.created_at)}
                        </span>
                      </td>
                      <td className="px-6 py-4">
                        <button
                          onClick={() => handleViewOrderDetails(order)}
                          className={cn(
                            "px-3 py-1 rounded-lg text-sm font-medium",
                            t?.button?.primary?.base || "bg-blue-600 hover:bg-blue-700 text-white"
                          )}
                        >
                          <Eye className="h-4 w-4" />
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
};

export default OrderCenterIndex;
