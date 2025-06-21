import React, { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import {
  FiSearch,
  FiFilter,
  FiPlus,
  FiEdit3,
  FiTrash2,
  FiEye,
  FiDownload,
  FiUpload,
  FiPackage,
  FiTag,
  FiDollarSign,
  FiFileText,
  FiImage,
  FiGrid,
  FiList,
  FiStar,
  FiTrendingUp,
  FiActivity,
  FiBarChart,
  FiMoreVertical,
  FiExternalLink,
  FiCopy,
  FiShare2
} from 'react-icons/fi';

interface Product {
  id: number;
  sku: string;
  name: string;
  description: string;
  manufacturer: string;
  category: string;
  price_per_sq_cm: number;
  national_asp?: number;
  q_code: string;
  available_sizes: number[];
  size_options?: string[];
  commission_rate: number;
  is_active: boolean;
  image_url?: string;
  document_urls?: string[];
  created_at: string;
  updated_at: string;
  mue_limit?: number;
  graph_type?: string;
}

interface Props {
  products: Product[];
  categories: string[];
  manufacturers: string[];
  filters: {
    search?: string;
    category?: string;
    manufacturer?: string;
    active?: boolean;
  };
}

export default function ProductsIndex({ products, categories, manufacturers, filters }: Props) {
  const { theme } = useTheme();
  const t = themes[theme];

  const [searchTerm, setSearchTerm] = useState(filters.search || '');
  const [selectedCategory, setSelectedCategory] = useState(filters.category || '');
  const [selectedManufacturer, setSelectedManufacturer] = useState(filters.manufacturer || '');
  const [activeFilter, setActiveFilter] = useState(filters.active ?? true);
  const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');
  const [showFilters, setShowFilters] = useState(false);
  const [selectedProducts, setSelectedProducts] = useState<number[]>([]);
  const [sortBy, setSortBy] = useState<'name' | 'price' | 'commission' | 'updated'>('name');
  const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('asc');

  // Filter and sort products
  const filteredProducts = products
    .filter(product => {
      const matchesSearch = product.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                          product.sku.toLowerCase().includes(searchTerm.toLowerCase()) ||
                          product.q_code.toLowerCase().includes(searchTerm.toLowerCase()) ||
                          product.manufacturer.toLowerCase().includes(searchTerm.toLowerCase());
      const matchesCategory = !selectedCategory || product.category === selectedCategory;
      const matchesManufacturer = !selectedManufacturer || product.manufacturer === selectedManufacturer;
      const matchesActive = activeFilter === undefined || product.is_active === activeFilter;

      return matchesSearch && matchesCategory && matchesManufacturer && matchesActive;
    })
    .sort((a, b) => {
      let aValue, bValue;
      switch (sortBy) {
        case 'price':
          aValue = a.price_per_sq_cm;
          bValue = b.price_per_sq_cm;
          break;
        case 'commission':
          aValue = a.commission_rate;
          bValue = b.commission_rate;
          break;
        case 'updated':
          aValue = new Date(a.updated_at).getTime();
          bValue = new Date(b.updated_at).getTime();
          break;
        default:
          aValue = a.name.toLowerCase();
          bValue = b.name.toLowerCase();
      }

      if (sortOrder === 'asc') {
        return aValue > bValue ? 1 : -1;
      } else {
        return aValue < bValue ? 1 : -1;
      }
    });

  const handleSearch = () => {
    router.get('/products', {
      search: searchTerm,
      category: selectedCategory,
      manufacturer: selectedManufacturer,
      active: activeFilter
    }, { preserveState: true });
  };

  const handleBulkAction = (action: string) => {
    if (selectedProducts.length === 0) return;

    switch (action) {
      case 'activate':
        router.post('/products/bulk-activate', { ids: selectedProducts });
        break;
      case 'deactivate':
        router.post('/products/bulk-deactivate', { ids: selectedProducts });
        break;
      case 'delete':
        if (confirm(`Are you sure you want to delete ${selectedProducts.length} products?`)) {
          router.delete('/products/bulk-delete', { data: { ids: selectedProducts } });
        }
        break;
    }
    setSelectedProducts([]);
  };

  const toggleProductSelection = (productId: number) => {
    setSelectedProducts(prev =>
      prev.includes(productId)
        ? prev.filter(id => id !== productId)
        : [...prev, productId]
    );
  };

  const selectAllProducts = () => {
    if (selectedProducts.length === filteredProducts.length) {
      setSelectedProducts([]);
    } else {
      setSelectedProducts(filteredProducts.map(p => p.id));
    }
  };

  return (
    <MainLayout>
      <Head title="Product Catalog" />

      <div className="space-y-6">
        {/* Header */}
        <div className={cn("p-6 rounded-2xl", t.glass.card)}>
          <div className="flex items-center justify-between mb-6">
            <div>
              <h1 className={cn("text-3xl font-bold flex items-center gap-3", t.text.primary)}>
                <FiPackage className="w-8 h-8 text-blue-500" />
                Product Catalog
              </h1>
              <p className={cn("text-sm mt-2", t.text.secondary)}>
                Manage your wound care product inventory and documentation
              </p>
            </div>
            <div className="flex items-center gap-3">
              <Link
                href="/products/create"
                className={cn(
                  "flex items-center gap-2 px-4 py-2 rounded-xl font-medium transition-all",
                  t.button.primary.base,
                  t.button.primary.hover
                )}
              >
                <FiPlus className="w-4 h-4" />
                Add Product
              </Link>
            </div>
          </div>

          {/* Stats Cards */}
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div className={cn("p-4 rounded-xl", t.glass.frost)}>
              <div className="flex items-center justify-between">
                <div>
                  <p className={cn("text-xs font-medium", t.text.secondary)}>Total Products</p>
                  <p className={cn("text-2xl font-bold", t.text.primary)}>{products.length}</p>
                </div>
                <FiPackage className={cn("w-8 h-8", t.text.secondary)} />
              </div>
            </div>
            <div className={cn("p-4 rounded-xl", t.glass.frost)}>
              <div className="flex items-center justify-between">
                <div>
                  <p className={cn("text-xs font-medium", t.text.secondary)}>Active Products</p>
                  <p className={cn("text-2xl font-bold text-green-500")}>
                    {products.filter(p => p.is_active).length}
                  </p>
                </div>
                <FiActivity className="w-8 h-8 text-green-500" />
              </div>
            </div>
            <div className={cn("p-4 rounded-xl", t.glass.frost)}>
              <div className="flex items-center justify-between">
                <div>
                  <p className={cn("text-xs font-medium", t.text.secondary)}>Categories</p>
                  <p className={cn("text-2xl font-bold", t.text.primary)}>{categories.length}</p>
                </div>
                <FiTag className={cn("w-8 h-8", t.text.secondary)} />
              </div>
            </div>
            <div className={cn("p-4 rounded-xl", t.glass.frost)}>
              <div className="flex items-center justify-between">
                <div>
                  <p className={cn("text-xs font-medium", t.text.secondary)}>Manufacturers</p>
                  <p className={cn("text-2xl font-bold", t.text.primary)}>{manufacturers.length}</p>
                </div>
                <FiBarChart className={cn("w-8 h-8", t.text.secondary)} />
              </div>
            </div>
          </div>

          {/* Search and Filters */}
          <div className="space-y-4">
            <div className="flex flex-col lg:flex-row gap-4">
              {/* Search */}
              <div className="flex-1 relative">
                <FiSearch className={cn("absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5", t.text.secondary)} />
                <input
                  type="text"
                  placeholder="Search products, SKU, Q-code, or manufacturer..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  onKeyPress={(e) => e.key === 'Enter' && handleSearch()}
                  className={cn(
                    "w-full pl-10 pr-4 py-3 rounded-xl",
                    t.input.base,
                    t.input.focus
                  )}
                />
              </div>

              {/* View Toggle */}
              <div className={cn("flex rounded-xl p-1", t.glass.frost)}>
                <button
                  onClick={() => setViewMode('grid')}
                  className={cn(
                    "flex items-center gap-2 px-3 py-2 rounded-lg transition-all",
                    viewMode === 'grid'
                      ? cn(t.button.primary.base, "text-white")
                      : cn(t.text.secondary, "hover:bg-white/10")
                  )}
                >
                  <FiGrid className="w-4 h-4" />
                  Grid
                </button>
                <button
                  onClick={() => setViewMode('list')}
                  className={cn(
                    "flex items-center gap-2 px-3 py-2 rounded-lg transition-all",
                    viewMode === 'list'
                      ? cn(t.button.primary.base, "text-white")
                      : cn(t.text.secondary, "hover:bg-white/10")
                  )}
                >
                  <FiList className="w-4 h-4" />
                  List
                </button>
              </div>

              {/* Filter Toggle */}
              <button
                onClick={() => setShowFilters(!showFilters)}
                className={cn(
                  "flex items-center gap-2 px-4 py-3 rounded-xl transition-all",
                  showFilters
                    ? cn(t.button.primary.base, t.button.primary.hover)
                    : cn(t.button.secondary.base, t.button.secondary.hover)
                )}
              >
                <FiFilter className="w-4 h-4" />
                Filters
              </button>
            </div>

            {/* Advanced Filters */}
            {showFilters && (
              <div className={cn("p-4 rounded-xl", t.glass.frost)}>
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                  <div>
                    <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                      Category
                    </label>
                    <select
                      value={selectedCategory}
                      onChange={(e) => setSelectedCategory(e.target.value)}
                      className={cn("w-full", t.input.base, t.input.focus)}
                    >
                      <option value="">All Categories</option>
                      {categories.map(category => (
                        <option key={category} value={category}>{category}</option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                      Manufacturer
                    </label>
                    <select
                      value={selectedManufacturer}
                      onChange={(e) => setSelectedManufacturer(e.target.value)}
                      className={cn("w-full", t.input.base, t.input.focus)}
                    >
                      <option value="">All Manufacturers</option>
                      {manufacturers.map(manufacturer => (
                        <option key={manufacturer} value={manufacturer}>{manufacturer}</option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                      Status
                    </label>
                    <select
                      value={activeFilter === undefined ? '' : activeFilter.toString()}
                      onChange={(e) => setActiveFilter(e.target.value === '' ? undefined : e.target.value === 'true')}
                      className={cn("w-full", t.input.base, t.input.focus)}
                    >
                      <option value="">All Status</option>
                      <option value="true">Active</option>
                      <option value="false">Inactive</option>
                    </select>
                  </div>
                  <div>
                    <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                      Sort By
                    </label>
                    <div className="flex gap-2">
                      <select
                        value={sortBy}
                        onChange={(e) => setSortBy(e.target.value as any)}
                        className={cn("flex-1", t.input.base, t.input.focus)}
                      >
                        <option value="name">Name</option>
                        <option value="price">Price</option>
                        <option value="commission">Commission</option>
                        <option value="updated">Updated</option>
                      </select>
                      <button
                        onClick={() => setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc')}
                        className={cn(
                          "px-3 py-2 rounded-xl",
                          t.button.secondary.base,
                          t.button.secondary.hover
                        )}
                      >
                        {sortOrder === 'asc' ? '↑' : '↓'}
                      </button>
                    </div>
                  </div>
                </div>
                <div className="flex justify-end mt-4">
                  <button
                    onClick={handleSearch}
                    className={cn(
                      "px-6 py-2 rounded-xl font-medium",
                      t.button.primary.base,
                      t.button.primary.hover
                    )}
                  >
                    Apply Filters
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Bulk Actions */}
        {selectedProducts.length > 0 && (
          <div className={cn("p-4 rounded-xl flex items-center justify-between", t.status.info)}>
            <span className="font-medium">
              {selectedProducts.length} product{selectedProducts.length !== 1 ? 's' : ''} selected
            </span>
            <div className="flex gap-2">
              <button
                onClick={() => handleBulkAction('activate')}
                className={cn("px-3 py-1 rounded-lg text-sm", t.button.approve.base, t.button.approve.hover)}
              >
                Activate
              </button>
              <button
                onClick={() => handleBulkAction('deactivate')}
                className={cn("px-3 py-1 rounded-lg text-sm", t.button.warning.base, t.button.warning.hover)}
              >
                Deactivate
              </button>
              <button
                onClick={() => handleBulkAction('delete')}
                className={cn("px-3 py-1 rounded-lg text-sm", t.button.danger.base, t.button.danger.hover)}
              >
                Delete
              </button>
            </div>
          </div>
        )}

        {/* Products Grid/List */}
        <div className={cn("p-6 rounded-2xl", t.glass.card)}>
          {filteredProducts.length === 0 ? (
            <div className="text-center py-12">
              <FiPackage className={cn("w-16 h-16 mx-auto mb-4", t.text.muted)} />
              <h3 className={cn("text-lg font-medium mb-2", t.text.primary)}>No products found</h3>
              <p className={cn("text-sm", t.text.secondary)}>
                Try adjusting your search criteria or add a new product.
              </p>
            </div>
          ) : (
            <>
              {/* Select All */}
              <div className="flex items-center justify-between mb-6">
                <div className="flex items-center gap-3">
                  <input
                    type="checkbox"
                    checked={selectedProducts.length === filteredProducts.length}
                    onChange={selectAllProducts}
                    className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                  />
                  <span className={cn("text-sm", t.text.secondary)}>
                    Select all ({filteredProducts.length} products)
                  </span>
                </div>
                <span className={cn("text-sm", t.text.secondary)}>
                  Showing {filteredProducts.length} of {products.length} products
                </span>
              </div>

              {/* Products Display */}
              {viewMode === 'grid' ? (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                  {filteredProducts.map(product => (
                    <ProductCard
                      key={product.id}
                      product={product}
                      isSelected={selectedProducts.includes(product.id)}
                      onToggleSelect={() => toggleProductSelection(product.id)}
                      theme={t}
                    />
                  ))}
                </div>
              ) : (
                <div className="space-y-3">
                  {filteredProducts.map(product => (
                    <ProductListItem
                      key={product.id}
                      product={product}
                      isSelected={selectedProducts.includes(product.id)}
                      onToggleSelect={() => toggleProductSelection(product.id)}
                      theme={t}
                    />
                  ))}
                </div>
              )}
            </>
          )}
        </div>
      </div>
    </MainLayout>
  );
}

// Product Card Component for Grid View
const ProductCard: React.FC<{
  product: Product;
  isSelected: boolean;
  onToggleSelect: () => void;
  theme: any;
}> = ({ product, isSelected, onToggleSelect, theme: t }) => {
  const [showActions, setShowActions] = useState(false);

  return (
    <div
      className={cn(
        "relative group rounded-xl transition-all duration-300",
        t.glass.card,
        t.glass.hover,
        isSelected && "ring-2 ring-blue-500"
      )}
      onMouseEnter={() => setShowActions(true)}
      onMouseLeave={() => setShowActions(false)}
    >
      {/* Selection Checkbox */}
      <div className="absolute top-3 left-3 z-10">
        <input
          type="checkbox"
          checked={isSelected}
          onChange={onToggleSelect}
          className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
        />
      </div>

      {/* Status Badge */}
      <div className="absolute top-3 right-3 z-10">
        <span
          className={cn(
            "px-2 py-1 text-xs font-medium rounded-full",
            product.is_active
              ? "bg-green-500/20 text-green-400 border border-green-500/30"
              : "bg-red-500/20 text-red-400 border border-red-500/30"
          )}
        >
          {product.is_active ? 'Active' : 'Inactive'}
        </span>
      </div>

      {/* Product Image */}
      <div className="aspect-square bg-gradient-to-br from-blue-500/10 to-purple-500/10 rounded-t-xl flex items-center justify-center">
        {product.image_url ? (
          <img
            src={product.image_url}
            alt={product.name}
            className="w-full h-full object-cover rounded-t-xl"
          />
        ) : (
          <FiPackage className={cn("w-12 h-12", t.text.muted)} />
        )}
      </div>

      {/* Product Info */}
      <div className="p-4">
        <div className="mb-3">
          <h3 className={cn("font-semibold text-sm line-clamp-2", t.text.primary)}>
            {product.name}
          </h3>
          <div className="flex items-center gap-2 mt-1">
            <span className={cn("text-xs px-2 py-1 rounded-full", t.glass.frost, t.text.secondary)}>
              Q{product.q_code}
            </span>
            <span className={cn("text-xs", t.text.secondary)}>
              {product.sku}
            </span>
          </div>
        </div>

        <div className="space-y-2 mb-4">
          <div className="flex justify-between items-center">
            <span className={cn("text-xs", t.text.secondary)}>Price/cm²:</span>
            <span className={cn("text-sm font-medium", t.text.primary)}>
              ${product.price_per_sq_cm.toFixed(2)}
            </span>
          </div>
          <div className="flex justify-between items-center">
            <span className={cn("text-xs", t.text.secondary)}>Commission:</span>
            <span className="text-sm font-medium text-green-500">
              {product.commission_rate}%
            </span>
          </div>
          <div className="flex justify-between items-center">
            <span className={cn("text-xs", t.text.secondary)}>Category:</span>
            <span className={cn("text-xs", t.text.secondary)}>
              {product.category}
            </span>
          </div>
        </div>

        {/* Documents */}
        {product.document_urls && product.document_urls.length > 0 && (
          <div className="mb-4">
            <div className="flex items-center gap-2 mb-2">
              <FiFileText className={cn("w-3 h-3", t.text.secondary)} />
              <span className={cn("text-xs", t.text.secondary)}>
                {product.document_urls.length} document{product.document_urls.length !== 1 ? 's' : ''}
              </span>
            </div>
            <div className="flex flex-wrap gap-1">
              {product.document_urls.slice(0, 3).map((url, index) => (
                <a
                  key={index}
                  href={url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className={cn(
                    "text-xs px-2 py-1 rounded-lg transition-colors",
                    t.glass.frost,
                    "hover:bg-blue-500/20 text-blue-400"
                  )}
                >
                  Doc {index + 1}
                </a>
              ))}
              {product.document_urls.length > 3 && (
                <span className={cn("text-xs px-2 py-1", t.text.muted)}>
                  +{product.document_urls.length - 3} more
                </span>
              )}
            </div>
          </div>
        )}

        {/* Actions */}
        <div className={cn(
          "flex gap-2 transition-all duration-300",
          showActions ? "opacity-100" : "opacity-0"
        )}>
          <Link
            href={`/products/${product.id}`}
            className={cn(
              "flex-1 flex items-center justify-center gap-1 px-3 py-2 rounded-lg text-xs transition-all",
              t.button.secondary.base,
              t.button.secondary.hover
            )}
          >
            <FiEye className="w-3 h-3" />
            View
          </Link>
          <Link
            href={`/products/${product.id}/edit`}
            className={cn(
              "flex-1 flex items-center justify-center gap-1 px-3 py-2 rounded-lg text-xs transition-all",
              t.button.primary.base,
              t.button.primary.hover
            )}
          >
            <FiEdit3 className="w-3 h-3" />
            Edit
          </Link>
        </div>
      </div>
    </div>
  );
};

// Product List Item Component for List View
const ProductListItem: React.FC<{
  product: Product;
  isSelected: boolean;
  onToggleSelect: () => void;
  theme: any;
}> = ({ product, isSelected, onToggleSelect, theme: t }) => {
  return (
    <div
      className={cn(
        "flex items-center gap-4 p-4 rounded-xl transition-all",
        t.glass.frost,
        t.glass.hover,
        isSelected && "ring-2 ring-blue-500"
      )}
    >
      {/* Selection */}
      <input
        type="checkbox"
        checked={isSelected}
        onChange={onToggleSelect}
        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
      />

      {/* Image */}
      <div className="w-12 h-12 bg-gradient-to-br from-blue-500/10 to-purple-500/10 rounded-lg flex items-center justify-center flex-shrink-0">
        {product.image_url ? (
          <img
            src={product.image_url}
            alt={product.name}
            className="w-full h-full object-cover rounded-lg"
          />
        ) : (
          <FiPackage className={cn("w-6 h-6", t.text.muted)} />
        )}
      </div>

      {/* Info */}
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-3 mb-1">
          <h3 className={cn("font-medium truncate", t.text.primary)}>
            {product.name}
          </h3>
          <span
            className={cn(
              "px-2 py-1 text-xs font-medium rounded-full flex-shrink-0",
              product.is_active
                ? "bg-green-500/20 text-green-400"
                : "bg-red-500/20 text-red-400"
            )}
          >
            {product.is_active ? 'Active' : 'Inactive'}
          </span>
        </div>
        <div className="flex items-center gap-4 text-sm">
          <span className={cn("text-xs", t.text.secondary)}>
            Q{product.q_code} • {product.sku}
          </span>
          <span className={cn("text-xs", t.text.secondary)}>
            {product.manufacturer}
          </span>
          <span className={cn("text-xs", t.text.secondary)}>
            {product.category}
          </span>
        </div>
      </div>

      {/* Price & Commission */}
      <div className="text-right flex-shrink-0">
        <div className={cn("font-medium", t.text.primary)}>
          ${product.price_per_sq_cm.toFixed(2)}/cm²
        </div>
        <div className="text-sm text-green-500">
          {product.commission_rate}% commission
        </div>
      </div>

      {/* Documents */}
      <div className="flex-shrink-0">
        {product.document_urls && product.document_urls.length > 0 ? (
          <div className="flex items-center gap-1">
            <FiFileText className={cn("w-4 h-4", t.text.secondary)} />
            <span className={cn("text-xs", t.text.secondary)}>
              {product.document_urls.length}
            </span>
          </div>
        ) : (
          <span className={cn("text-xs", t.text.muted)}>No docs</span>
        )}
      </div>

      {/* Actions */}
      <div className="flex gap-2 flex-shrink-0">
        <Link
          href={`/products/${product.id}`}
          className={cn(
            "p-2 rounded-lg transition-all",
            t.button.ghost.base,
            t.button.ghost.hover
          )}
        >
          <FiEye className="w-4 h-4" />
        </Link>
        <Link
          href={`/products/${product.id}/edit`}
          className={cn(
            "p-2 rounded-lg transition-all",
            t.button.primary.base,
            t.button.primary.hover
          )}
        >
          <FiEdit3 className="w-4 h-4" />
        </Link>
      </div>
    </div>
  );
};
