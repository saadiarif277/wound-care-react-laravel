import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import {
  FiEdit3,
  FiTrash2,
  FiArrowLeft,
  FiPackage,
  FiDollarSign,
  FiTag,
  FiFileText,
  FiDownload,
  FiCopy,
  FiActivity,
  FiTrendingUp,
  FiBarChart,
  FiCalendar,
  FiCheck,
  FiAlertTriangle,
  FiInfo,
  FiImage,
  FiEye,
  FiMoreVertical
} from 'react-icons/fi';

interface Size {
  id: number;
  display_label: string;
  size_type: 'rectangular' | 'square' | 'circular' | 'custom';
  length_mm?: number;
  width_mm?: number;
  diameter_mm?: number;
  area_cm2?: number;
  formatted_size: string;
}

interface Product {
  id: number;
  sku: string;
  q_code: string;
  name: string;
  description: string;
  manufacturer: string;
  category: string;
  price_per_sq_cm: number;
  national_asp?: number;
  commission_rate: number;
  sizes: Size[];
  size_unit?: string;
  mue_limit?: number;
  graph_type?: string;
  is_active: boolean;
  image_url?: string;
  document_urls?: string[];
  created_at: string;
  updated_at: string;
}

interface Props {
  product: Product;
}

export default function ProductShow({ product }: Props) {
  const { theme } = useTheme();
  const t = themes[theme];

  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [activeTab, setActiveTab] = useState<'overview' | 'pricing' | 'sizes' | 'documents' | 'activity'>('overview');
  const [copiedSku, setCopiedSku] = useState(false);

  const handleDelete = () => {
    router.delete(`/products/${product.id}`);
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    setCopiedSku(true);
    setTimeout(() => setCopiedSku(false), 2000);
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const tabs = [
    { id: 'overview', label: 'Overview', icon: FiPackage },
    { id: 'pricing', label: 'Pricing', icon: FiDollarSign },
    { id: 'sizes', label: 'Sizes', icon: FiTag },
    { id: 'documents', label: 'Documents', icon: FiFileText },
    { id: 'activity', label: 'Activity', icon: FiActivity }
  ];

  return (
    <MainLayout>
      <Head title={`${product.name} - Product Details`} />

      <div className="space-y-6">
        {/* Header */}
        <div className={cn("p-6 rounded-2xl", t.glass.card)}>
          <div className="flex items-start justify-between">
            <div className="flex items-start gap-4">
              <button
                onClick={() => router.get('/products')}
                className={cn(
                  "p-2 rounded-xl transition-all mt-1",
                  t.button.ghost.base,
                  t.button.ghost.hover
                )}
              >
                <FiArrowLeft className="w-5 h-5" />
              </button>

              <div className="flex items-start gap-4">
                <div className="w-20 h-20 bg-gradient-to-br from-blue-500/10 to-purple-500/10 rounded-2xl flex items-center justify-center">
                  {product.image_url ? (
                    <img
                      src={product.image_url}
                      alt={product.name}
                      className="w-full h-full object-cover rounded-2xl"
                    />
                  ) : (
                    <FiPackage className={cn("w-10 h-10", t.text.muted)} />
                  )}
                </div>

                <div>
                  <div className="flex items-center gap-3 mb-2">
                    <h1 className={cn("text-3xl font-bold", t.text.primary)}>
                      {product.name}
                    </h1>
                    <span
                      className={cn(
                        "px-3 py-1 text-sm font-medium rounded-full",
                        product.is_active
                          ? "bg-green-500/20 text-green-400"
                          : "bg-red-500/20 text-red-400"
                      )}
                    >
                      {product.is_active ? 'Active' : 'Inactive'}
                    </span>
                  </div>

                  <div className="flex items-center gap-6 mb-3">
                    <div className="flex items-center gap-2">
                      <span className={cn("text-sm", t.text.secondary)}>Q{product.q_code}</span>
                      <button
                        onClick={() => copyToClipboard(product.q_code)}
                        className={cn("p-1 rounded hover:bg-white/10 transition-colors")}
                      >
                        {copiedSku ? (
                          <FiCheck className="w-3 h-3 text-green-500" />
                        ) : (
                          <FiCopy className="w-3 h-3 text-gray-400" />
                        )}
                      </button>
                    </div>

                    <div className="flex items-center gap-2">
                      <span className={cn("text-sm", t.text.secondary)}>{product.sku}</span>
                      <button
                        onClick={() => copyToClipboard(product.sku)}
                        className={cn("p-1 rounded hover:bg-white/10 transition-colors")}
                      >
                        <FiCopy className="w-3 h-3 text-gray-400" />
                      </button>
                    </div>

                    <span className={cn("text-sm", t.text.secondary)}>
                      {product.manufacturer}
                    </span>

                    <span className={cn("text-sm px-2 py-1 rounded-lg", t.glass.frost)}>
                      {product.category}
                    </span>
                  </div>

                  <p className={cn("text-sm max-w-2xl", t.text.secondary)}>
                    {product.description || 'No description available'}
                  </p>
                </div>
              </div>
            </div>

            <div className="flex items-center gap-3">
              <button
                onClick={() => router.get(`/products/${product.id}/edit`)}
                className={cn(
                  "flex items-center gap-2 px-4 py-2 rounded-xl font-medium transition-all",
                  t.button.primary.base,
                  t.button.primary.hover
                )}
              >
                <FiEdit3 className="w-4 h-4" />
                Edit Product
              </button>

              <div className="relative">
                <button
                  onClick={() => setShowDeleteModal(true)}
                  className={cn(
                    "flex items-center gap-2 px-4 py-2 rounded-xl font-medium transition-all",
                    "bg-red-500/10 text-red-400 hover:bg-red-500/20"
                  )}
                >
                  <FiTrash2 className="w-4 h-4" />
                  Delete
                </button>
              </div>
            </div>
          </div>

          {/* Quick Stats */}
          <div className={cn("mt-6 p-4 rounded-xl", t.glass.frost)}>
            <div className="grid grid-cols-1 md:grid-cols-5 gap-6">
              <div className="text-center">
                <div className={cn("text-2xl font-bold", t.text.primary)}>
                  ${product.price_per_sq_cm.toFixed(2)}
                </div>
                <div className={cn("text-xs", t.text.secondary)}>Price per cm²</div>
              </div>

              <div className="text-center">
                <div className={cn("text-2xl font-bold text-green-500")}>
                  {product.commission_rate}%
                </div>
                <div className={cn("text-xs", t.text.secondary)}>Commission</div>
              </div>

              <div className="text-center">
                <div className={cn("text-2xl font-bold", t.text.primary)}>
                  {product.sizes.length}
                </div>
                <div className={cn("text-xs", t.text.secondary)}>Size Options</div>
              </div>

              <div className="text-center">
                <div className={cn("text-2xl font-bold", t.text.primary)}>
                  {product.document_urls?.length || 0}
                </div>
                <div className={cn("text-xs", t.text.secondary)}>Documents</div>
              </div>

              <div className="text-center">
                <div className={cn("text-2xl font-bold", t.text.primary)}>
                  {product.mue_limit || 'N/A'}
                </div>
                <div className={cn("text-xs", t.text.secondary)}>MUE Limit</div>
              </div>
            </div>
          </div>
        </div>

        {/* Content Tabs */}
        <div className={cn("rounded-2xl", t.glass.card)}>
          {/* Tab Navigation */}
          <div className="border-b border-white/10">
            <nav className="flex space-x-8 px-6">
              {tabs.map((tab) => {
                const Icon = tab.icon;
                return (
                  <button
                    key={tab.id}
                    onClick={() => setActiveTab(tab.id as any)}
                    className={cn(
                      "flex items-center gap-2 py-4 px-2 border-b-2 font-medium text-sm transition-all",
                      activeTab === tab.id
                        ? "border-blue-500 text-blue-400"
                        : cn("border-transparent", t.text.secondary, "hover:text-blue-400")
                    )}
                  >
                    <Icon className="w-4 h-4" />
                    {tab.label}
                  </button>
                );
              })}
            </nav>
          </div>

          <div className="p-6">
            {/* Overview Tab */}
            {activeTab === 'overview' && (
              <div className="space-y-6">
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                  <div className={cn("p-4 rounded-xl", t.glass.frost)}>
                    <h3 className={cn("text-lg font-semibold mb-4", t.text.primary)}>
                      Product Information
                    </h3>
                    <div className="space-y-3">
                      <div className="flex justify-between">
                        <span className={cn("text-sm", t.text.secondary)}>SKU:</span>
                        <span className={cn("text-sm font-medium", t.text.primary)}>{product.sku}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className={cn("text-sm", t.text.secondary)}>Q-Code:</span>
                        <span className={cn("text-sm font-medium", t.text.primary)}>Q{product.q_code}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className={cn("text-sm", t.text.secondary)}>Manufacturer:</span>
                        <span className={cn("text-sm font-medium", t.text.primary)}>{product.manufacturer}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className={cn("text-sm", t.text.secondary)}>Category:</span>
                        <span className={cn("text-sm font-medium", t.text.primary)}>{product.category}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className={cn("text-sm", t.text.secondary)}>Graph Type:</span>
                        <span className={cn("text-sm font-medium", t.text.primary)}>
                          {product.graph_type || 'Not specified'}
                        </span>
                      </div>
                      <div className="flex justify-between">
                        <span className={cn("text-sm", t.text.secondary)}>Size Unit:</span>
                        <span className={cn("text-sm font-medium", t.text.primary)}>
                          {product.size_unit === 'cm' ? 'Centimeters (cm²)' : 'Inches'}
                        </span>
                      </div>
                    </div>
                  </div>

                  <div className={cn("p-4 rounded-xl", t.glass.frost)}>
                    <h3 className={cn("text-lg font-semibold mb-4", t.text.primary)}>
                      Metadata
                    </h3>
                    <div className="space-y-3">
                      <div className="flex justify-between">
                        <span className={cn("text-sm", t.text.secondary)}>Created:</span>
                        <span className={cn("text-sm font-medium", t.text.primary)}>
                          {formatDate(product.created_at)}
                        </span>
                      </div>
                      <div className="flex justify-between">
                        <span className={cn("text-sm", t.text.secondary)}>Last Updated:</span>
                        <span className={cn("text-sm font-medium", t.text.primary)}>
                          {formatDate(product.updated_at)}
                        </span>
                      </div>
                      <div className="flex justify-between">
                        <span className={cn("text-sm", t.text.secondary)}>Status:</span>
                        <span
                          className={cn(
                            "text-sm font-medium px-2 py-1 rounded-full",
                            product.is_active
                              ? "bg-green-500/20 text-green-400"
                              : "bg-red-500/20 text-red-400"
                          )}
                        >
                          {product.is_active ? 'Active' : 'Inactive'}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>

                {product.description && (
                  <div className={cn("p-4 rounded-xl", t.glass.frost)}>
                    <h3 className={cn("text-lg font-semibold mb-3", t.text.primary)}>
                      Description
                    </h3>
                    <p className={cn("text-sm leading-relaxed", t.text.secondary)}>
                      {product.description}
                    </p>
                  </div>
                )}
              </div>
            )}

            {/* Pricing Tab */}
            {activeTab === 'pricing' && (
              <div className="space-y-6">
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                  <div className={cn("p-6 rounded-xl text-center", t.glass.frost)}>
                    <FiDollarSign className={cn("w-8 h-8 mx-auto mb-3 text-blue-500")} />
                    <div className={cn("text-2xl font-bold mb-1", t.text.primary)}>
                      ${product.price_per_sq_cm.toFixed(2)}
                    </div>
                    <div className={cn("text-sm", t.text.secondary)}>Price per cm²</div>
                  </div>

                  <div className={cn("p-6 rounded-xl text-center", t.glass.frost)}>
                    <FiTrendingUp className={cn("w-8 h-8 mx-auto mb-3 text-green-500")} />
                    <div className={cn("text-2xl font-bold mb-1 text-green-500")}>
                      {product.commission_rate}%
                    </div>
                    <div className={cn("text-sm", t.text.secondary)}>Commission Rate</div>
                  </div>

                  <div className={cn("p-6 rounded-xl text-center", t.glass.frost)}>
                    <FiBarChart className={cn("w-8 h-8 mx-auto mb-3 text-purple-500")} />
                    <div className={cn("text-2xl font-bold mb-1", t.text.primary)}>
                      ${product.national_asp?.toFixed(2) || 'N/A'}
                    </div>
                    <div className={cn("text-sm", t.text.secondary)}>National ASP</div>
                  </div>
                </div>

                {/* Pricing Calculator */}
                <div className={cn("p-6 rounded-xl", t.glass.frost)}>
                  <h3 className={cn("text-lg font-semibold mb-4", t.text.primary)}>
                    Pricing Calculator
                  </h3>
                  <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    {[1, 5, 10, 15, 20, 25, 30, 50, 75, 100].map(size => (
                      <div key={size} className={cn("p-3 rounded-lg text-center", "bg-white/5")}>
                        <div className={cn("text-xs mb-1", t.text.secondary)}>{size} cm²</div>
                        <div className={cn("text-sm font-bold", t.text.primary)}>
                          ${(product.price_per_sq_cm * size).toFixed(2)}
                        </div>
                        <div className="text-xs text-green-500">
                          ${((product.price_per_sq_cm * size * product.commission_rate) / 100).toFixed(2)} comm.
                        </div>
                      </div>
                    ))}
                  </div>
                </div>

                {product.mue_limit && (
                  <div className={cn("p-4 rounded-xl", t.status.warning)}>
                    <div className="flex items-start gap-3">
                      <FiInfo className="w-5 h-5 flex-shrink-0 mt-0.5" />
                      <div>
                        <h4 className="font-medium mb-1">MUE Limit Notice</h4>
                        <p className="text-sm">
                          This product has a Maximum Units of Eligibility limit of {product.mue_limit} cm² per patient.
                        </p>
                      </div>
                    </div>
                  </div>
                )}
              </div>
            )}

            {/* Sizes Tab */}
            {activeTab === 'sizes' && (
              <div className="space-y-6">
                {product.sizes.length > 0 ? (
                  <div>
                    <h3 className={cn("text-lg font-semibold mb-4", t.text.primary)}>
                      Available Sizes
                    </h3>
                    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                      {product.sizes.map((size) => (
                        <div
                          key={size.id}
                          className={cn("p-4 rounded-xl text-center", t.glass.frost)}
                        >
                          <div className={cn("text-lg font-bold", t.text.primary)}>
                            {size.formatted_size}
                          </div>
                          <div className={cn("text-xs mt-1", t.text.secondary)}>
                            {size.size_type}
                          </div>
                          {size.area_cm2 && (
                            <div className="text-xs text-gray-400 mt-1">
                              {size.area_cm2} cm²
                            </div>
                          )}
                          <div className="text-sm text-green-500 mt-2 font-semibold">
                            ${(product.price_per_sq_cm * (size.area_cm2 || 0)).toFixed(2)}
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                ) : (
                  <div className={cn("text-center py-12", t.glass.frost, "rounded-xl")}>
                    <FiTag className={cn("w-16 h-16 mx-auto mb-4", t.text.muted)} />
                    <h3 className={cn("text-lg font-medium mb-2", t.text.primary)}>
                      No Sizes Configured
                    </h3>
                    <p className={cn("text-sm", t.text.secondary)}>
                      This product doesn't have any size options configured yet.
                    </p>
                    <button
                      onClick={() => router.get(`/products/${product.id}/edit`)}
                      className={cn(
                        "mt-4 px-4 py-2 rounded-lg text-sm",
                        t.button.primary.base,
                        t.button.primary.hover
                      )}
                    >
                      Add Sizes
                    </button>
                  </div>
                )}
              </div>
            )}

            {/* Documents Tab */}
            {activeTab === 'documents' && (
              <div className="space-y-6">
                {product.document_urls && product.document_urls.length > 0 ? (
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {product.document_urls.map((url, index) => (
                      <div
                        key={index}
                        className={cn("p-4 rounded-xl border transition-all hover:shadow-lg", t.glass.frost)}
                      >
                        <div className="flex items-start justify-between mb-3">
                          <div className="flex items-center gap-3">
                            {url.toLowerCase().includes('.pdf') ? (
                              <FiFileText className={cn("w-6 h-6 text-red-500")} />
                            ) : url.match(/\.(jpg|jpeg|png|gif)$/i) ? (
                              <FiImage className={cn("w-6 h-6 text-blue-500")} />
                            ) : (
                              <FiFileText className={cn("w-6 h-6", t.text.secondary)} />
                            )}
                          </div>
                          <button className={cn("p-1 rounded hover:bg-white/10")}>
                            <FiMoreVertical className="w-4 h-4" />
                          </button>
                        </div>

                        <h4 className={cn("text-sm font-medium mb-2 truncate", t.text.primary)}>
                          {url.split('/').pop()?.replace(/\.[^/.]+$/, '') || 'Document'}
                        </h4>

                        <div className="flex gap-2">
                          <a
                            href={url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className={cn(
                              "flex-1 flex items-center justify-center gap-1 px-3 py-2 rounded-lg text-xs",
                              t.button.secondary.base,
                              t.button.secondary.hover
                            )}
                          >
                            <FiEye className="w-3 h-3" />
                            View
                          </a>
                          <a
                            href={url}
                            download
                            className={cn(
                              "flex-1 flex items-center justify-center gap-1 px-3 py-2 rounded-lg text-xs",
                              t.button.primary.base,
                              t.button.primary.hover
                            )}
                          >
                            <FiDownload className="w-3 h-3" />
                            Download
                          </a>
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className={cn("text-center py-12", t.glass.frost, "rounded-xl")}>
                    <FiFileText className={cn("w-16 h-16 mx-auto mb-4", t.text.muted)} />
                    <h3 className={cn("text-lg font-medium mb-2", t.text.primary)}>
                      No Documents Available
                    </h3>
                    <p className={cn("text-sm", t.text.secondary)}>
                      This product doesn't have any documents uploaded yet.
                    </p>
                    <button
                      onClick={() => router.get(`/products/${product.id}/edit`)}
                      className={cn(
                        "mt-4 px-4 py-2 rounded-lg text-sm",
                        t.button.primary.base,
                        t.button.primary.hover
                      )}
                    >
                      Upload Documents
                    </button>
                  </div>
                )}
              </div>
            )}

            {/* Activity Tab */}
            {activeTab === 'activity' && (
              <div className="space-y-6">
                <div className={cn("p-4 rounded-xl", t.glass.frost)}>
                  <h3 className={cn("text-lg font-semibold mb-4", t.text.primary)}>
                    Recent Activity
                  </h3>
                  <div className="space-y-4">
                    <div className="flex items-start gap-3">
                      <div className="w-8 h-8 rounded-full bg-blue-500/20 flex items-center justify-center">
                        <FiCalendar className="w-4 h-4 text-blue-500" />
                      </div>
                      <div className="flex-1">
                        <p className={cn("text-sm", t.text.primary)}>Product created</p>
                        <p className={cn("text-xs", t.text.secondary)}>
                          {formatDate(product.created_at)}
                        </p>
                      </div>
                    </div>

                    {product.updated_at !== product.created_at && (
                      <div className="flex items-start gap-3">
                        <div className="w-8 h-8 rounded-full bg-green-500/20 flex items-center justify-center">
                          <FiEdit3 className="w-4 h-4 text-green-500" />
                        </div>
                        <div className="flex-1">
                          <p className={cn("text-sm", t.text.primary)}>Product updated</p>
                          <p className={cn("text-xs", t.text.secondary)}>
                            {formatDate(product.updated_at)}
                          </p>
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Delete Confirmation Modal */}
      {showDeleteModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className={cn("p-6 rounded-2xl max-w-md w-full mx-4", t.glass.card)}>
            <div className="flex items-center gap-3 mb-4">
              <FiAlertTriangle className="w-6 h-6 text-red-500" />
              <h3 className={cn("text-lg font-semibold", t.text.primary)}>
                Delete Product
              </h3>
            </div>

            <p className={cn("mb-6", t.text.secondary)}>
              Are you sure you want to delete "{product.name}"? This action cannot be undone.
            </p>

            <div className="flex gap-3 justify-end">
              <button
                onClick={() => setShowDeleteModal(false)}
                className={cn(
                  "px-4 py-2 rounded-xl font-medium",
                  t.button.ghost.base,
                  t.button.ghost.hover
                )}
              >
                Cancel
              </button>
              <button
                onClick={handleDelete}
                className={cn(
                  "px-4 py-2 rounded-xl font-medium",
                  "bg-red-500/20 text-red-400 hover:bg-red-500/30"
                )}
              >
                Delete Product
              </button>
            </div>
          </div>
        </div>
      )}
    </MainLayout>
  );
}
