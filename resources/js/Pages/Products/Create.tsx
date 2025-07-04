import React, { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import {
  FiSave,
  FiX,
  FiUpload,
  FiFileText,
  FiImage,
  FiTrash2,
  FiPackage,
  FiDollarSign,
  FiTag,
  FiInfo,
  FiPlus,
  FiMinus,
  FiArrowLeft
} from 'react-icons/fi';

interface Props {
  categories: string[];
  manufacturers: string[];
}

export default function ProductCreate({ categories, manufacturers }: Props) {
  const { theme } = useTheme();
  const t = themes[theme];

  const [activeTab, setActiveTab] = useState<'basic' | 'pricing' | 'sizes' | 'documents'>('basic');
  const [uploadingFiles, setUploadingFiles] = useState(false);
  const [previewUrls, setPreviewUrls] = useState<string[]>([]);

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

  const { data, setData, post, processing, errors, reset } = useForm({
    sku: '',
    q_code: '',
    name: '',
    description: '',
    manufacturer: '',
    category: '',
    price_per_sq_cm: 0,
    national_asp: 0,
    commission_rate: 0,
    available_sizes: [] as number[],
    size_options: [] as string[],
    size_unit: 'cm',
    mue: 0,
    graph_type: '',
    is_active: true,
    image_url: '',
    documents: [] as File[]
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    // Create FormData for file uploads
    const formData = new FormData();

    // Add all form fields
    Object.entries(data).forEach(([key, value]) => {
      if (key === 'documents') {
        // Handle file uploads
        (value as File[]).forEach((file, index) => {
          formData.append(`documents[${index}]`, file);
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

    router.post('/products', formData, {
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
    setData('documents', [...data.documents, ...newFiles]);

    // Create preview URLs
    const newPreviewUrls = newFiles.map(file => URL.createObjectURL(file));
    setPreviewUrls(prev => [...prev, ...newPreviewUrls]);
  };

  const removeDocument = (index: number) => {
    const newDocuments = data.documents.filter((_, i) => i !== index);
    setData('documents', newDocuments);

    // Clean up preview URLs
    URL.revokeObjectURL(previewUrls[index]);
    setPreviewUrls(prev => prev.filter((_, i) => i !== index));
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

  const tabs = [
    { id: 'basic', label: 'Basic Info', icon: FiPackage },
    { id: 'pricing', label: 'Pricing', icon: FiDollarSign },
    { id: 'sizes', label: 'Sizes', icon: FiTag },
    { id: 'documents', label: 'Documents', icon: FiFileText }
  ];

  return (
    <MainLayout>
      <Head title="Create New Product" />

      <div className="space-y-6">
        {/* Header */}
        <div className={cn("p-6 rounded-2xl", t.glass.card)}>
          <div className="flex items-center justify-between">
            <div>
              <div className="flex items-center gap-3 mb-2">
                <button
                  onClick={() => router.get('/products')}
                  className={cn(
                    "p-2 rounded-xl transition-all",
                    t.button.ghost.base,
                    t.button.ghost.hover
                  )}
                >
                  <FiArrowLeft className="w-5 h-5" />
                </button>
                <h1 className={cn("text-3xl font-bold flex items-center gap-3", t.text.primary)}>
                  <FiPlus className="w-8 h-8 text-blue-500" />
                  Create New Product
                </h1>
              </div>
              <p className={cn("text-sm", t.text.secondary)}>
                Add a new wound care product to your catalog with pricing and documentation
              </p>
            </div>
            <div className="flex items-center gap-3">
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

          {/* Quick Stats */}
          <div className={cn("mt-6 p-4 rounded-xl", t.glass.frost)}>
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div className="text-center">
                <div className={cn("text-lg font-bold", t.text.primary)}>
                  {formatPrice(data.price_per_sq_cm)}
                </div>
                <div className={cn("text-xs", t.text.secondary)}>Price per cm²</div>
              </div>
                              <div className="text-center">
                  <div className={cn("text-lg font-bold text-green-500")}>
                    {toNumber(data.commission_rate)}%
                  </div>
                  <div className={cn("text-xs", t.text.secondary)}>Commission</div>
                </div>
              <div className="text-center">
                <div className={cn("text-lg font-bold", t.text.primary)}>
                  {data.available_sizes.length + data.size_options.length}
                </div>
                <div className={cn("text-xs", t.text.secondary)}>Size Options</div>
              </div>
              <div className="text-center">
                <div className={cn("text-lg font-bold", t.text.primary)}>
                  {data.documents.length}
                </div>
                <div className={cn("text-xs", t.text.secondary)}>Documents</div>
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

                {/* Documents Preview */}
                {data.documents.length > 0 && (
                  <div>
                    <h3 className={cn("text-lg font-medium mb-4", t.text.primary)}>
                      Documents to Upload ({data.documents.length})
                    </h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                      {data.documents.map((file, index) => (
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
                              onClick={() => removeDocument(index)}
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
                    Creating...
                  </>
                ) : (
                  <>
                    <FiSave className="w-4 h-4" />
                    Create Product
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
