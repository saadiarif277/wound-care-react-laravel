import React, { useState, useEffect } from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import {
  FiShoppingCart, FiFileText, FiPlus, FiSearch, FiFilter,
  FiEye, FiEdit, FiCheckCircle, FiClock, FiAlertTriangle,
  FiDownload, FiUpload, FiCalendar, FiDollarSign, FiInfo,
  FiChevronRight, FiChevronLeft, FiCheck, FiX, FiSave
} from 'react-icons/fi';
import { api, handleApiResponse } from '@/lib/api';

interface Order {
  id: string;
  order_number: string;
  organization_name: string;
  facility_name: string;
  patient_fhir_id: string;
  status: string;
  order_date: string;
  items_count: number;
  total_amount?: number;
  sales_rep_name: string;
  expected_service_date?: string;
  docuseal_generation_status?: string;
  mac_validation?: string;
  eligibility_status?: string;
  pre_auth_status?: string;
}

interface DocuSealSubmission {
  id: string;
  order_number: string;
  document_type: string;
  status: 'pending' | 'completed' | 'expired' | 'cancelled' | 'overdue';
  created_at: string;
  completed_at?: string;
  signer_name: string;
  signer_email: string;
  signing_url?: string;
  download_url?: string;
}

type TabType = 'processing' | 'documents' | 'create';

export default function OrderManagement() {
  const [activeTab, setActiveTab] = useState<TabType>('processing');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [searchTerm, setSearchTerm] = useState('');

  // Data states
  const [orders, setOrders] = useState<Order[]>([]);
  const [docuSealSubmissions, setDocuSealSubmissions] = useState<DocuSealSubmission[]>([]);

  // Create Order Form State
  const [step, setStep] = useState(1);
  const [orderForm, setOrderForm] = useState({
    orderNumber: `ORD-${new Date().getTime()}`,
    doctorFacilityName: '',
    patientHash: '',
    dateOfService: new Date(),
    creditTerms: 'net60',
    sku: '',
    nationalAsp: 0,
    pricePerSqCm: 0,
    expectedReimbursement: 0,
    graphType: '',
    productName: '',
    graphSize: '',
    units: 1,
    qCode: '',
    paymentStatus: 'pending'
  });

  // Stats
  const [stats, setStats] = useState({
    totalOrders: 0,
    pendingApproval: 0,
    pendingDocuments: 0,
    completedToday: 0
  });

  // Fetch data based on active tab
  const fetchData = async () => {
    setLoading(true);
    setError(null);

    try {
      switch (activeTab) {
        case 'processing':
          const ordersResponse = await api.orders.getAll({ search: searchTerm });
          setOrders(ordersResponse.data);
          break;

        case 'documents':
          const documentsResponse = await api.docuseal.getSubmissions({ search: searchTerm });
          setDocuSealSubmissions(documentsResponse.data);
          break;

        case 'create':
          // No API call needed for create form
          break;
      }

      // Fetch stats
      await fetchStats();

    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch data');
      console.error('Error fetching order data:', err);
    } finally {
      setLoading(false);
    }
  };

  const fetchStats = async () => {
    try {
      const analytics = await api.orders.getAnalytics();
      setStats({
        totalOrders: analytics.total_orders || 0,
        pendingApproval: analytics.pending_approval || 0,
        pendingDocuments: analytics.pending_documents || 0,
        completedToday: analytics.completed_today || 0
      });
    } catch (err) {
      console.error('Error fetching stats:', err);
    }
  };

  useEffect(() => {
    fetchData();
  }, [activeTab, searchTerm]);

  const tabs = [
    { id: 'processing', label: 'Order Processing', icon: FiShoppingCart, count: stats.pendingApproval },
    { id: 'documents', label: 'Document Generation', icon: FiFileText, count: stats.pendingDocuments },
    { id: 'create', label: 'Manual Order Creation', icon: FiPlus, count: null }
  ];

  const getStatusBadge = (status: string) => {
    const baseClasses = "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium";

    switch (status.toLowerCase()) {
      case 'approved':
      case 'completed':
      case 'fulfilled':
        return `${baseClasses} bg-green-100 text-green-800`;
      case 'pending':
      case 'processing':
        return `${baseClasses} bg-yellow-100 text-yellow-800`;
      case 'rejected':
      case 'cancelled':
      case 'expired':
      case 'overdue':
        return `${baseClasses} bg-red-100 text-red-800`;
      case 'shipped':
        return `${baseClasses} bg-blue-100 text-blue-800`;
      default:
        return `${baseClasses} bg-gray-100 text-gray-800`;
    }
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
      day: 'numeric'
    });
  };

  const handleApproveOrder = async (orderId: string) => {
    try {
      await api.orders.approve(orderId, { notes: 'Approved from order management' });
      await fetchData(); // Refresh data
    } catch (err) {
      console.error('Error approving order:', err);
    }
  };

  const handleRejectOrder = async (orderId: string) => {
    try {
      await api.orders.reject(orderId, { reason: 'Rejected from order management' });
      await fetchData(); // Refresh data
    } catch (err) {
      console.error('Error rejecting order:', err);
    }
  };

  const handleGenerateDocuments = async (orderId: string) => {
    try {
      await api.docuseal.generateDocument(orderId);
      await fetchData(); // Refresh data
    } catch (err) {
      console.error('Error generating documents:', err);
    }
  };

  const handleOrderFormSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await api.orders.create(orderForm);
      alert('Order created successfully!');
      // Reset form
      setOrderForm({
        orderNumber: `ORD-${new Date().getTime()}`,
        doctorFacilityName: '',
        patientHash: '',
        dateOfService: new Date(),
        creditTerms: 'net60',
        sku: '',
        nationalAsp: 0,
        pricePerSqCm: 0,
        expectedReimbursement: 0,
        graphType: '',
        productName: '',
        graphSize: '',
        units: 1,
        qCode: '',
        paymentStatus: 'pending'
      });
      setStep(1);
    } catch (err) {
      console.error('Error creating order:', err);
      alert('Failed to create order');
    }
  };

  // Render functions for each tab
  const renderOrderProcessing = () => (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h3 className="text-lg font-medium text-gray-900">Order Processing & Approval</h3>
        <div className="flex space-x-2">
          <select className="border border-gray-300 rounded-md px-3 py-2 text-sm">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>
          <button className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 flex items-center gap-2">
            <FiDownload className="w-4 h-4" />
            Export
          </button>
        </div>
      </div>

      <div className="bg-white shadow overflow-hidden sm:rounded-md">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Order Details
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Organization / Facility
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Validation
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Total
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {orders.map((order) => (
                <tr key={order.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm font-medium text-gray-900">{order.order_number}</div>
                    <div className="text-sm text-gray-500">
                      {formatDate(order.order_date)} • {order.items_count} items
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900">{order.organization_name}</div>
                    <div className="text-sm text-gray-500">{order.facility_name}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className={getStatusBadge(order.status)}>
                      {order.status}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex flex-col space-y-1">
                      {order.mac_validation && (
                        <span className={getStatusBadge(order.mac_validation)}>
                          MAC: {order.mac_validation}
                        </span>
                      )}
                      {order.eligibility_status && (
                        <span className={getStatusBadge(order.eligibility_status)}>
                          Eligibility: {order.eligibility_status}
                        </span>
                      )}
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {order.total_amount ? formatCurrency(order.total_amount) : '-'}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <div className="flex space-x-2">
                      <Link href={`/admin/orders/${order.id}`} className="text-blue-600 hover:text-blue-900">
                        <FiEye className="w-4 h-4" title="View Details" />
                      </Link>
                      {order.status === 'pending' && (
                        <>
                          <button
                            onClick={() => handleApproveOrder(order.id)}
                            className="text-green-600 hover:text-green-900"
                            title="Approve Order"
                          >
                            <FiCheckCircle className="w-4 h-4" />
                          </button>
                          <button
                            onClick={() => handleRejectOrder(order.id)}
                            className="text-red-600 hover:text-red-900"
                            title="Reject Order"
                          >
                            <FiX className="w-4 h-4" />
                          </button>
                        </>
                      )}
                      <Link href={`/admin/orders/${order.id}/edit`} className="text-yellow-600 hover:text-yellow-900">
                        <FiEdit className="w-4 h-4" title="Edit Order" />
                      </Link>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {orders.length === 0 && (
          <div className="text-center py-8">
            <FiShoppingCart className="h-12 w-12 mx-auto text-gray-400 mb-4" />
            <p className="text-gray-600 text-lg">No orders found</p>
            <p className="text-gray-500 text-sm">Orders will appear here when they are submitted for processing.</p>
          </div>
        )}
      </div>
    </div>
  );

  const renderDocumentGeneration = () => (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h3 className="text-lg font-medium text-gray-900">Document Generation & Management</h3>
        <div className="flex space-x-2">
          <select className="border border-gray-300 rounded-md px-3 py-2 text-sm">
            <option value="">All Documents</option>
            <option value="InsuranceVerification">Insurance Verification</option>
            <option value="OrderForm">Order Form</option>
            <option value="OnboardingForm">Onboarding Form</option>
          </select>
          <button className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 flex items-center gap-2">
            <FiUpload className="w-4 h-4" />
            Bulk Generate
          </button>
        </div>
      </div>

      <div className="bg-white shadow overflow-hidden sm:rounded-md">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Order / Document
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Signer
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Created
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Completed
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {docuSealSubmissions.map((submission) => (
                <tr key={submission.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex items-center">
                      <FiFileText className="w-5 h-5 text-gray-400 mr-3" />
                      <div>
                        <div className="text-sm font-medium text-gray-900">
                          {submission.order_number}
                        </div>
                        <div className="text-sm text-gray-500">
                          {submission.document_type}
                        </div>
                      </div>
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900">{submission.signer_name}</div>
                    <div className="text-sm text-gray-500">{submission.signer_email}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className={getStatusBadge(submission.status)}>
                      {submission.status}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {formatDate(submission.created_at)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {submission.completed_at ? formatDate(submission.completed_at) : '-'}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <div className="flex space-x-2">
                      {submission.status === 'completed' && submission.download_url && (
                        <button
                          onClick={() => window.open(submission.download_url, '_blank')}
                          className="text-blue-600 hover:text-blue-900 flex items-center"
                          title="Download Document"
                        >
                          <FiDownload className="w-4 h-4" />
                        </button>
                      )}
                      {submission.signing_url && submission.status === 'pending' && (
                        <button
                          onClick={() => window.open(submission.signing_url, '_blank')}
                          className="text-green-600 hover:text-green-900 flex items-center"
                          title="Open for Signing"
                        >
                          <FiEdit className="w-4 h-4" />
                        </button>
                      )}
                      <button className="text-gray-600 hover:text-gray-900" title="View Details">
                        <FiEye className="w-4 h-4" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {docuSealSubmissions.length === 0 && (
          <div className="text-center py-8">
            <FiFileText className="h-12 w-12 mx-auto text-gray-400 mb-4" />
            <p className="text-gray-600 text-lg">No document submissions found</p>
            <p className="text-gray-500 text-sm">Document submissions will appear here when orders are processed.</p>
          </div>
        )}
      </div>
    </div>
  );

  const renderManualOrderCreation = () => (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h3 className="text-lg font-medium text-gray-900">Manual Order Creation</h3>
        <div className="text-sm text-gray-500">
          Step {step} of 3
        </div>
      </div>

      <form onSubmit={handleOrderFormSubmit} className="bg-white rounded-xl shadow-lg overflow-hidden">
        {/* Step 1: Order Information */}
        {step === 1 && (
          <div className="p-6">
            <h2 className="text-xl font-semibold text-indigo-800 mb-6 flex items-center gap-2">
              <FiInfo className="text-indigo-600" />
              Order Information
            </h2>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Order Number
                </label>
                <input
                  type="text"
                  value={orderForm.orderNumber}
                  onChange={(e) => setOrderForm({...orderForm, orderNumber: e.target.value})}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                  disabled
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Doctor/Facility Name *
                </label>
                <input
                  type="text"
                  value={orderForm.doctorFacilityName}
                  onChange={(e) => setOrderForm({...orderForm, doctorFacilityName: e.target.value})}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Patient Hash *
                </label>
                <input
                  type="text"
                  value={orderForm.patientHash}
                  onChange={(e) => setOrderForm({...orderForm, patientHash: e.target.value})}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Date of Service *
                </label>
                <input
                  type="date"
                  value={orderForm.dateOfService.toISOString().split('T')[0]}
                  onChange={(e) => setOrderForm({...orderForm, dateOfService: new Date(e.target.value)})}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Credit Terms
                </label>
                <select
                  value={orderForm.creditTerms}
                  onChange={(e) => setOrderForm({...orderForm, creditTerms: e.target.value})}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                >
                  <option value="net60">Net-60 Terms</option>
                  <option value="net30">Net-30 Terms</option>
                  <option value="net90">Net-90 Terms</option>
                </select>
              </div>
            </div>
          </div>
        )}

        {/* Step 2: Product Details */}
        {step === 2 && (
          <div className="p-6">
            <h2 className="text-xl font-semibold text-indigo-800 mb-6 flex items-center gap-2">
              <FiPlus className="text-indigo-600" />
              Product Details
            </h2>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  SKU *
                </label>
                <input
                  type="text"
                  value={orderForm.sku}
                  onChange={(e) => setOrderForm({...orderForm, sku: e.target.value})}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Product Name *
                </label>
                <input
                  type="text"
                  value={orderForm.productName}
                  onChange={(e) => setOrderForm({...orderForm, productName: e.target.value})}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  National ASP
                </label>
                <input
                  type="number"
                  step="0.01"
                  value={orderForm.nationalAsp}
                  onChange={(e) => setOrderForm({...orderForm, nationalAsp: parseFloat(e.target.value) || 0})}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Price per sq cm
                </label>
                <input
                  type="number"
                  step="0.01"
                  value={orderForm.pricePerSqCm}
                  onChange={(e) => setOrderForm({...orderForm, pricePerSqCm: parseFloat(e.target.value) || 0})}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Graph Type
                </label>
                <input
                  type="text"
                  value={orderForm.graphType}
                  onChange={(e) => setOrderForm({...orderForm, graphType: e.target.value})}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Graph Size
                </label>
                <input
                  type="text"
                  value={orderForm.graphSize}
                  onChange={(e) => setOrderForm({...orderForm, graphSize: e.target.value})}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Units
                </label>
                <input
                  type="number"
                  min="1"
                  value={orderForm.units}
                  onChange={(e) => setOrderForm({...orderForm, units: parseInt(e.target.value) || 1})}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Q Code
                </label>
                <input
                  type="text"
                  value={orderForm.qCode}
                  onChange={(e) => setOrderForm({...orderForm, qCode: e.target.value})}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                />
              </div>
            </div>
          </div>
        )}

        {/* Step 3: Review & Submit */}
        {step === 3 && (
          <div className="p-6">
            <h2 className="text-xl font-semibold text-indigo-800 mb-6 flex items-center gap-2">
              <FiCheck className="text-indigo-600" />
              Review & Submit
            </h2>

            <div className="space-y-6">
              <div className="border rounded-lg p-4">
                <h3 className="font-medium text-lg mb-3 text-indigo-700">Order Information</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div><span className="font-medium">Order Number:</span> {orderForm.orderNumber}</div>
                  <div><span className="font-medium">Doctor/Facility:</span> {orderForm.doctorFacilityName}</div>
                  <div><span className="font-medium">Patient Hash:</span> {orderForm.patientHash}</div>
                  <div><span className="font-medium">Date of Service:</span> {orderForm.dateOfService.toLocaleDateString()}</div>
                  <div><span className="font-medium">Credit Terms:</span> {orderForm.creditTerms.toUpperCase()}</div>
                </div>
              </div>

              <div className="border rounded-lg p-4">
                <h3 className="font-medium text-lg mb-3 text-indigo-700">Product Details</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div><span className="font-medium">SKU:</span> {orderForm.sku}</div>
                  <div><span className="font-medium">Product Name:</span> {orderForm.productName}</div>
                  <div><span className="font-medium">National ASP:</span> ${orderForm.nationalAsp.toFixed(2)}</div>
                  <div><span className="font-medium">Price per sq cm:</span> ${orderForm.pricePerSqCm.toFixed(2)}</div>
                  <div><span className="font-medium">Graph Type:</span> {orderForm.graphType}</div>
                  <div><span className="font-medium">Units:</span> {orderForm.units}</div>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Navigation */}
        <div className="px-6 py-4 bg-gray-50 flex justify-between items-center">
          <button
            type="button"
            onClick={() => setStep(Math.max(step - 1, 1))}
            disabled={step === 1}
            className={`flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-md ${
              step === 1
                ? 'text-gray-400 cursor-not-allowed'
                : 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50'
            }`}
          >
            <FiChevronLeft className="w-4 h-4" />
            Previous
          </button>

          {step < 3 ? (
            <button
              type="button"
              onClick={() => setStep(step + 1)}
              className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700"
            >
              Next
              <FiChevronRight className="w-4 h-4" />
            </button>
          ) : (
            <button
              type="submit"
              className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700"
            >
              <FiSave className="w-4 h-4" />
              Create Order
            </button>
          )}
        </div>
      </form>
    </div>
  );

  if (loading) {
    return (
      <MainLayout>
        <Head title="Order Management" />
        <div className="min-h-screen flex items-center justify-center">
          <div className="text-center">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
            <p className="text-gray-600">Loading order data...</p>
          </div>
        </div>
      </MainLayout>
    );
  }

  return (
    <MainLayout>
      <Head title="Order Management" />

      <div className="space-y-6">
        {/* Header */}
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Order Management</h1>
            <p className="text-gray-500">
              Process orders, generate documents, and create new orders
            </p>
          </div>

          {/* Search */}
          <div className="relative">
            <input
              type="text"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              placeholder="Search orders..."
              className="w-64 pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <FiSearch className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
          </div>
        </div>

        {/* Stats Cards */}
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <div className="bg-white rounded-lg shadow p-6">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <FiShoppingCart className="h-8 w-8 text-blue-600" />
              </div>
              <div className="ml-5">
                <dl>
                  <dt className="text-sm font-medium text-gray-500">Total Orders</dt>
                  <dd className="text-2xl font-semibold text-gray-900">{stats.totalOrders}</dd>
                </dl>
              </div>
            </div>
          </div>

          <div className="bg-white rounded-lg shadow p-6">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <FiClock className="h-8 w-8 text-yellow-600" />
              </div>
              <div className="ml-5">
                <dl>
                  <dt className="text-sm font-medium text-gray-500">Pending Approval</dt>
                  <dd className="text-2xl font-semibold text-gray-900">{stats.pendingApproval}</dd>
                </dl>
              </div>
            </div>
          </div>

          <div className="bg-white rounded-lg shadow p-6">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <FiFileText className="h-8 w-8 text-purple-600" />
              </div>
              <div className="ml-5">
                <dl>
                  <dt className="text-sm font-medium text-gray-500">Pending Documents</dt>
                  <dd className="text-2xl font-semibold text-gray-900">{stats.pendingDocuments}</dd>
                </dl>
              </div>
            </div>
          </div>

          <div className="bg-white rounded-lg shadow p-6">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <FiCheckCircle className="h-8 w-8 text-green-600" />
              </div>
              <div className="ml-5">
                <dl>
                  <dt className="text-sm font-medium text-gray-500">Completed Today</dt>
                  <dd className="text-2xl font-semibold text-gray-900">{stats.completedToday}</dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        {/* Tabs */}
        <div className="border-b border-gray-200">
          <nav className="-mb-px flex space-x-8">
            {tabs.map((tab) => {
              const Icon = tab.icon;
              return (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id as TabType)}
                  className={`py-2 px-1 border-b-2 font-medium text-sm flex items-center gap-2 ${
                    activeTab === tab.id
                      ? 'border-blue-500 text-blue-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                  }`}
                >
                  <Icon className="w-4 h-4" />
                  {tab.label}
                  {tab.count !== null && tab.count > 0 && (
                    <span className="bg-red-100 text-red-600 py-0.5 px-2.5 rounded-full text-xs">
                      {tab.count}
                    </span>
                  )}
                </button>
              );
            })}
          </nav>
        </div>

        {/* Error State */}
        {error && (
          <div className="bg-red-50 border border-red-200 rounded-md p-4">
            <div className="text-red-700">
              <p className="font-medium">Error loading data</p>
              <p className="text-sm">{error}</p>
            </div>
          </div>
        )}

        {/* Tab Content */}
        <div className="min-h-96">
          {activeTab === 'processing' && renderOrderProcessing()}
          {activeTab === 'documents' && renderDocumentGeneration()}
          {activeTab === 'create' && renderManualOrderCreation()}
        </div>
      </div>
    </MainLayout>
  );
}
