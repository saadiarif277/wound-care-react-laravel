import React, { useState, useEffect } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import axios from 'axios';
import {
  FiSave,
  FiX,
  FiUpload,
  FiFileText,
  FiImage,
  FiDownload,
  FiTrash2,
  FiPackage,
  FiDollarSign,
  FiTag,
  FiInfo,
  FiAlertCircle,
  FiCheck,
  FiEye,
  FiEdit3,
  FiPlus,
  FiMinus,
  FiCopy,
  FiExternalLink
} from 'react-icons/fi';

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
  available_sizes: number[];
  size_options?: string[];
  size_unit?: string;
  mue?: number;
  graph_type?: string;
  is_active: boolean;
  image_url?: string;
  document_urls?: string[];
  created_at: string;
  updated_at: string;
}

interface Props {
  product: Product;
  categories: string[];
  manufacturers: string[];
}

export default function ProductEdit({ product, categories, manufacturers }: Props) {
  const { theme } = useTheme();
  const t = themes[theme];

  const [activeTab, setActiveTab] = useState<'basic' | 'pricing' | 'sizes' | 'documents' | 'history'>('basic');
  const [uploadingFiles, setUploadingFiles] = useState(false);
  const [previewUrls, setPreviewUrls] = useState<string[]>([]);
  const [pricingHistory, setPricingHistory] = useState<any[]>([]);
  const [loadingHistory, setLoadingHistory] = useState(false);

  // Helper function to safely format prices
  const formatPrice = (value: any): string => {
    const numValue = typeof value === 'string' ? parseFloat(value) : value;
    return (numValue && !isNaN(numValue)) ? numValue.toFixed(2) : '0.00';
  };

  // Helper function to safely convert to number
  const toNumber = (value: any): number => {
    const numValue = typeof value === 'string' ? parseFloat(value) : value;
    return (numValue && !isNaN(numValue)) ? numValue : 0;
  };

  const { data, setData, put, processing, errors, reset } = useForm({
    sku: product.sku || '',
    q_code: product.q_code || '',
    name: product.name || '',
    description: product.description || '',
    manufacturer: product.manufacturer || '',
    category: product.category || '',
    price_per_sq_cm: toNumber(product.price_per_sq_cm),
    national_asp: toNumber(product.national_asp),
    commission_rate: toNumber(product.commission_rate),
    available_sizes: product.available_sizes || [],
    size_options: product.size_options || [],
    size_unit: product.size_unit || 'cm',
    mue: toNumber(product.mue),
    graph_type: product.graph_type || '',
    is_active: product.is_active ?? true,
    image_url: product.image_url || '',
    document_urls: product.document_urls || [],
    new_documents: [] as File[],
    remove_documents: [] as string[]
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    // Create FormData for file uploads
    const formData = new FormData();

    // Add all form fields
    Object.entries(data).forEach(([key, value]) => {
      if (key === 'new_documents') {
        // Handle file uploads
        (value as File[]).forEach((file, index) => {
          formData.append(`new_documents[${index}]`, file);
        });
      } else if (Array.isArray(value)) {
        // Handle arrays
        value.forEach((item, index) => {
          formData.append(`${key}[${index}]`, String(item));
        });
      } else {
        formData.append(key, String(value));
      }
    });

    router.post(`/products/${product.id}`, formData, {
      forceFormData: true,
      onSuccess: () => {
        // Handle success
      },
      onError: (errors) => {
        console.error('Form submission errors:', errors);
      },
    });
  };

  const handleFileUpload = (files: FileList | null) => {
    if (!files) return;

    const newFiles = Array.from(files);
    setData('new_documents', [...data.new_documents, ...newFiles]);

    // Create preview URLs
    const newPreviewUrls = newFiles.map(file => URL.createObjectURL(file));
    setPreviewUrls(prev => [...prev, ...newPreviewUrls]);
  };

  const removeNewDocument = (index: number) => {
    const newDocuments = data.new_documents.filter((_, i) => i !== index);
    setData('new_documents', newDocuments);

    // Clean up preview URLs
    URL.revokeObjectURL(previewUrls[index]);
    setPreviewUrls(prev => prev.filter((_, i) => i !== index));
  };

  const removeExistingDocument = (url: string) => {
    setData('remove_documents', [...data.remove_documents, url]);
    setData('document_urls', data.document_urls.filter(docUrl => docUrl !== url));
  };

  const addSize = () => {
    setData('available_sizes', [...data.available_sizes, 0]);
  };

  const updateSize = (index: number, value: number) => {
    const newSizes = [...data.available_sizes];
    newSizes[index] = value;
    setData('available_sizes', newSizes);
  };

  const removeSize = (index: number) => {
    setData('available_sizes', data.available_sizes.filter((_, i) => i !== index));
  };

  const addSizeOption = () => {
    setData('size_options', [...data.size_options, '']);
  };

  const updateSizeOption = (index: number, value: string) => {
    const newOptions = [...data.size_options];
    newOptions[index] = value;
    setData('size_options', newOptions);
  };

  const removeSizeOption = (index: number) => {
    setData('size_options', data.size_options.filter((_, i) => i !== index));
  };

  // Fetch pricing history when history tab is active
  useEffect(() => {
    if (activeTab === 'history' && pricingHistory.length === 0) {
      setLoadingHistory(true);
      axios.get(`/products/${product.id}/pricing-history`)
        .then(response => {
          setPricingHistory(response.data.history || []);
        })
        .catch(error => {
          console.error('Failed to fetch pricing history:', error);
        })
        .finally(() => {
          setLoadingHistory(false);
        });
    }
  }, [activeTab, product.id, pricingHistory.length]);

  const tabs = [
    { id: 'basic', label: 'Basic Info', icon: FiPackage },
    { id: 'pricing', label: 'Pricing', icon: FiDollarSign },
    { id: 'sizes', label: 'Sizes', icon: FiTag },
    { id: 'documents', label: 'Documents', icon: FiFileText },
    { id: 'history', label: 'Pricing History', icon: FiCopy }
  ];

  return (
    <MainLayout>
      <Head title={`Edit Product - ${product.name}`} />

      <div className="space-y-6">
        {/* Header */}
        <div className={cn("p-6 rounded-2xl", t.glass.card)}>
          <div className="flex items-center justify-between">
            <div>
              <h1 className={cn("text-3xl font-bold flex items-center gap-3", t.text.primary)}>
                <FiEdit3 className="w-8 h-8 text-blue-500" />
                Edit Product
              </h1>
              <p className={cn("text-sm mt-2", t.text.secondary)}>
                Update product information, pricing, and documentation
              </p>
            </div>
            <div className="flex items-center gap-3">
              <button
                onClick={() => router.get(`/products/${product.id}`)}
                className={cn(
                  "flex items-center gap-2 px-4 py-2 rounded-xl font-medium transition-all",
                  t.button.secondary.base,
                  t.button.secondary.hover
                )}
              >
                <FiEye className="w-4 h-4" />
                View Product
              </button>
              <button
                onClick={() => router.get('/products')}
                className={cn(
                  "flex items-center gap-2 px-4 py-2 rounded-xl font-medium transition-all",
                  t.button.ghost.base,
                  t.button.ghost.hover
                )}
              >
                <FiX className="w-4 h-4" />
                Cancel
              </button>
            </div>
          </div>

          {/* Product Summary */}
          <div className={cn("mt-6 p-4 rounded-xl", t.glass.frost)}>
            <div className="flex items-center gap-4">
              <div className="w-16 h-16 bg-gradient-to-br from-blue-500/10 to-purple-500/10 rounded-xl flex items-center justify-center">
                {product.image_url ? (
                  <img
                    src={product.image_url}
                    alt={product.name}
                    className="w-full h-full object-cover rounded-xl"
                  />
                ) : (
                  <FiPackage className={cn("w-8 h-8", t.text.muted)} />
                )}
              </div>
              <div className="flex-1">
                <h3 className={cn("text-lg font-semibold", t.text.primary)}>
                  {product.name}
                </h3>
                <div className="flex items-center gap-4 mt-1">
                  <span className={cn("text-sm", t.text.secondary)}>
                    Q{product.q_code} • {product.sku}
                  </span>
                  <span className={cn("text-sm", t.text.secondary)}>
                    {product.manufacturer}
                  </span>
                  <span
                    className={cn(
                      "px-2 py-1 text-xs font-medium rounded-full",
                      product.is_active
                        ? "bg-green-500/20 text-green-400"
                        : "bg-red-500/20 text-red-400"
                    )}
                  >
                    {product.is_active ? 'Active' : 'Inactive'}
                  </span>
                </div>
              </div>
              <div className="text-right">
                <div className={cn("text-lg font-bold", t.text.primary)}>
                  ${formatPrice(product.price_per_sq_cm)}/cm²
                </div>
                <div className="text-sm text-green-500">
                  {product.commission_rate}% commission
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Form */}
        <div className={cn("rounded-2xl", t.glass.card)}>
          {/* Tabs */}
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

          <form onSubmit={handleSubmit} className="p-6">
            {/* Basic Info Tab */}
            {activeTab === 'basic' && (
              <div className="space-y-6">
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                  <div>
                    <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                      Product Name *
                    </label>
                    <input
                      type="text"
                      value={data.name}
                      onChange={(e) => setData('name', e.target.value)}
                      className={cn(
                        "w-full px-4 py-3 rounded-xl",
                        t.input.base,
                        t.input.focus,
                        errors.name && "border-red-500"
                      )}
                      placeholder="Enter product name..."
                    />
                    {errors.name && <p className="text-sm mt-1 text-red-500">{errors.name}</p>}
                  </div>

                  <div>
                    <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                      SKU *
                    </label>
                    <input
                      type="text"
                      value={data.sku}
                      onChange={(e) => setData('sku', e.target.value)}
                      className={cn(
                        "w-full px-4 py-3 rounded-xl",
                        t.input.base,
                        t.input.focus,
                        errors.sku && "border-red-500"
                      )}
                      placeholder="Enter SKU..."
                    />
                    {errors.sku && <p className="text-sm mt-1 text-red-500">{errors.sku}</p>}
                  </div>

                  <div>
                    <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                      Q-Code *
                    </label>
                    <input
                      type="text"
                      value={data.q_code}
                      onChange={(e) => setData('q_code', e.target.value)}
                      className={cn(
                        "w-full px-4 py-3 rounded-xl",
                        t.input.base,
                        t.input.focus,
                        errors.q_code && "border-red-500"
                      )}
                      placeholder="Enter Q-code..."
                    />
                    {errors.q_code && <p className="text-sm mt-1 text-red-500">{errors.q_code}</p>}
                  </div>

                  <div>
                    <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                      Manufacturer *
                    </label>
                    <select
                      value={data.manufacturer}
                      onChange={(e) => setData('manufacturer', e.target.value)}
                      className={cn(
                        "w-full px-4 py-3 rounded-xl",
                        t.input.base,
                        t.input.focus,
                        errors.manufacturer && "border-red-500"
                      )}
                    >
                      <option value="">Select manufacturer...</option>
                      {manufacturers.map(manufacturer => (
                        <option key={manufacturer} value={manufacturer}>
                          {manufacturer}
                        </option>
                      ))}
                    </select>
                    {errors.manufacturer && <p className="text-sm mt-1 text-red-500">{errors.manufacturer}</p>}
                  </div>

                  <div>
                    <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                      Category *
                    </label>
                    <select
                      value={data.category}
                      onChange={(e) => setData('category', e.target.value)}
                      className={cn(
                        "w-full px-4 py-3 rounded-xl",
                        t.input.base,
                        t.input.focus,
                        errors.category && "border-red-500"
                      )}
                    >
                      <option value="">Select category...</option>
                      {categories.map(category => (
                        <option key={category} value={category}>
                          {category}
                        </option>
                      ))}
                    </select>
                    {errors.category && <p className="text-sm mt-1 text-red-500">{errors.category}</p>}
                  </div>

                  <div>
                    <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                      Status
                    </label>
                    <div className="flex items-center gap-4">
                      <label className="flex items-center gap-2">
                        <input
                          type="radio"
                          checked={data.is_active}
                          onChange={() => setData('is_active', true)}
                          className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                        />
                        <span className={cn("text-sm", t.text.primary)}>Active</span>
                      </label>
                      <label className="flex items-center gap-2">
                        <input
                          type="radio"
                          checked={!data.is_active}
                          onChange={() => setData('is_active', false)}
                          className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                        />
                        <span className={cn("text-sm", t.text.primary)}>Inactive</span>
                      </label>
                    </div>
                  </div>
                </div>

                <div>
                  <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                    Description
                  </label>
                  <textarea
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                    rows={4}
                    className={cn(
                      "w-full px-4 py-3 rounded-xl",
                      t.input.base,
                      t.input.focus,
                      errors.description && "border-red-500"
                    )}
                    placeholder="Enter product description..."
                  />
                  {errors.description && <p className="text-sm mt-1 text-red-500">{errors.description}</p>}
                </div>

                <div>
                  <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                    Product Image URL
                  </label>
                  <input
                    type="url"
                    value={data.image_url}
                    onChange={(e) => setData('image_url', e.target.value)}
                    className={cn(
                      "w-full px-4 py-3 rounded-xl",
                      t.input.base,
                      t.input.focus
                    )}
                    placeholder="https://example.com/image.jpg"
                  />
                  {data.image_url && (
                    <div className="mt-3">
                      <img
                        src={data.image_url}
                        alt="Product preview"
                        className="w-32 h-32 object-cover rounded-xl"
                        onError={(e) => {
                          e.currentTarget.style.display = 'none';
                        }}
                      />
                    </div>
                  )}
                </div>
              </div>
            )}

            {/* Pricing Tab */}
            {activeTab === 'pricing' && (
              <div className="space-y-6">
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                  <div>
                    <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                      Price per cm² *
                    </label>
                    <div className="relative">
                      <span className={cn("absolute left-3 top-1/2 transform -translate-y-1/2", t.text.secondary)}>
                        $
                      </span>
                      <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={data.price_per_sq_cm}
                        onChange={(e) => setData('price_per_sq_cm', toNumber(e.target.value))}
                        className={cn(
                          "w-full pl-8 pr-4 py-3 rounded-xl",
                          t.input.base,
                          t.input.focus,
                          errors.price_per_sq_cm && "border-red-500"
                        )}
                        placeholder="0.00"
                      />
                    </div>
                    {errors.price_per_sq_cm && <p className="text-sm mt-1 text-red-500">{errors.price_per_sq_cm}</p>}
                  </div>

                  <div>
                    <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                      National ASP
                    </label>
                    <div className="relative">
                      <span className={cn("absolute left-3 top-1/2 transform -translate-y-1/2", t.text.secondary)}>
                        $
                      </span>
                      <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={data.national_asp}
                        onChange={(e) => setData('national_asp', toNumber(e.target.value))}
                        className={cn(
                          "w-full pl-8 pr-4 py-3 rounded-xl",
                          t.input.base,
                          t.input.focus
                        )}
                        placeholder="0.00"
                      />
                    </div>
                  </div>

                  <div>
                    <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                      Commission Rate *
                    </label>
                    <div className="relative">
                      <input
                        type="number"
                        step="0.1"
                        min="0"
                        max="100"
                        value={data.commission_rate}
                        onChange={(e) => setData('commission_rate', toNumber(e.target.value))}
                        className={cn(
                          "w-full pr-8 pl-4 py-3 rounded-xl",
                          t.input.base,
                          t.input.focus,
                          errors.commission_rate && "border-red-500"
                        )}
                        placeholder="0.0"
                      />
                      <span className={cn("absolute right-3 top-1/2 transform -translate-y-1/2", t.text.secondary)}>
                        %
                      </span>
                    </div>
                    {errors.commission_rate && <p className="text-sm mt-1 text-red-500">{errors.commission_rate}</p>}
                  </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                  <div>
                    <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                      MUE Limit
                    </label>
                    <input
                      type="number"
                      min="0"
                      value={data.mue}
                      onChange={(e) => setData('mue', toNumber(e.target.value))}
                      className={cn(
                        "w-full px-4 py-3 rounded-xl",
                        t.input.base,
                        t.input.focus
                      )}
                      placeholder="Maximum Units of Eligibility"
                    />
                    <p className={cn("text-xs mt-1", t.text.secondary)}>
                      Maximum units that can be billed per patient
                    </p>
                  </div>

                  <div>
                    <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                      Graph Type
                    </label>
                    <select
                      value={data.graph_type}
                      onChange={(e) => setData('graph_type', e.target.value)}
                      className={cn(
                        "w-full px-4 py-3 rounded-xl",
                        t.input.base,
                        t.input.focus
                      )}
                    >
                      <option value="">Select graph type...</option>
                      <option value="linear">Linear</option>
                      <option value="exponential">Exponential</option>
                      <option value="logarithmic">Logarithmic</option>
                    </select>
                  </div>
                </div>

                {/* Pricing Preview */}
                <div className={cn("p-4 rounded-xl", t.glass.frost)}>
                  <h3 className={cn("text-sm font-medium mb-3", t.text.primary)}>
                    Pricing Preview
                  </h3>
                  <div className="grid grid-cols-3 gap-4 text-sm">
                    <div>
                      <span className={cn("block", t.text.secondary)}>1 cm²</span>
                      <span className={cn("font-medium", t.text.primary)}>
                        ${formatPrice(data.price_per_sq_cm)}
                      </span>
                    </div>
                    <div>
                      <span className={cn("block", t.text.secondary)}>10 cm²</span>
                      <span className={cn("font-medium", t.text.primary)}>
                        ${formatPrice(data.price_per_sq_cm * 10)}
                      </span>
                    </div>
                    <div>
                      <span className={cn("block", t.text.secondary)}>25 cm²</span>
                      <span className={cn("font-medium", t.text.primary)}>
                        ${formatPrice(data.price_per_sq_cm * 25)}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            )}

            {/* Sizes Tab */}
            {activeTab === 'sizes' && (
              <div className="space-y-6">
                <div>
                  <label className={cn("block text-sm font-medium mb-2", t.text.primary)}>
                    Size Unit
                  </label>
                  <select
                    value={data.size_unit}
                    onChange={(e) => setData('size_unit', e.target.value)}
                    className={cn(
                      "w-full px-4 py-3 rounded-xl max-w-xs",
                      t.input.base,
                      t.input.focus
                    )}
                  >
                    <option value="cm">Centimeters (cm²)</option>
                    <option value="inches">Inches</option>
                  </select>
                </div>

                {/* Available Sizes */}
                <div>
                  <div className="flex items-center justify-between mb-4">
                    <h3 className={cn("text-lg font-medium", t.text.primary)}>
                      Available Sizes ({data.size_unit === 'cm' ? 'cm²' : 'inches'})
                    </h3>
                    <button
                      type="button"
                      onClick={addSize}
                      className={cn(
                        "flex items-center gap-2 px-3 py-2 rounded-lg text-sm",
                        t.button.primary.base,
                        t.button.primary.hover
                      )}
                    >
                      <FiPlus className="w-4 h-4" />
                      Add Size
                    </button>
                  </div>

                  <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    {data.available_sizes.map((size, index) => (
                      <div key={index} className="flex items-center gap-2">
                        <input
                          type="number"
                          min="0"
                          step="0.1"
                          value={size}
                          onChange={(e) => updateSize(index, toNumber(e.target.value))}
                          className={cn(
                            "flex-1 px-3 py-2 rounded-lg text-sm",
                            t.input.base,
                            t.input.focus
                          )}
                          placeholder="0"
                        />
                        <button
                          type="button"
                          onClick={() => removeSize(index)}
                          className={cn(
                            "p-2 rounded-lg text-red-500 hover:bg-red-500/10"
                          )}
                        >
                          <FiMinus className="w-3 h-3" />
                        </button>
                      </div>
                    ))}
                  </div>

                  {data.available_sizes.length === 0 && (
                    <div className={cn("text-center py-8", t.glass.frost, "rounded-xl")}>
                      <FiTag className={cn("w-12 h-12 mx-auto mb-3", t.text.muted)} />
                      <p className={cn("text-sm", t.text.secondary)}>
                        No sizes configured. Click "Add Size" to get started.
                      </p>
                    </div>
                  )}
                </div>

                {/* Size Options (Alternative format) */}
                <div>
                  <div className="flex items-center justify-between mb-4">
                    <div>
                      <h3 className={cn("text-lg font-medium", t.text.primary)}>
                        Size Options (Text Format)
                      </h3>
                      <p className={cn("text-sm", t.text.secondary)}>
                        Alternative text-based size options (e.g., "Small", "Medium", "Large")
                      </p>
                    </div>
                    <button
                      type="button"
                      onClick={addSizeOption}
                      className={cn(
                        "flex items-center gap-2 px-3 py-2 rounded-lg text-sm",
                        t.button.secondary.base,
                        t.button.secondary.hover
                      )}
                    >
                      <FiPlus className="w-4 h-4" />
                      Add Option
                    </button>
                  </div>

                  <div className="space-y-3">
                    {data.size_options.map((option, index) => (
                      <div key={index} className="flex items-center gap-3">
                        <input
                          type="text"
                          value={option}
                          onChange={(e) => updateSizeOption(index, e.target.value)}
                          className={cn(
                            "flex-1 px-4 py-3 rounded-xl",
                            t.input.base,
                            t.input.focus
                          )}
                          placeholder="Enter size option..."
                        />
                        <button
                          type="button"
                          onClick={() => removeSizeOption(index)}
                          className={cn(
                            "p-3 rounded-xl text-red-500 hover:bg-red-500/10"
                          )}
                        >
                          <FiTrash2 className="w-4 h-4" />
                        </button>
                      </div>
                    ))}
                  </div>

                  {data.size_options.length === 0 && (
                    <div className={cn("text-center py-8", t.glass.frost, "rounded-xl")}>
                      <FiTag className={cn("w-12 h-12 mx-auto mb-3", t.text.muted)} />
                      <p className={cn("text-sm", t.text.secondary)}>
                        No size options configured. This is optional.
                      </p>
                    </div>
                  )}
                </div>
              </div>
            )}

            {/* Documents Tab */}
            {activeTab === 'documents' && (
              <div className="space-y-6">
                {/* File Upload */}
                <div>
                  <label className={cn("block text-sm font-medium mb-4", t.text.primary)}>
                    Upload Documents
                  </label>
                  <div
                    className={cn(
                      "border-2 border-dashed rounded-xl p-8 text-center transition-colors",
                      "border-blue-500/30 hover:border-blue-500/50",
                      t.glass.frost
                    )}
                  >
                    <input
                      type="file"
                      multiple
                      accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif"
                      onChange={(e) => handleFileUpload(e.target.files)}
                      className="hidden"
                      id="file-upload"
                    />
                    <label htmlFor="file-upload" className="cursor-pointer">
                      <FiUpload className={cn("w-12 h-12 mx-auto mb-4", t.text.secondary)} />
                      <p className={cn("text-lg font-medium mb-2", t.text.primary)}>
                        Drop files here or click to upload
                      </p>
                      <p className={cn("text-sm", t.text.secondary)}>
                        Supports PDF, DOC, DOCX, JPG, PNG, GIF (max 10MB each)
                      </p>
                    </label>
                  </div>
                </div>

                {/* Existing Documents */}
                {data.document_urls.length > 0 && (
                  <div>
                    <h3 className={cn("text-lg font-medium mb-4", t.text.primary)}>
                      Existing Documents
                    </h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                      {data.document_urls.map((url, index) => (
                        <div
                          key={index}
                          className={cn(
                            "p-4 rounded-xl border transition-all",
                            t.glass.frost,
                            "hover:shadow-lg"
                          )}
                        >
                          <div className="flex items-start justify-between mb-3">
                            <FiFileText className={cn("w-6 h-6 flex-shrink-0", t.text.secondary)} />
                            <button
                              type="button"
                              onClick={() => removeExistingDocument(url)}
                              className="text-red-500 hover:text-red-700"
                            >
                              <FiTrash2 className="w-4 h-4" />
                            </button>
                          </div>
                          <p className={cn("text-sm font-medium mb-2 truncate", t.text.primary)}>
                            {url.split('/').pop() || 'Document'}
                          </p>
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
                              <FiExternalLink className="w-3 h-3" />
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
                  </div>
                )}

                {/* New Documents Preview */}
                {data.new_documents.length > 0 && (
                  <div>
                    <h3 className={cn("text-lg font-medium mb-4", t.text.primary)}>
                      New Documents (Pending Upload)
                    </h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                      {data.new_documents.map((file, index) => (
                        <div
                          key={index}
                          className={cn(
                            "p-4 rounded-xl border transition-all",
                            "border-blue-500/30 bg-blue-500/5"
                          )}
                        >
                          <div className="flex items-start justify-between mb-3">
                            {file.type.startsWith('image/') ? (
                              <FiImage className="w-6 h-6 flex-shrink-0 text-blue-500" />
                            ) : (
                              <FiFileText className="w-6 h-6 flex-shrink-0 text-blue-500" />
                            )}
                            <button
                              type="button"
                              onClick={() => removeNewDocument(index)}
                              className="text-red-500 hover:text-red-700"
                            >
                              <FiTrash2 className="w-4 h-4" />
                            </button>
                          </div>
                          <p className={cn("text-sm font-medium mb-1 truncate", t.text.primary)}>
                            {file.name}
                          </p>
                          <p className={cn("text-xs", t.text.secondary)}>
                            {(file.size / 1024 / 1024).toFixed(2)} MB
                          </p>
                          {file.type.startsWith('image/') && previewUrls[index] && (
                            <img
                              src={previewUrls[index]}
                              alt="Preview"
                              className="w-full h-20 object-cover rounded-lg mt-3"
                            />
                          )}
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {/* Document Guidelines */}
                <div className={cn("p-4 rounded-xl", t.status.info)}>
                  <div className="flex items-start gap-3">
                    <FiInfo className="w-5 h-5 flex-shrink-0 mt-0.5" />
                    <div>
                      <h4 className="font-medium mb-2">Document Guidelines</h4>
                      <ul className="text-sm space-y-1">
                        <li>• Upload product specifications, clinical studies, or marketing materials</li>
                        <li>• Supported formats: PDF, DOC, DOCX, JPG, PNG, GIF</li>
                        <li>• Maximum file size: 10MB per file</li>
                        <li>• Documents will be available to authorized users</li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            )}

            {/* History Tab */}
            {activeTab === 'history' && (
              <div className="space-y-6">
                <div>
                  <h3 className={cn("text-lg font-medium mb-4", t.text.primary)}>
                    Pricing History
                  </h3>
                  <p className={cn("text-sm mb-6", t.text.secondary)}>
                    Track changes to National ASP, MUE, and other pricing fields over time.
                  </p>

                  {loadingHistory ? (
                    <div className="flex items-center justify-center py-12">
                      <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                    </div>
                  ) : pricingHistory.length > 0 ? (
                    <div className="overflow-x-auto">
                      <table className="min-w-full">
                        <thead className={cn(t.table.header)}>
                          <tr>
                            <th className={cn("px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider", t.table.headerText)}>
                              Date
                            </th>
                            <th className={cn("px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider", t.table.headerText)}>
                              Changed By
                            </th>
                            <th className={cn("px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider", t.table.headerText)}>
                              Field
                            </th>
                            <th className={cn("px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider", t.table.headerText)}>
                              Old Value
                            </th>
                            <th className={cn("px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider", t.table.headerText)}>
                              New Value
                            </th>
                            <th className={cn("px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider", t.table.headerText)}>
                              Change
                            </th>
                            <th className={cn("px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider", t.table.headerText)}>
                              Reason
                            </th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-white/10">
                          {pricingHistory.map((record, index) => (
                            <React.Fragment key={record.id}>
                              {record.changed_fields?.map((field: string, fieldIndex: number) => (
                                <tr key={`${record.id}-${field}`} className={cn(
                                  t.table.rowHover,
                                  index % 2 === 0 ? t.table.evenRow : ''
                                )}>
                                  {fieldIndex === 0 && (
                                    <>
                                      <td rowSpan={record.changed_fields.length} className="px-4 py-3 text-sm">
                                        {new Date(record.effective_date).toLocaleDateString()}
                                      </td>
                                      <td rowSpan={record.changed_fields.length} className="px-4 py-3 text-sm">
                                        {record.changed_by ? (
                                          <div>
                                            <div className={t.text.primary}>{record.changed_by.name}</div>
                                            <div className={cn("text-xs", t.text.secondary)}>{record.changed_by.email}</div>
                                          </div>
                                        ) : (
                                          <span className={t.text.secondary}>System</span>
                                        )}
                                      </td>
                                    </>
                                  )}
                                  <td className="px-4 py-3 text-sm">
                                    <span className={cn("font-medium", t.text.primary)}>
                                      {field === 'national_asp' ? 'National ASP' :
                                       field === 'mue' ? 'MUE' :
                                       field === 'price_per_sq_cm' ? 'Price/cm²' :
                                       field === 'msc_price' ? 'MSC Price' :
                                       field === 'commission_rate' ? 'Commission Rate' :
                                       field}
                                    </span>
                                  </td>
                                  <td className="px-4 py-3 text-sm">
                                    {field === 'national_asp' || field === 'price_per_sq_cm' || field === 'msc_price' ? 
                                      `$${formatPrice(record.previous_values?.[field])}` :
                                     field === 'commission_rate' ? 
                                      `${toNumber(record.previous_values?.[field])}%` :
                                      record.previous_values?.[field] || 'N/A'}
                                  </td>
                                  <td className="px-4 py-3 text-sm">
                                    {field === 'national_asp' || field === 'price_per_sq_cm' || field === 'msc_price' ? 
                                      `$${formatPrice(record[field])}` :
                                     field === 'commission_rate' ? 
                                      `${toNumber(record[field])}%` :
                                      record[field] || 'N/A'}
                                  </td>
                                  <td className="px-4 py-3 text-sm">
                                    {field === 'national_asp' && record.price_change_percentage ? (
                                      <span className={cn(
                                        "px-2 py-1 rounded-full text-xs font-medium",
                                        record.is_price_increase ? "bg-red-500/20 text-red-400" :
                                        record.is_price_decrease ? "bg-green-500/20 text-green-400" :
                                        "bg-gray-500/20 text-gray-400"
                                      )}>
                                        {record.is_price_increase ? '↑' : '↓'} {formatPrice(Math.abs(toNumber(record.price_change_percentage)))}%
                                      </span>
                                    ) : null}
                                  </td>
                                  {fieldIndex === 0 && (
                                    <td rowSpan={record.changed_fields.length} className="px-4 py-3 text-sm">
                                      {record.change_reason || '-'}
                                    </td>
                                  )}
                                </tr>
                              ))}
                            </React.Fragment>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  ) : (
                    <div className={cn("text-center py-12", t.text.secondary)}>
                      <FiCopy className="w-12 h-12 mx-auto mb-4 opacity-50" />
                      <p>No pricing history available for this product.</p>
                    </div>
                  )}
                </div>

                {/* History Info */}
                <div className={cn("p-4 rounded-xl", t.status.info)}>
                  <div className="flex items-start gap-3">
                    <FiInfo className="w-5 h-5 flex-shrink-0 mt-0.5" />
                    <div>
                      <h4 className="font-medium mb-2">About Pricing History</h4>
                      <ul className="text-sm space-y-1">
                        <li>• All changes to pricing fields are automatically tracked</li>
                        <li>• National ASP changes show percentage increase/decrease</li>
                        <li>• System changes include migrations and automated updates</li>
                        <li>• History is maintained for audit and compliance purposes</li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            )}

            {/* Form Actions */}
            <div className="flex items-center justify-end gap-4 pt-6 border-t border-white/10">
              <button
                type="button"
                onClick={() => router.get('/products')}
                className={cn(
                  "px-6 py-3 rounded-xl font-medium",
                  t.button.ghost.base,
                  t.button.ghost.hover
                )}
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={processing}
                className={cn(
                  "flex items-center gap-2 px-6 py-3 rounded-xl font-medium transition-all",
                  processing
                    ? "bg-gray-400 cursor-not-allowed"
                    : cn(t.button.primary.base, t.button.primary.hover)
                )}
              >
                {processing ? (
                  <>
                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white" />
                    Saving...
                  </>
                ) : (
                  <>
                    <FiSave className="w-4 h-4" />
                    Save Product
                  </>
                )}
              </button>
            </div>
          </form>
        </div>
      </div>
    </MainLayout>
  );
}
