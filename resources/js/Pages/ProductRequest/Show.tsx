import React, { useState } from 'react';
import { Head, Link, usePage, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import ProductSelector from '@/Components/ProductCatalog/ProductSelector';
import {
  ArrowLeft,
  User,
  Calendar,
  Building,
  FileText,
  Package,
  CheckCircle,
  Clock,
  AlertCircle,
  DollarSign,
  ClipboardCheck,
  Shield,
  AlertTriangle,
  Send,
  Check,
  X
} from 'lucide-react';

interface ProductRequest {
  id: number;
  request_number: string;
  order_status: string;
  step: number;
  step_description: string;
  wound_type: string;
  expected_service_date: string;
  patient_display: string;
  patient_fhir_id: string;
  facility: {
    id: number;
    name: string;
  } | null;
  payer_name: string;
  clinical_summary?: any;
  mac_validation_results?: any;
  mac_validation_status?: string;
  eligibility_results?: any;
  eligibility_status?: string;
  pre_auth_required?: boolean;
  clinical_opportunities?: any;
  total_amount?: number;
  created_at: string;
  products?: Array<{
    id: number;
    name: string;
    q_code: string;
    quantity: number;
    size?: string;
    unit_price: number;
    total_price: number;
  }>;
}

interface SelectedProduct {
  product_id: number;
  quantity: number;
  size?: string;
  product?: any;
}

interface RoleRestrictions {
  can_view_financials: boolean;
  can_see_discounts: boolean;
  can_see_msc_pricing: boolean;
  can_see_order_totals: boolean;
  pricing_access_level: string;
}

interface Props {
  request: ProductRequest;
  roleRestrictions: RoleRestrictions;
}

const ProductRequestShow: React.FC<Props> = ({ request, roleRestrictions }) => {

  const [selectedProducts, setSelectedProducts] = useState<SelectedProduct[]>(
    request.products?.map(product => ({
      product_id: product.id,
      quantity: product.quantity,
      size: product.size,
    })) || []
  );

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'draft': return 'bg-gray-100 text-gray-800';
      case 'submitted': return 'bg-blue-100 text-blue-800';
      case 'approved': return 'bg-green-100 text-green-800';
      case 'rejected': return 'bg-red-100 text-red-800';
      case 'processing': return 'bg-yellow-100 text-yellow-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'approved': return <CheckCircle className="w-4 h-4" />;
      case 'processing': return <Clock className="w-4 h-4" />;
      case 'rejected': return <AlertCircle className="w-4 h-4" />;
      default: return <FileText className="w-4 h-4" />;
    }
  };

  const formatPrice = (price: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(price);
  };

  const handleProductsChange = (products: SelectedProduct[]) => {
    setSelectedProducts(products);
    // Here you could make an API call to update the product request
    console.log('Products updated:', products);
  };

  const handleSubmit = () => {
    if (confirm('Are you sure you want to submit this request for review?')) {
      router.post(`/product-requests/${request.id}/submit`, {}, {
        onSuccess: () => {
          // The backend will handle the redirect
          router.visit('/product-requests');
        }
      });
    }
  };

  const handleApprove = () => {
    if (confirm('Are you sure you want to approve this request?')) {
      router.post(`/product-requests/${request.id}/approve`, {
        notify_provider: true
      }, {
        onSuccess: () => {
          router.reload();
        }
      });
    }
  };

  const handleReject = () => {
    if (confirm('Are you sure you want to reject this request?')) {
      router.post(`/product-requests/${request.id}/reject`, {
        reason: 'Request does not meet clinical criteria',
        category: 'clinical',
        notify_provider: true
      }, {
        onSuccess: () => {
          router.reload();
        }
      });
    }
  };

  return (
    <MainLayout>
      <Head title={`Product Request ${request.request_number}`} />

      <div className="py-6">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header */}
          <div className="mb-6">
            <div className="flex items-center justify-between">
              <div className="flex items-center space-x-4">
                <Link
                  href="/product-requests"
                  className="inline-flex items-center text-sm text-gray-500 hover:text-gray-700"
                >
                  <ArrowLeft className="w-4 h-4 mr-1" />
                  Back to Product Requests
                </Link>
              </div>
              {/* Action Buttons */}
              <div className="flex items-center space-x-3">
                {request.order_status === 'draft' && (
                  <button
                    onClick={handleSubmit}
                    className="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700"
                  >
                    <Send className="w-4 h-4 mr-2" />
                    Submit for Review
                  </button>
                )}
                {roleRestrictions.can_approve_requests && request.order_status === 'submitted' && (
                  <>
                    <button
                      onClick={handleApprove}
                      className="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700"
                    >
                      <Check className="w-4 h-4 mr-2" />
                      Approve
                    </button>
                    <button
                      onClick={handleReject}
                      className="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700"
                    >
                      <X className="w-4 h-4 mr-2" />
                      Reject
                    </button>
                  </>
                )}
              </div>
            </div>

            <div className="mt-4">
              <div className="flex items-center justify-between">
                <div>
                  <h1 className="text-2xl font-bold text-gray-900">
                    {request.request_number}
                  </h1>
                  <p className="text-sm text-gray-600 mt-1">
                    Created on {request.created_at}
                  </p>
                </div>
                <div className="flex items-center space-x-3">
                  <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${getStatusColor(request.order_status)}`}>
                    {getStatusIcon(request.order_status)}
                    <span className="ml-1 capitalize">{request.order_status}</span>
                  </span>
                  <span className="text-sm text-gray-500">
                    Step {request.step}: {request.step_description}
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Main Content Grid */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Left Column - Patient & Clinical Info */}
            <div className="lg:col-span-2 space-y-6">
              {/* Patient Information */}
              <div className="bg-white shadow rounded-lg p-6">
                <div className="flex items-center mb-4">
                  <User className="w-5 h-5 text-blue-600 mr-2" />
                  <h3 className="text-lg font-medium text-gray-900">Patient Information</h3>
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="text-sm font-medium text-gray-500">Patient ID</label>
                    <p className="text-sm text-gray-900">{request.patient_display}</p>
                  </div>
                  <div>
                    <label className="text-sm font-medium text-gray-500">Wound Type</label>
                    <p className="text-sm text-gray-900">{request.wound_type}</p>
                  </div>
                  <div>
                    <label className="text-sm font-medium text-gray-500">Expected Service Date</label>
                    <p className="text-sm text-gray-900">{request.expected_service_date}</p>
                  </div>
                </div>
              </div>

              {/* Clinical Assessment */}
              {request.clinical_summary && (
                <div className="bg-white shadow rounded-lg p-6">
                  <div className="flex items-center mb-4">
                    <ClipboardCheck className="w-5 h-5 text-indigo-600 mr-2" />
                    <h3 className="text-lg font-medium text-gray-900">Clinical Assessment</h3>
                  </div>
                  <div className="space-y-4">
                    {request.clinical_summary.wound_characteristics && (
                      <div>
                        <h4 className="text-sm font-medium text-gray-700 mb-2">Wound Characteristics</h4>
                        <div className="bg-gray-50 rounded-md p-3">
                          <pre className="text-sm text-gray-600 whitespace-pre-wrap">
                            {JSON.stringify(request.clinical_summary.wound_characteristics, null, 2)}
                          </pre>
                        </div>
                      </div>
                    )}
                    {request.clinical_summary.conservative_care_provided && (
                      <div>
                        <h4 className="text-sm font-medium text-gray-700 mb-2">Conservative Care</h4>
                        <div className="bg-gray-50 rounded-md p-3">
                          <pre className="text-sm text-gray-600 whitespace-pre-wrap">
                            {JSON.stringify(request.clinical_summary.conservative_care_provided, null, 2)}
                          </pre>
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              )}

              {/* Validation & Eligibility */}
              <div className="bg-white shadow rounded-lg p-6">
                <div className="flex items-center mb-4">
                  <Shield className="w-5 h-5 text-purple-600 mr-2" />
                  <h3 className="text-lg font-medium text-gray-900">Validation & Eligibility</h3>
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="text-sm font-medium text-gray-500">MAC Validation</label>
                    <div className="flex items-center mt-1">
                      {request.mac_validation_status === 'passed' ? (
                        <CheckCircle className="w-4 h-4 text-green-500 mr-1" />
                      ) : request.mac_validation_status === 'failed' ? (
                        <AlertCircle className="w-4 h-4 text-red-500 mr-1" />
                      ) : (
                        <Clock className="w-4 h-4 text-gray-400 mr-1" />
                      )}
                      <span className={`text-sm ${
                        request.mac_validation_status === 'passed' ? 'text-green-600' :
                        request.mac_validation_status === 'failed' ? 'text-red-600' :
                        'text-gray-600'
                      }`}>
                        {request.mac_validation_status || 'Pending'}
                      </span>
                    </div>
                  </div>
                  <div>
                    <label className="text-sm font-medium text-gray-500">Eligibility Status</label>
                    <div className="flex items-center mt-1">
                      {request.eligibility_status === 'eligible' ? (
                        <CheckCircle className="w-4 h-4 text-green-500 mr-1" />
                      ) : request.eligibility_status === 'ineligible' ? (
                        <AlertCircle className="w-4 h-4 text-red-500 mr-1" />
                      ) : (
                        <Clock className="w-4 h-4 text-gray-400 mr-1" />
                      )}
                      <span className={`text-sm ${
                        request.eligibility_status === 'eligible' ? 'text-green-600' :
                        request.eligibility_status === 'ineligible' ? 'text-red-600' :
                        'text-gray-600'
                      }`}>
                        {request.eligibility_status || 'Pending'}
                      </span>
                    </div>
                  </div>
                  {request.pre_auth_required && (
                    <div className="col-span-2">
                      <div className="flex items-center text-sm text-amber-600 bg-amber-50 p-2 rounded-md">
                        <AlertTriangle className="w-4 h-4 mr-2" />
                        Prior Authorization Required
                      </div>
                    </div>
                  )}
                </div>
              </div>

              {/* Current Products */}
              <div className="bg-white shadow rounded-lg p-6">
                <div className="flex items-center justify-between mb-4">
                  <div className="flex items-center">
                    <Package className="w-5 h-5 text-indigo-600 mr-2" />
                    <h3 className="text-lg font-medium text-gray-900">Current Products</h3>
                  </div>
                  {request.total_amount && (
                    <div className="text-lg font-semibold text-gray-900">
                      Total: {formatPrice(request.total_amount)}
                    </div>
                  )}
                </div>
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                      <tr>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Product
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Q-Code
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Quantity
                        </th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                          Size
                        </th>
                        {roleRestrictions.can_view_financials && (
                          <>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                              Unit Price
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                              Total
                            </th>
                          </>
                        )}
                      </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                      {request.products?.map((product) => (
                        <tr key={product.id}>
                          <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {product.name}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            Q{product.q_code}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {product.quantity}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {product.size ? `${product.size} cm²` : '-'}
                          </td>
                          {roleRestrictions.can_view_financials && (
                            <>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {formatPrice(product.unit_price)}
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {formatPrice(product.total_price)}
                              </td>
                            </>
                          )}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            {/* Right Column - Facility & Recommendations */}
            <div className="space-y-6">
              {/* Facility & Payer */}
              <div className="bg-white shadow rounded-lg p-6">
                <div className="flex items-center mb-4">
                  <Building className="w-5 h-5 text-green-600 mr-2" />
                  <h3 className="text-lg font-medium text-gray-900">Facility & Payer</h3>
                </div>
                <div className="space-y-3">
                  <div>
                    <label className="text-sm font-medium text-gray-500">Facility</label>
                    <p className="text-sm text-gray-900">{request.facility?.name || 'No facility'}</p>
                  </div>
                  <div>
                    <label className="text-sm font-medium text-gray-500">Payer</label>
                    <p className="text-sm text-gray-900">{request.payer_name}</p>
                  </div>
                </div>
              </div>

              {/* AI Recommendations */}
              {request.order_status === 'draft' && (
                <div className="bg-white shadow rounded-lg p-6">
                  <h3 className="text-lg font-medium text-gray-900 mb-4">
                    AI-Powered Product Recommendations
                  </h3>
                  <ProductSelector
                    selectedProducts={selectedProducts}
                    onProductsChange={handleProductsChange}
                    recommendationContext={request.wound_type}
                    productRequestId={request.id}
                    roleRestrictions={roleRestrictions}
                    showCart={true}
                    title="Clinical Product Recommendations"
                    description="AI-enhanced recommendations tailored to this patient's specific clinical needs"
                  />
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
};

export default ProductRequestShow;
