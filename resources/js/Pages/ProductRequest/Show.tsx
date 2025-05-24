import React, { useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
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
  DollarSign
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

interface Props {
  request: ProductRequest;
}

const ProductRequestShow: React.FC<Props> = ({ request }) => {
  const { props } = usePage<any>();
  const userRole = props.userRole || 'provider';

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

          {/* Request Details */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            {/* Patient Information */}
            <div className="bg-white shadow rounded-lg p-6">
              <div className="flex items-center mb-4">
                <User className="w-5 h-5 text-blue-600 mr-2" />
                <h3 className="text-lg font-medium text-gray-900">Patient Information</h3>
              </div>
              <div className="space-y-3">
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
                {request.pre_auth_required && (
                  <div className="flex items-center text-sm text-amber-600">
                    <AlertCircle className="w-4 h-4 mr-1" />
                    Prior Authorization Required
                  </div>
                )}
              </div>
            </div>

            {/* Order Summary */}
            <div className="bg-white shadow rounded-lg p-6">
              <div className="flex items-center mb-4">
                <DollarSign className="w-5 h-5 text-purple-600 mr-2" />
                <h3 className="text-lg font-medium text-gray-900">Order Summary</h3>
              </div>
              <div className="space-y-3">
                <div>
                  <label className="text-sm font-medium text-gray-500">Total Products</label>
                  <p className="text-sm text-gray-900">{request.products?.length || 0}</p>
                </div>
                {request.total_amount && (
                  <div>
                    <label className="text-sm font-medium text-gray-500">Total Amount</label>
                    <p className="text-lg font-semibold text-gray-900">{formatPrice(request.total_amount)}</p>
                  </div>
                )}
                <div>
                  <label className="text-sm font-medium text-gray-500">MAC Validation</label>
                  <p className={`text-sm ${request.mac_validation_status === 'validated' ? 'text-green-600' : 'text-gray-500'}`}>
                    {request.mac_validation_status || 'Pending'}
                  </p>
                </div>
              </div>
            </div>
          </div>

          {/* Current Products */}
          {request.products && request.products.length > 0 && (
            <div className="bg-white shadow rounded-lg p-6 mb-8">
              <div className="flex items-center mb-4">
                <Package className="w-5 h-5 text-indigo-600 mr-2" />
                <h3 className="text-lg font-medium text-gray-900">Current Products</h3>
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
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Unit Price
                      </th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Total
                      </th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {request.products.map((product) => (
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
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          {formatPrice(product.unit_price)}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                          {formatPrice(product.total_price)}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* AI-Powered Product Recommendations */}
          <div className="bg-white shadow rounded-lg p-6">
            <div className="mb-6">
              <h3 className="text-lg font-medium text-gray-900 mb-2">
                AI-Powered Product Recommendations
              </h3>
              <p className="text-sm text-gray-600">
                Get intelligent product recommendations based on clinical assessment, patient factors, and evidence-based protocols.
              </p>
            </div>

            <ProductSelector
              selectedProducts={selectedProducts}
              onProductsChange={handleProductsChange}
              recommendationContext={request.wound_type}
              productRequestId={request.id}
              userRole={userRole}
              showCart={true}
              title="Clinical Product Recommendations"
              description="AI-enhanced recommendations tailored to this patient's specific clinical needs"
            />
          </div>
        </div>
      </div>
    </MainLayout>
  );
};

export default ProductRequestShow;
