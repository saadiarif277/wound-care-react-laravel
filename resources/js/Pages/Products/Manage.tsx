import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
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
  FiTag,
  FiEye
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
  const { theme } = useTheme();
  const t = themes[theme];
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

      <div className="space-y-6">
        {/* Header */}
        <div className="flex justify-between items-center">
          <div>
            <h1 className={cn("text-3xl font-bold", t.text.primary)}>
              Product Management
            </h1>
            <p className={cn("mt-1", t.text.secondary)}>
              Manage your product catalog and inventory
            </p>
          </div>
          {permissions.can_create && (
            <Link
              href={route('products.create')}
              className={cn(
                "px-6 py-3 rounded-xl font-semibold flex items-center gap-2 transition-all",
                t.button.primary.base,
                t.button.primary.hover
              )}
            >
              <FiPlus className="w-5 h-5" />
              Add Product
            </Link>
          )}
        </div>

        {/* Stats Cards */}
        <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
          <div className={cn("p-6 rounded-2xl", t.glass.card, t.shadows.glass)}>
            <div className="flex items-center gap-4">
              <div className="p-3 rounded-xl bg-blue-500/20">
                <FiPackage className="w-8 h-8 text-blue-400" />
              </div>
              <div>
                <p className={cn("text-sm font-medium", t.text.secondary)}>Total Products</p>
                <p className={cn("text-2xl font-bold", t.text.primary)}>{stats.total_products}</p>
              </div>
            </div>
          </div>

          <div className={cn("p-6 rounded-2xl", t.glass.card, t.shadows.glass)}>
            <div className="flex items-center gap-4">
              <div className="p-3 rounded-xl bg-emerald-500/20">
                <FiBarChart className="w-8 h-8 text-emerald-400" />
              </div>
              <div>
                <p className={cn("text-sm font-medium", t.text.secondary)}>Active</p>
                <p className={cn("text-2xl font-bold", t.text.primary)}>{stats.active_products}</p>
              </div>
            </div>
          </div>

          <div className={cn("p-6 rounded-2xl", t.glass.card, t.shadows.glass)}>
            <div className="flex items-center gap-4">
              <div className="p-3 rounded-xl bg-red-500/20">
                <FiTrash2 className="w-8 h-8 text-red-400" />
              </div>
              <div>
                <p className={cn("text-sm font-medium", t.text.secondary)}>Inactive</p>
                <p className={cn("text-2xl font-bold", t.text.primary)}>{stats.inactive_products}</p>
              </div>
            </div>
          </div>

          <div className={cn("p-6 rounded-2xl", t.glass.card, t.shadows.glass)}>
            <div className="flex items-center gap-4">
              <div className="p-3 rounded-xl bg-purple-500/20">
                <FiTag className="w-8 h-8 text-purple-400" />
              </div>
              <div>
                <p className={cn("text-sm font-medium", t.text.secondary)}>Categories</p>
                <p className={cn("text-2xl font-bold", t.text.primary)}>{stats.categories_count}</p>
              </div>
            </div>
          </div>

          <div className={cn("p-6 rounded-2xl", t.glass.card, t.shadows.glass)}>
            <div className="flex items-center gap-4">
              <div className="p-3 rounded-xl bg-indigo-500/20">
                <FiUsers className="w-8 h-8 text-indigo-400" />
              </div>
              <div>
                <p className={cn("text-sm font-medium", t.text.secondary)}>Manufacturers</p>
                <p className={cn("text-2xl font-bold", t.text.primary)}>{stats.manufacturers_count}</p>
              </div>
            </div>
          </div>
        </div>

        {/* Search and Filters */}
        <div className={cn("p-6 rounded-2xl", t.glass.card, t.shadows.glass)}>
          <div className="flex flex-col md:flex-row gap-4">
            <form onSubmit={handleSearch} className="flex-1">
              <div className="relative">
                <FiSearch className={cn("absolute left-4 top-1/2 transform -translate-y-1/2 w-5 h-5", t.text.tertiary)} />
                <input
                  type="text"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  placeholder="Search products..."
                  className={cn("w-full pl-12 pr-4 py-3", t.input.base, t.input.focus)}
                />
              </div>
            </form>

            <div className="flex gap-3">
              <select
                value={filters.status || ''}
                onChange={(e) => handleFilter('status', e.target.value)}
                className={cn("px-4 py-3 min-w-[140px]", t.input.select || t.input.base, t.input.focus)}
              >
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>

              <select
                value={filters.category || ''}
                onChange={(e) => handleFilter('category', e.target.value)}
                className={cn("px-4 py-3 min-w-[160px]", t.input.select || t.input.base, t.input.focus)}
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
                className={cn("px-4 py-3 min-w-[160px]", t.input.select || t.input.base, t.input.focus)}
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
        <div className={cn("rounded-2xl overflow-hidden", t.table.container, t.shadows.glass)}>
          <div className="overflow-x-auto">
            <table className="min-w-full">
              <thead className={cn(t.table.header)}>
                <tr>
                  <th
                    className={cn(
                      "px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider cursor-pointer transition-colors",
                      t.table.headerText,
                      t.glass.hover
                    )}
                    onClick={() => handleSort('name')}
                  >
                    Product
                  </th>
                  <th
                    className={cn(
                      "px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider cursor-pointer transition-colors",
                      t.table.headerText,
                      t.glass.hover
                    )}
                    onClick={() => handleSort('category')}
                  >
                    Category
                  </th>
                  <th
                    className={cn(
                      "px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider cursor-pointer transition-colors",
                      t.table.headerText,
                      t.glass.hover
                    )}
                    onClick={() => handleSort('manufacturer')}
                  >
                    Manufacturer
                  </th>
                  <th
                    className={cn(
                      "px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider cursor-pointer transition-colors",
                      t.table.headerText,
                      t.glass.hover
                    )}
                    onClick={() => handleSort('price')}
                  >
                    Pricing
                  </th>
                  <th className={cn("px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider", t.table.headerText)}>
                    Status
                  </th>
                  <th
                    className={cn(
                      "px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider cursor-pointer transition-colors",
                      t.table.headerText,
                      t.glass.hover
                    )}
                    onClick={() => handleSort('created_at')}
                  >
                    Created
                  </th>
                  <th className={cn("px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider", t.table.headerText)}>
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/10">
                {products?.data?.length > 0 ? products.data.map((product, index) => (
                  <tr key={product.id} className={cn(
                    t.table.rowHover,
                    index % 2 === 0 ? t.table.evenRow : ''
                  )}>
                    <td className="px-6 py-4">
                      <div className="flex items-center">
                        {product.image_url && (
                          <img
                            src={product.image_url}
                            alt={product.name}
                            className="w-12 h-12 rounded-xl object-cover mr-4 border border-white/10"
                          />
                        )}
                        <div>
                          <div className={cn("text-sm font-semibold", t.text.primary)}>
                            {product.name}
                          </div>
                          <div className={cn("text-xs mt-1", t.text.secondary)}>
                            SKU: {product.sku} | Q-Code: {product.q_code}
                          </div>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <span className={cn("text-sm", t.text.primary)}>{product.category}</span>
                    </td>
                    <td className="px-6 py-4">
                      <span className={cn("text-sm", t.text.primary)}>{product.manufacturer}</span>
                    </td>
                    <td className="px-6 py-4">
                      <div>
                        <div className={cn("text-sm font-medium", t.text.primary)}>
                          ASP: ${product.national_asp ? Number(product.national_asp).toFixed(2) : 'N/A'}
                        </div>
                        <div className={cn("text-xs mt-1", t.text.secondary)}>
                          Per cm²: ${product.price_per_sq_cm ? Number(product.price_per_sq_cm).toFixed(4) : 'N/A'}
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      {product.deleted_at ? (
                        <span className={cn("px-3 py-1 rounded-full text-xs font-medium", t.status.error)}>
                          Inactive
                        </span>
                      ) : (
                        <span className={cn("px-3 py-1 rounded-full text-xs font-medium", t.status.success)}>
                          Active
                        </span>
                      )}
                    </td>
                    <td className="px-6 py-4">
                      <span className={cn("text-sm", t.text.secondary)}>{product.created_at}</span>
                    </td>
                    <td className="px-6 py-4">
                      <div className="flex items-center justify-end gap-2">
                        <button
                          onClick={() => router.visit(route('products.show', product.id))}
                          className={cn(
                            "p-2 rounded-lg transition-all",
                            t.table.actionButton
                          )}
                          title="View product"
                        >
                          <FiEye className="w-4 h-4" />
                        </button>

                        {permissions.can_edit && (
                          <button
                            onClick={() => router.visit(route('products.edit', product.id))}
                            className={cn(
                              "p-2 rounded-lg transition-all",
                              t.table.actionButton
                            )}
                            title="Edit product"
                          >
                            <FiEdit className="w-4 h-4" />
                          </button>
                        )}

                        {product.deleted_at ? (
                          permissions.can_restore && (
                            <button
                              onClick={() => handleRestore(product.id)}
                              className={cn(
                                "p-2 rounded-lg transition-all text-emerald-400 hover:text-emerald-300",
                                t.table.actionButton
                              )}
                              title="Restore product"
                            >
                              <FiRotateCcw className="w-4 h-4" />
                            </button>
                          )
                        ) : (
                          permissions.can_delete && (
                            <button
                              onClick={() => handleDelete(product.id)}
                              className={cn(
                                "p-2 rounded-lg transition-all text-red-400 hover:text-red-300",
                                t.table.actionButton
                              )}
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
                    <td colSpan={7} className="px-6 py-16 text-center">
                      <div className={cn("flex flex-col items-center", t.text.secondary)}>
                        <div className="p-4 rounded-2xl bg-white/5 mb-4">
                          <FiPackage className="h-12 w-12 mx-auto text-white/30" />
                        </div>
                        <p className={cn("text-lg font-medium mb-2", t.text.primary)}>No products found</p>
                        <p className={cn("text-sm", t.text.secondary)}>Try adjusting your filters or add a new product</p>
                      </div>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {products.meta?.links && (
            <div className={cn("px-6 py-4 border-t border-white/10", t.glass.frost)}>
              <div className="flex items-center justify-between">
                <div className="flex justify-between flex-1 sm:hidden">
                  {products.meta.links.map((link: any, index: number) => {
                    if (link.label.includes('Previous') || link.label.includes('Next')) {
                      return (
                        <Link
                          key={index}
                          href={link.url || '#'}
                          className={cn(
                            "relative inline-flex items-center px-4 py-2 rounded-xl text-sm font-medium transition-all",
                            link.url
                              ? cn(t.button.secondary.base, t.button.secondary.hover)
                              : cn(t.input.disabled, "cursor-not-allowed")
                          )}
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
                    <p className={cn("text-sm", t.text.secondary)}>
                      Showing{' '}
                      <span className={cn("font-medium", t.text.primary)}>{products.meta?.from || 0}</span>
                      {' '}to{' '}
                      <span className={cn("font-medium", t.text.primary)}>{products.meta?.to || 0}</span>
                      {' '}of{' '}
                      <span className={cn("font-medium", t.text.primary)}>{products.meta?.total || 0}</span>
                      {' '}results
                    </p>
                  </div>
                  <div>
                    <nav className="relative z-0 inline-flex rounded-xl shadow-sm -space-x-px">
                      {products.meta?.links?.map((link: any, index: number) => (
                        <Link
                          key={index}
                          href={link.url || '#'}
                          className={cn(
                            "relative inline-flex items-center px-3 py-2 text-sm font-medium transition-all",
                            link.active
                              ? cn("z-10", t.button.primary.base)
                              : link.url
                              ? cn(t.button.secondary.base, t.button.secondary.hover)
                              : cn(t.input.disabled, "cursor-not-allowed"),
                            index === 0 ? 'rounded-l-xl' : '',
                            index === products.meta?.links?.length - 1 ? 'rounded-r-xl' : ''
                          )}
                        >
                          <span dangerouslySetInnerHTML={{ __html: link.label }} />
                        </Link>
                      ))}
                    </nav>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </MainLayout>
  );
}
