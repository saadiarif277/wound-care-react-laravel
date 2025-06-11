import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import {
  FiPlus,
  FiEdit,
  FiTrash2,
  FiRotateCcw,
  FiSearch,
  FiFilter,
  FiPackage,
  FiBarChart,
  FiUsers,
  FiTag
} from 'react-icons/fi';

interface Product {
  id: number;
  sku: string;
  name: string;
  manufacturer: string;
  category: string;
  q_code: string;
  national_asp: number;
  price_per_sq_cm: number;
  commission_rate: number;
  is_active: boolean;
  deleted_at: string | null;
  created_at: string;
  updated_at: string;
  image_url: string;
  available_sizes: number[];
}

interface Props {
  products: {
    data: Product[];
    links: any;
    meta: any;
  };
  filters: {
    search?: string;
    category?: string;
    manufacturer?: string;
    status?: string;
    sort?: string;
    direction?: string;
  };
  categories: string[];
  manufacturers: string[];
  stats: {
    total_products: number;
    active_products: number;
    inactive_products: number;
    categories_count: number;
    manufacturers_count: number;
  };
  permissions: {
    can_create: boolean;
    can_edit: boolean;
    can_delete: boolean;
    can_restore: boolean;
  };
}

export default function Manage({
  products,
  filters,
  categories,
  manufacturers,
  stats,
  permissions
}: Props) {
  const [searchTerm, setSearchTerm] = useState(filters.search || '');
  const [showFilters, setShowFilters] = useState(false);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    router.get(route('products.manage'), {
      ...filters,
      search: searchTerm,
      page: 1
    });
  };

  const handleFilter = (key: string, value: string) => {
    router.get(route('products.manage'), {
      ...filters,
      [key]: value,
      page: 1
    });
  };

  const handleSort = (column: string) => {
    const direction = filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';
    router.get(route('products.manage'), {
      ...filters,
      sort: column,
      direction,
      page: 1
    });
  };

  const handleDelete = (productId: number) => {
    if (confirm('Are you sure you want to delete this product?')) {
      router.delete(route('products.destroy', productId));
    }
  };

  const handleRestore = (productId: number) => {
    if (confirm('Are you sure you want to restore this product?')) {
      router.put(route('products.restore', productId));
    }
  };

  return (
    <MainLayout>
      <Head title="Product Management" />

      <div className="py-6">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header */}
          <div className="flex justify-between items-center mb-6">
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Product Management</h1>
              <p className="text-gray-600">Manage your product catalog</p>
            </div>
            {permissions.can_create && (
              <Link
                href={route('products.create')}
                className="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2"
              >
                <FiPlus className="w-4 h-4" />
                Add Product
              </Link>
            )}
          </div>

          {/* Stats Cards */}
          <div className="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <div className="bg-white p-4 rounded-lg shadow">
              <div className="flex items-center gap-3">
                <FiPackage className="w-8 h-8 text-blue-600" />
                <div>
                  <p className="text-sm text-gray-600">Total Products</p>
                  <p className="text-xl font-bold">{stats.total_products}</p>
                </div>
              </div>
            </div>
            <div className="bg-white p-4 rounded-lg shadow">
              <div className="flex items-center gap-3">
                <FiBarChart className="w-8 h-8 text-green-600" />
                <div>
                  <p className="text-sm text-gray-600">Active</p>
                  <p className="text-xl font-bold">{stats.active_products}</p>
                </div>
              </div>
            </div>
            <div className="bg-white p-4 rounded-lg shadow">
              <div className="flex items-center gap-3">
                <FiTrash2 className="w-8 h-8 text-red-600" />
                <div>
                  <p className="text-sm text-gray-600">Inactive</p>
                  <p className="text-xl font-bold">{stats.inactive_products}</p>
                </div>
              </div>
            </div>
            <div className="bg-white p-4 rounded-lg shadow">
              <div className="flex items-center gap-3">
                <FiTag className="w-8 h-8 text-purple-600" />
                <div>
                  <p className="text-sm text-gray-600">Categories</p>
                  <p className="text-xl font-bold">{stats.categories_count}</p>
                </div>
              </div>
            </div>
            <div className="bg-white p-4 rounded-lg shadow">
              <div className="flex items-center gap-3">
                <FiUsers className="w-8 h-8 text-indigo-600" />
                <div>
                  <p className="text-sm text-gray-600">Manufacturers</p>
                  <p className="text-xl font-bold">{stats.manufacturers_count}</p>
                </div>
              </div>
            </div>
          </div>

          {/* Search and Filters */}
          <div className="bg-white p-4 rounded-lg shadow mb-6">
            <div className="flex flex-col md:flex-row gap-4">
              <form onSubmit={handleSearch} className="flex-1">
                <div className="relative">
                  <FiSearch className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
                  <input
                    type="text"
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    placeholder="Search products..."
                    className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                  />
                </div>
              </form>

              <div className="flex gap-2">
                <select
                  value={filters.status || ''}
                  onChange={(e) => handleFilter('status', e.target.value)}
                  className="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                >
                  <option value="">All Status</option>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>

                <select
                  value={filters.category || ''}
                  onChange={(e) => handleFilter('category', e.target.value)}
                  className="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                >
                  <option value="">All Categories</option>
                  {categories.map((category) => (
                    <option key={category} value={category}>
                      {category}
                    </option>
                  ))}
                </select>

                <select
                  value={filters.manufacturer || ''}
                  onChange={(e) => handleFilter('manufacturer', e.target.value)}
                  className="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                >
                  <option value="">All Manufacturers</option>
                  {manufacturers.map((manufacturer) => (
                    <option key={manufacturer} value={manufacturer}>
                      {manufacturer}
                    </option>
                  ))}
                </select>
              </div>
            </div>
          </div>

          {/* Products Table */}
          <div className="bg-white rounded-lg shadow overflow-hidden">
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
                      onClick={() => handleSort('category')}
                    >
                      Category
                    </th>
                    <th
                      className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                      onClick={() => handleSort('manufacturer')}
                    >
                      Manufacturer
                    </th>
                    <th
                      className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                      onClick={() => handleSort('price')}
                    >
                      Pricing
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                    <th
                      className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                      onClick={() => handleSort('created_at')}
                    >
                      Created
                    </th>
                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {products?.data?.length > 0 ? products.data.map((product) => (
                    <tr key={product.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center">
                          {product.image_url && (
                            <img
                              src={product.image_url}
                              alt={product.name}
                              className="w-10 h-10 rounded-lg object-cover mr-3"
                            />
                          )}
                          <div>
                            <div className="text-sm font-medium text-gray-900">
                              {product.name}
                            </div>
                            <div className="text-sm text-gray-500">
                              SKU: {product.sku} | Q-Code: {product.q_code}
                            </div>
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {product.category}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {product.manufacturer}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <div>
                          <div>ASP: ${product.national_asp ? Number(product.national_asp).toFixed(2) : 'N/A'}</div>
                          <div className="text-xs text-gray-500">
                            Per cm²: ${product.price_per_sq_cm ? Number(product.price_per_sq_cm).toFixed(4) : 'N/A'}
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        {product.deleted_at ? (
                          <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                            Inactive
                          </span>
                        ) : (
                          <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            Active
                          </span>
                        )}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {product.created_at}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div className="flex items-center justify-end gap-2">
                          {permissions.can_edit && (
                            <Link
                              href={route('products.edit', product.id)}
                              className="text-indigo-600 hover:text-indigo-900"
                            >
                              <FiEdit className="w-4 h-4" />
                            </Link>
                          )}

                          {product.deleted_at ? (
                            permissions.can_restore && (
                              <button
                                onClick={() => handleRestore(product.id)}
                                className="text-green-600 hover:text-green-900"
                                title="Restore product"
                              >
                                <FiRotateCcw className="w-4 h-4" />
                              </button>
                            )
                          ) : (
                            permissions.can_delete && (
                              <button
                                onClick={() => handleDelete(product.id)}
                                className="text-red-600 hover:text-red-900"
                                title="Delete product"
                              >
                                <FiTrash2 className="w-4 h-4" />
                              </button>
                            )
                          )}
                        </div>
                      </td>
                    </tr>
                  )) : (
                    <tr>
                      <td colSpan={8} className="px-6 py-12 text-center">
                        <div className="text-gray-500">
                          <FiPackage className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                          <p className="text-lg font-medium">No products found</p>
                          <p className="text-sm mt-1">Try adjusting your filters or add a new product</p>
                        </div>
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>

            {/* Pagination */}
            {products.meta?.links && (
              <div className="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                <div className="flex items-center justify-between">
                  <div className="flex justify-between flex-1 sm:hidden">
                    {products.meta.links.map((link: any, index: number) => {
                      if (link.label.includes('Previous') || link.label.includes('Next')) {
                        return (
                          <Link
                            key={index}
                            href={link.url || '#'}
                            className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium rounded-md ${
                              link.url
                                ? 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                                : 'border-gray-300 bg-gray-100 text-gray-400 cursor-not-allowed'
                            }`}
                          >
                            {link.label}
                          </Link>
                        );
                      }
                      return null;
                    })}
                  </div>
                  <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                      <p className="text-sm text-gray-700">
                        Showing{' '}
                        <span className="font-medium">{products.meta?.from || 0}</span>
                        {' '}to{' '}
                        <span className="font-medium">{products.meta?.to || 0}</span>
                        {' '}of{' '}
                        <span className="font-medium">{products.meta?.total || 0}</span>
                        {' '}results
                      </p>
                    </div>
                    <div>
                      <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                        {products.meta?.links?.map((link: any, index: number) => (
                          <Link
                            key={index}
                            href={link.url || '#'}
                            className={`relative inline-flex items-center px-2 py-2 border text-sm font-medium ${
                              link.active
                                ? 'z-10 bg-red-50 border-red-500 text-red-600'
                                : link.url
                                ? 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                                : 'bg-gray-100 border-gray-300 text-gray-400 cursor-not-allowed'
                            } ${
                              index === 0 ? 'rounded-l-md' : ''
                            } ${
                              index === products.meta?.links?.length - 1 ? 'rounded-r-md' : ''
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                          />
                        ))}
                      </nav>
                    </div>
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </MainLayout>
  );
}
