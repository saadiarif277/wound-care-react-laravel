import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { PricingDisplay } from '@/Components/Pricing/PricingDisplay';
import {
  FiSearch,
  FiFilter,
  FiGrid,
  FiList,
  FiPlus,
  FiEdit,
  FiTrash2,
  FiEye,
  FiPackage,
  FiDollarSign,
  FiBarChart
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
  commission_rate?: number; // Optional based on role
  is_active: boolean;
  created_at: string;
}

interface RoleRestrictions {
  can_view_financials: boolean;
  can_see_discounts: boolean;
  can_see_msc_pricing: boolean;
  can_see_order_totals: boolean;
  pricing_access_level: string;
  commission_access_level: string;
}

interface Props {
  products: {
    data: Product[];
    links: any[];
    total: number;
    per_page: number;
    current_page: number;
  };
  categories: string[];
  manufacturers: string[];
  filters: {
    search?: string;
    category?: string;
    manufacturer?: string;
    sort?: string;
    direction?: string;
  };
  roleRestrictions: RoleRestrictions;
}

export default function ProductsIndex({ products, categories, manufacturers, filters, roleRestrictions }: Props) {
  const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');
  const [showFilters, setShowFilters] = useState(false);
  const [localFilters, setLocalFilters] = useState(filters);

  const handleFilterChange = (key: string, value: string) => {
    const newFilters = { ...localFilters, [key]: value };
    setLocalFilters(newFilters);

    // Remove empty filters
    Object.keys(newFilters).forEach(k => {
      if (!newFilters[k as keyof typeof newFilters]) {
        delete newFilters[k as keyof typeof newFilters];
      }
    });

    router.get('/products', newFilters, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleSort = (field: string) => {
    const direction = localFilters.sort === field && localFilters.direction === 'asc' ? 'desc' : 'asc';
    handleFilterChange('sort', field);
    handleFilterChange('direction', direction);
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    const formData = new FormData(e.target as HTMLFormElement);
    const search = formData.get('search') as string;
    handleFilterChange('search', search);
  };

  const clearFilters = () => {
    setLocalFilters({});
    router.get('/products');
  };

  const formatPrice = (price: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 2,
      maximumFractionDigits: 4,
    }).format(price);
  };

  // Get user role for pricing display
  const getUserRole = () => {
    if (!roleRestrictions.can_see_msc_pricing) return 'office_manager';
    if (roleRestrictions.pricing_access_level === 'limited') return 'msc_subrep';
    return 'provider'; // Default for full access roles
  };

  const userRole = getUserRole();

  const ProductCard = ({ product }: { product: Product }) => (
    <div className="bg-white rounded-xl shadow-sm border border-gray-200 hover:shadow-lg transition-all duration-200 overflow-hidden group">
      <div className="aspect-w-16 aspect-h-9 bg-gray-100 relative">
        {product.image_url ? (
          <img
            src={product.image_url}
            alt={product.name}
            className="w-full h-48 object-cover"
          />
        ) : (
          <div className="w-full h-48 bg-gradient-to-br from-blue-50 to-gray-100 flex items-center justify-center">
            <FiPackage className="w-12 h-12 text-gray-400" />
          </div>
        )}
        <div className="absolute top-3 right-3">
          <span className={`px-2 py-1 text-xs font-semibold rounded-full ${
            product.category === 'SkinSubstitute'
              ? 'bg-blue-100 text-blue-800'
              : product.category === 'Biologic'
              ? 'bg-green-100 text-green-800'
              : 'bg-gray-100 text-gray-800'
          }`}>
            {product.category}
          </span>
        </div>
      </div>

      <div className="p-6">
        <div className="flex items-start justify-between mb-3">
          <div className="flex-1">
            <h3 className="font-semibold text-lg text-gray-900 mb-1 line-clamp-2 group-hover:text-blue-600 transition-colors">
              {product.name}
            </h3>
            <p className="text-sm text-gray-500 mb-2">
              {product.manufacturer} • Q{product.q_code}
            </p>
          </div>
        </div>

        <p className="text-sm text-gray-600 mb-4 line-clamp-2">
          {product.description || 'No description available'}
        </p>

        <div className="space-y-2 mb-4">
          {/* Use PricingDisplay component for role-aware pricing */}
          <PricingDisplay
            roleRestrictions={roleRestrictions}
            product={{
              nationalAsp: product.price_per_sq_cm,
              mscPrice: product.msc_price,
            }}
            showLabel={true}
            className="text-sm"
          />

          <div className="flex justify-between items-center">
            <span className="text-sm text-gray-500">Sizes:</span>
            <span className="text-sm text-gray-700">
              {product.available_sizes?.length || 0} available
            </span>
          </div>

          {/* Show commission rate only if commission access is allowed (RBAC compliant) */}
          {roleRestrictions.commission_access_level !== 'none' && product.commission_rate && (
            <div className="flex justify-between items-center">
              <span className="text-sm text-gray-500">Commission:</span>
              <span className="text-sm font-medium text-green-600">
                {product.commission_rate}%
              </span>
            </div>
          )}
        </div>

        <div className="flex gap-2">
          <Link
            href={`/products/${product.id}`}
            className="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors flex items-center justify-center gap-2"
          >
            <FiEye className="w-4 h-4" />
            View Details
          </Link>
          <Link
            href={`/products/${product.id}/edit`}
            className="px-3 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
            title="Edit Product"
          >
            <FiEdit className="w-4 h-4" />
          </Link>
        </div>
      </div>
    </div>
  );

  const ProductRow = ({ product }: { product: Product }) => (
    <tr className="hover:bg-gray-50 transition-colors">
      <td className="px-6 py-4">
        <div className="flex items-center gap-3">
          <div className="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
            {product.image_url ? (
              <img src={product.image_url} alt={product.name} className="w-12 h-12 object-cover rounded-lg" />
            ) : (
              <FiPackage className="w-5 h-5 text-gray-400" />
            )}
          </div>
          <div>
            <div className="font-medium text-gray-900">{product.name}</div>
            <div className="text-sm text-gray-500">{product.manufacturer}</div>
          </div>
        </div>
      </td>
      <td className="px-6 py-4 text-sm text-gray-900">Q{product.q_code}</td>
      <td className="px-6 py-4 text-sm">
        <span className={`px-2 py-1 text-xs font-semibold rounded-full ${
          product.category === 'SkinSubstitute'
            ? 'bg-blue-100 text-blue-800'
            : product.category === 'Biologic'
            ? 'bg-green-100 text-green-800'
            : 'bg-gray-100 text-gray-800'
        }`}>
          {product.category}
        </span>
      </td>
      <td className="px-6 py-4 text-sm font-medium text-gray-900">
        {formatPrice(product.price_per_sq_cm)}
      </td>
      {/* Show MSC Price column only if role allows */}
      {roleRestrictions.can_see_msc_pricing && (
        <td className="px-6 py-4 text-sm font-semibold text-blue-600">
          {product.msc_price ? formatPrice(product.msc_price) : 'N/A'}
        </td>
      )}
      <td className="px-6 py-4 text-sm text-gray-500">
        {product.available_sizes?.length || 0} sizes
      </td>
      {/* Show commission column only if commission access is allowed (RBAC compliant) */}
      {roleRestrictions.commission_access_level !== 'none' && (
        <td className="px-6 py-4 text-sm text-green-600">
          {product.commission_rate ? `${product.commission_rate}%` : 'N/A'}
        </td>
      )}
      <td className="px-6 py-4">
        <div className="flex gap-2">
          <Link
            href={`/products/${product.id}`}
            className="text-blue-600 hover:text-blue-800 transition-colors"
            title="View Product"
          >
            <FiEye className="w-4 h-4" />
          </Link>
          <Link
            href={`/products/${product.id}/edit`}
            className="text-gray-600 hover:text-gray-800 transition-colors"
            title="Edit Product"
          >
            <FiEdit className="w-4 h-4" />
          </Link>
        </div>
      </td>
    </tr>
  );

  return (
    <MainLayout title="Product Catalog">
      <Head title="Product Catalog" />

      <div className="p-6">
        {/* Header */}
        <div className="mb-8">
          <div className="flex items-center justify-between mb-4">
            <div>
              <h1 className="text-3xl font-bold text-gray-900">Product Catalog</h1>
              <p className="text-gray-600 mt-1">
                Manage and browse MSC wound care products
              </p>
            </div>
            <Link
              href="/products/create"
              className="bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors flex items-center gap-2"
            >
              <FiPlus className="w-4 h-4" />
              Add Product
            </Link>
          </div>

          {/* Stats */}
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div className="bg-white p-4 rounded-lg border border-gray-200">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-500">Total Products</p>
                  <p className="text-2xl font-bold text-gray-900">{products.total}</p>
                </div>
                <FiPackage className="w-8 h-8 text-blue-600" />
              </div>
            </div>
            <div className="bg-white p-4 rounded-lg border border-gray-200">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-500">Categories</p>
                  <p className="text-2xl font-bold text-gray-900">{categories.length}</p>
                </div>
                <FiBarChart className="w-8 h-8 text-green-600" />
              </div>
            </div>
            <div className="bg-white p-4 rounded-lg border border-gray-200">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-500">Manufacturers</p>
                  <p className="text-2xl font-bold text-gray-900">{manufacturers.length}</p>
                </div>
                <FiGrid className="w-8 h-8 text-purple-600" />
              </div>
            </div>
            {/* Show pricing stats only if role allows */}
            {roleRestrictions.can_see_msc_pricing ? (
              <div className="bg-white p-4 rounded-lg border border-gray-200">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-gray-500">Avg MSC Price</p>
                    <p className="text-2xl font-bold text-gray-900">
                      {formatPrice(
                        products.data.reduce((sum, p) => sum + (p.msc_price || 0), 0) / products.data.length || 0
                      )}
                    </p>
                  </div>
                  <FiDollarSign className="w-8 h-8 text-orange-600" />
                </div>
              </div>
            ) : (
              <div className="bg-white p-4 rounded-lg border border-gray-200">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-gray-500">Avg National ASP</p>
                    <p className="text-2xl font-bold text-gray-900">
                      {formatPrice(
                        products.data.reduce((sum, p) => sum + p.price_per_sq_cm, 0) / products.data.length || 0
                      )}
                    </p>
                  </div>
                  <FiDollarSign className="w-8 h-8 text-orange-600" />
                </div>
              </div>
            )}
          </div>

          {/* Search and Filters */}
          <div className="bg-white rounded-lg border border-gray-200 p-4">
            <div className="flex flex-col lg:flex-row gap-4">
              {/* Search */}
              <form onSubmit={handleSearch} className="flex-1">
                <div className="relative">
                  <FiSearch className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
                  <input
                    type="text"
                    name="search"
                    placeholder="Search products, Q-codes, manufacturers..."
                    defaultValue={localFilters.search || ''}
                    className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>
              </form>

              {/* Filter Toggle */}
              <button
                onClick={() => setShowFilters(!showFilters)}
                className="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
              >
                <FiFilter className="w-4 h-4" />
                Filters
              </button>

              {/* View Mode Toggle */}
              <div className="flex border border-gray-300 rounded-lg overflow-hidden">
                <button
                  onClick={() => setViewMode('grid')}
                  className={`px-3 py-2 ${viewMode === 'grid' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'} transition-colors`}
                >
                  <FiGrid className="w-4 h-4" />
                </button>
                <button
                  onClick={() => setViewMode('list')}
                  className={`px-3 py-2 ${viewMode === 'list' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'} transition-colors`}
                >
                  <FiList className="w-4 h-4" />
                </button>
              </div>
            </div>

            {/* Expanded Filters */}
            {showFilters && (
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4 pt-4 border-t border-gray-200">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Category</label>
                  <select
                    value={localFilters.category || ''}
                    onChange={(e) => handleFilterChange('category', e.target.value)}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  >
                    <option value="">All Categories</option>
                    {categories.map((category) => (
                      <option key={category} value={category}>
                        {category}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Manufacturer</label>
                  <select
                    value={localFilters.manufacturer || ''}
                    onChange={(e) => handleFilterChange('manufacturer', e.target.value)}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  >
                    <option value="">All Manufacturers</option>
                    {manufacturers.map((manufacturer) => (
                      <option key={manufacturer} value={manufacturer}>
                        {manufacturer}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="flex items-end">
                  <button
                    onClick={clearFilters}
                    className="w-full px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                  >
                    Clear Filters
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Products Display */}
        {viewMode === 'grid' ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            {products.data.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        ) : (
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th
                      className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                      onClick={() => handleSort('name')}
                    >
                      Product
                    </th>
                    <th
                      className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                      onClick={() => handleSort('q_code')}
                    >
                      Q-Code
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Category
                    </th>
                    <th
                      className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                      onClick={() => handleSort('price_per_sq_cm')}
                    >
                      National ASP
                    </th>
                    {/* Show MSC Price column only if role allows */}
                    {roleRestrictions.can_see_msc_pricing && (
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        MSC Price
                      </th>
                    )}
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Sizes
                    </th>
                    {/* Show commission column only if commission access is allowed (RBAC compliant) */}
                    {roleRestrictions.commission_access_level !== 'none' && (
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Commission
                      </th>
                    )}
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {products.data.map((product) => (
                    <ProductRow key={product.id} product={product} />
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* Pagination */}
        {products.data.length === 0 ? (
          <div className="text-center py-12">
            <FiPackage className="w-12 h-12 text-gray-400 mx-auto mb-4" />
            <h3 className="text-lg font-medium text-gray-900 mb-2">No products found</h3>
            <p className="text-gray-500">Try adjusting your search or filter criteria.</p>
          </div>
        ) : (
          <div className="mt-8 flex justify-center">
            <div className="flex gap-2">
              {products.links.map((link, index) => (
                <Link
                  key={index}
                  href={link.url || '#'}
                  className={`px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
                    link.active
                      ? 'bg-blue-600 text-white'
                      : link.url
                      ? 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'
                      : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                  }`}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                />
              ))}
            </div>
          </div>
        )}

        {/* Financial Restriction Notice for Office Managers */}
        {!roleRestrictions.can_see_msc_pricing && (
          <div className="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
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
      </div>
    </MainLayout>
  );
}
