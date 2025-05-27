import React from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { PricingDisplay } from '@/Components/Pricing/PricingDisplay';
import {
  FiArrowLeft,
  FiEdit,
  FiDownload,
  FiPackage,
  FiTag,
  FiDollarSign,
  FiFileText,
  FiInfo,
  FiTrendingUp,
  FiCheck,
  FiAlertCircle
} from 'react-icons/fi';

interface Product {
  id: number;
  name: string;
  sku: string;
  q_code: string;
  manufacturer: string;
  category: string;
  description: string;
  price_per_sq_cm: number;
  msc_price?: number; // Optional based on role
  available_sizes: number[];
  image_url: string;
  document_urls: string[];
  commission_rate?: number; // Optional based on role
  is_active: boolean;
  graph_type: string;
  created_at: string;
  updated_at: string;
}

interface RoleRestrictions {
  can_view_financials: boolean;
  can_see_discounts: boolean;
  can_see_msc_pricing: boolean;
  can_see_order_totals: boolean;
  pricing_access_level: string;
}

interface Props {
  product: Product;
  roleRestrictions: RoleRestrictions;
}

export default function ProductShow({ product, roleRestrictions }: Props) {
  const formatPrice = (price: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 2,
      maximumFractionDigits: 4,
    }).format(price);
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  };

  const calculateTotalPrice = (size: number, useNationalAsp = false) => {
    const pricePerSqCm = useNationalAsp ? product.price_per_sq_cm : (product.msc_price || product.price_per_sq_cm);
    return pricePerSqCm * size;
  };

  const getSizeRecommendation = (size: number) => {
    if (size <= 4) return 'Small wounds';
    if (size <= 16) return 'Medium wounds';
    if (size <= 36) return 'Large wounds';
    return 'Extra large wounds';
  };

  // Get user role for pricing display
  const getUserRole = () => {
    if (!roleRestrictions.can_see_msc_pricing) return 'office_manager';
    if (roleRestrictions.pricing_access_level === 'limited') return 'msc_subrep';
    return 'provider'; // Default for full access roles
  };

  const userRole = getUserRole();

  return (
    <MainLayout title={product.name}>
      <Head title={`${product.name} - Product Details`} />

      <div className="p-6">
        {/* Header */}
        <div className="mb-8">
          <div className="flex items-center gap-4 mb-4">
            <Link
              href="/products"
              className="flex items-center gap-2 text-gray-600 hover:text-gray-800 transition-colors"
            >
              <FiArrowLeft className="w-4 h-4" />
              Back to Products
            </Link>
          </div>

          <div className="flex flex-col lg:flex-row gap-8">
            {/* Product Image */}
            <div className="lg:w-1/3">
              <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                {product.image_url ? (
                  <img
                    src={product.image_url}
                    alt={product.name}
                    className="w-full h-64 lg:h-80 object-cover"
                  />
                ) : (
                  <div className="w-full h-64 lg:h-80 bg-gradient-to-br from-blue-50 to-gray-100 flex items-center justify-center">
                    <FiPackage className="w-16 h-16 text-gray-400" />
                  </div>
                )}
              </div>
            </div>

            {/* Product Info */}
            <div className="lg:w-2/3">
              <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div className="flex items-start justify-between mb-4">
                  <div>
                    <h1 className="text-3xl font-bold text-gray-900 mb-2">
                      {product.name}
                    </h1>
                    <div className="flex items-center gap-4 text-sm text-gray-500 mb-4">
                      <span>SKU: {product.sku}</span>
                      <span>Q-Code: Q{product.q_code}</span>
                      <span className={`px-2 py-1 rounded-full text-xs font-semibold ${
                        product.is_active
                          ? 'bg-green-100 text-green-800'
                          : 'bg-red-100 text-red-800'
                      }`}>
                        {product.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </div>
                  </div>
                  <Link
                    href={`/products/${product.id}/edit`}
                    className="bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors flex items-center gap-2"
                  >
                    <FiEdit className="w-4 h-4" />
                    Edit Product
                  </Link>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                  <div>
                    <h3 className="font-semibold text-gray-900 mb-3">Product Details</h3>
                    <div className="space-y-2">
                      <div className="flex justify-between">
                        <span className="text-gray-500">Manufacturer:</span>
                        <span className="font-medium">{product.manufacturer}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-gray-500">Category:</span>
                        <span className={`px-2 py-1 rounded-full text-xs font-semibold ${
                          product.category === 'SkinSubstitute'
                            ? 'bg-blue-100 text-blue-800'
                            : product.category === 'Biologic'
                            ? 'bg-green-100 text-green-800'
                            : 'bg-gray-100 text-gray-800'
                        }`}>
                          {product.category}
                        </span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-gray-500">Graph Type:</span>
                        <span className="font-medium">{product.graph_type || 'N/A'}</span>
                      </div>
                      {/* Show commission only if role allows */}
                      {roleRestrictions.can_view_financials && product.commission_rate && (
                        <div className="flex justify-between">
                          <span className="text-gray-500">Commission Rate:</span>
                          <span className="font-medium text-green-600">{product.commission_rate}%</span>
                        </div>
                      )}
                    </div>
                  </div>

                  <div>
                    <h3 className="font-semibold text-gray-900 mb-3">Pricing</h3>
                    <div className="space-y-3">
                      {/* Use PricingDisplay component for role-aware pricing */}
                      <PricingDisplay
                        userRole={userRole as any}
                        product={{
                          nationalAsp: product.price_per_sq_cm,
                          mscPrice: product.msc_price,
                        }}
                        showLabel={true}
                        className="text-sm"
                      />

                      {/* Show discount only if role allows */}
                      {roleRestrictions.can_see_discounts && product.msc_price && (
                        <div className="flex justify-between">
                          <span className="text-gray-500">Discount:</span>
                          <span className="font-medium text-green-600">40%</span>
                        </div>
                      )}

                      <div className="flex justify-between">
                        <span className="text-gray-500">Available Sizes:</span>
                        <span className="font-medium">{product.available_sizes?.length || 0} options</span>
                      </div>

                      {/* Show commission only if role allows */}
                      {roleRestrictions.can_view_financials && product.commission_rate && (
                        <div className="flex justify-between">
                          <span className="text-gray-500">Commission Rate:</span>
                          <span className="font-medium text-green-600">{product.commission_rate}%</span>
                        </div>
                      )}
                    </div>
                  </div>
                </div>

                {product.description && (
                  <div className="mb-6">
                    <h3 className="font-semibold text-gray-900 mb-3">Description</h3>
                    <p className="text-gray-600 leading-relaxed">{product.description}</p>
                  </div>
                )}

                {/* Documents */}
                {product.document_urls && product.document_urls.length > 0 && (
                  <div>
                    <h3 className="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                      <FiFileText className="w-4 h-4" />
                      Product Documents
                    </h3>
                    <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                      {product.document_urls.map((url, index) => (
                        <a
                          key={index}
                          href={url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="flex items-center gap-2 p-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                          <FiDownload className="w-4 h-4 text-gray-500" />
                          <span className="text-sm font-medium">Document {index + 1}</span>
                        </a>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Financial Restriction Notice for Office Managers */}
        {!roleRestrictions.can_see_msc_pricing && (
          <div className="mb-8 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div className="flex">
              <div className="flex-shrink-0">
                <svg className="h-5 w-5 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div className="ml-3">
                <h3 className="text-sm font-medium text-yellow-800">Pricing Information Restricted</h3>
                <div className="mt-2 text-sm text-yellow-700">
                  <p>
                    As an Office Manager, only National ASP pricing is displayed. MSC pricing, discounts, and commission information are not available for your role.
                  </p>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Available Sizes */}
        {product.available_sizes && product.available_sizes.length > 0 && (
          <div className="mb-8">
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
              <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
                <FiTag className="w-5 h-5" />
                Available Sizes & Pricing
              </h2>

              <div className="overflow-x-auto">
                <table className="min-w-full">
                  <thead>
                    <tr className="border-b border-gray-200">
                      <th className="text-left py-3 px-4 font-semibold text-gray-900">Size (cm²)</th>
                      <th className="text-left py-3 px-4 font-semibold text-gray-900">National ASP Total</th>
                      {/* Show MSC pricing columns only if role allows */}
                      {roleRestrictions.can_see_msc_pricing && (
                        <>
                          <th className="text-left py-3 px-4 font-semibold text-gray-900">MSC Price Total</th>
                          <th className="text-left py-3 px-4 font-semibold text-gray-900">Savings</th>
                        </>
                      )}
                      <th className="text-left py-3 px-4 font-semibold text-gray-900">Recommended For</th>
                    </tr>
                  </thead>
                  <tbody>
                    {product.available_sizes.map((size) => {
                      const nationalTotal = calculateTotalPrice(size, true);
                      const mscTotal = calculateTotalPrice(size, false);
                      const savings = nationalTotal - mscTotal;

                      return (
                        <tr key={size} className="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                          <td className="py-3 px-4 font-medium text-gray-900">{size} cm²</td>
                          <td className="py-3 px-4 text-gray-600">{formatPrice(nationalTotal)}</td>
                          {/* Show MSC pricing columns only if role allows */}
                          {roleRestrictions.can_see_msc_pricing && (
                            <>
                              <td className="py-3 px-4 font-semibold text-blue-600">{formatPrice(mscTotal)}</td>
                              <td className="py-3 px-4 font-medium text-green-600">{formatPrice(savings)}</td>
                            </>
                          )}
                          <td className="py-3 px-4 text-sm text-gray-500">{getSizeRecommendation(size)}</td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>

              {/* Show pricing note for office managers */}
              {!roleRestrictions.can_see_msc_pricing && (
                <div className="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                  <p className="text-sm text-yellow-700">
                    <strong>Note:</strong> Only National ASP pricing is displayed. MSC pricing and savings information are not available for your role.
                  </p>
                </div>
              )}
            </div>
          </div>
        )}

        {/* Clinical Information */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
          {/* Key Features */}
          <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
              <FiCheck className="w-5 h-5 text-green-600" />
              Key Features
            </h2>

            <div className="space-y-4">
              {/* Generate features based on category */}
              {product.category === 'SkinSubstitute' && (
                <>
                  <div className="flex items-start gap-3">
                    <FiCheck className="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" />
                    <div>
                      <div className="font-medium text-gray-900">Advanced Skin Substitute</div>
                      <div className="text-sm text-gray-600">Provides structural support for wound closure</div>
                    </div>
                  </div>
                  <div className="flex items-start gap-3">
                    <FiCheck className="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" />
                    <div>
                      <div className="font-medium text-gray-900">Promotes Healing</div>
                      <div className="text-sm text-gray-600">Supports natural wound healing processes</div>
                    </div>
                  </div>
                </>
              )}

              {product.category === 'Biologic' && (
                <>
                  <div className="flex items-start gap-3">
                    <FiCheck className="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" />
                    <div>
                      <div className="font-medium text-gray-900">Biological Matrix</div>
                      <div className="text-sm text-gray-600">Contains natural growth factors and proteins</div>
                    </div>
                  </div>
                  <div className="flex items-start gap-3">
                    <FiCheck className="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" />
                    <div>
                      <div className="font-medium text-gray-900">Cellular Migration</div>
                      <div className="text-sm text-gray-600">Facilitates cell movement and tissue regeneration</div>
                    </div>
                  </div>
                </>
              )}

              <div className="flex items-start gap-3">
                <FiCheck className="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" />
                <div>
                  <div className="font-medium text-gray-900">Multiple Sizes Available</div>
                  <div className="text-sm text-gray-600">Suitable for various wound dimensions</div>
                </div>
              </div>

              <div className="flex items-start gap-3">
                <FiCheck className="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" />
                <div>
                  <div className="font-medium text-gray-900">Quality Assured</div>
                  <div className="text-sm text-gray-600">Meets Medicare coverage criteria</div>
                </div>
              </div>
            </div>
          </div>

          {/* Usage Guidelines */}
          <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
              <FiInfo className="w-5 h-5 text-blue-600" />
              Usage Guidelines
            </h2>

            <div className="space-y-4">
              <div className="p-4 bg-blue-50 rounded-lg border border-blue-200">
                <div className="flex items-start gap-3">
                  <FiAlertCircle className="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" />
                  <div>
                    <div className="font-medium text-blue-900">Application Instructions</div>
                    <div className="text-sm text-blue-700 mt-1">
                      Apply to clean wound bed after appropriate debridement. Secure with appropriate secondary dressing.
                    </div>
                  </div>
                </div>
              </div>

              <div className="space-y-3">
                <div>
                  <div className="font-medium text-gray-900">Common Indications:</div>
                  <ul className="text-sm text-gray-600 mt-1 space-y-1">
                    <li>• Diabetic foot ulcers (DFU)</li>
                    <li>• Venous leg ulcers (VLU)</li>
                    <li>• Pressure ulcers</li>
                    <li>• Surgical wounds</li>
                  </ul>
                </div>

                <div>
                  <div className="font-medium text-gray-900">Reapplication:</div>
                  <div className="text-sm text-gray-600 mt-1">
                    Typically every 1-2 weeks as clinically indicated
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Metadata */}
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
            <FiTrendingUp className="w-5 h-5" />
            Product Metadata
          </h2>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
              <div className="text-sm text-gray-500">Created</div>
              <div className="font-medium text-gray-900">{formatDate(product.created_at)}</div>
            </div>
            <div>
              <div className="text-sm text-gray-500">Last Updated</div>
              <div className="font-medium text-gray-900">{formatDate(product.updated_at)}</div>
            </div>
            <div>
              <div className="text-sm text-gray-500">Product ID</div>
              <div className="font-medium text-gray-900">#{product.id}</div>
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
}
