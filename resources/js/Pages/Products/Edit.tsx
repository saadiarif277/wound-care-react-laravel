import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import {
  FiArrowLeft,
  FiSave,
  FiPlus,
  FiTrash2,
  FiUpload,
  FiPackage,
  FiAlertTriangle
} from 'react-icons/fi';

interface Product {
  id: number;
  sku: string;
  name: string;
  description: string;
  manufacturer: string;
  category: string;
  price_per_sq_cm: number;
  q_code: string;
  available_sizes: number[];
  graph_type: string;
  image_url: string;
  document_urls: string[];
  commission_rate: number;
  is_active: boolean;
}

interface Props {
  product: Product;
  categories: string[];
  manufacturers: string[];
}

interface FormData {
  sku: string;
  name: string;
  description: string;
  manufacturer: string;
  category: string;
  national_asp: string;
  price_per_sq_cm: string;
  q_code: string;
  available_sizes: string[];
  graph_type: string;
  image_url: string;
  document_urls: string[];
  commission_rate: string;
  is_active: boolean;
}

export default function ProductEdit({ product, categories, manufacturers }: Props) {
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

  const { data, setData, put, delete: destroy, processing, errors } = useForm<FormData>({
    sku: product.sku || '',
    name: product.name || '',
    description: product.description || '',
    manufacturer: product.manufacturer || '',
    category: product.category || '',
    national_asp: product.price_per_sq_cm ? product.price_per_sq_cm.toString() : '',
    price_per_sq_cm: product.price_per_sq_cm ? product.price_per_sq_cm.toString() : '',
    q_code: product.q_code || '',
    available_sizes: product.available_sizes ? product.available_sizes.map(size => size.toString()) : [''],
    graph_type: product.graph_type || '',
    image_url: product.image_url || '',
    document_urls: product.document_urls && product.document_urls.length > 0 ? product.document_urls : [''],
    commission_rate: product.commission_rate ? product.commission_rate.toString() : '',
    is_active: product.is_active,
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    // Filter out empty values and convert to proper types
    const processedData = {
      ...data,
      available_sizes: data.available_sizes.filter(size => size.trim() !== '').map(size => parseFloat(size)),
      document_urls: data.document_urls.filter(url => url.trim() !== ''),
      national_asp: data.national_asp ? parseFloat(data.national_asp) : null,
      price_per_sq_cm: data.price_per_sq_cm ? parseFloat(data.price_per_sq_cm) : null,
      commission_rate: data.commission_rate ? parseFloat(data.commission_rate) : null,
    };

    put(`/products/${product.id}`, processedData as any);
  };

  const handleDelete = () => {
    destroy(`/products/${product.id}`);
  };

  const addSize = () => {
    setData('available_sizes', [...data.available_sizes, '']);
  };

  const removeSize = (index: number) => {
    const newSizes = data.available_sizes.filter((_, i) => i !== index);
    setData('available_sizes', newSizes);
  };

  const updateSize = (index: number, value: string) => {
    const newSizes = [...data.available_sizes];
    newSizes[index] = value;
    setData('available_sizes', newSizes);
  };

  const addDocumentUrl = () => {
    setData('document_urls', [...data.document_urls, '']);
  };

  const removeDocumentUrl = (index: number) => {
    const newUrls = data.document_urls.filter((_, i) => i !== index);
    setData('document_urls', newUrls);
  };

  const updateDocumentUrl = (index: number, value: string) => {
    const newUrls = [...data.document_urls];
    newUrls[index] = value;
    setData('document_urls', newUrls);
  };

  return (
    <MainLayout title={`Edit ${product.name}`}>
      <Head title={`Edit ${product.name}`} />

      <div className="p-6">
        {/* Header */}
        <div className="flex items-center justify-between mb-8">
          <div className="flex items-center gap-4">
            <Link
              href="/products"
              className="flex items-center gap-2 text-gray-600 hover:text-gray-800 transition-colors"
            >
              <FiArrowLeft className="w-4 h-4" />
              Back to Products
            </Link>
            <div className="w-px h-6 bg-gray-300"></div>
            <h1 className="text-3xl font-bold text-gray-900">Edit Product</h1>
          </div>

          <button
            onClick={() => setShowDeleteConfirm(true)}
            className="flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
          >
            <FiTrash2 className="w-4 h-4" />
            Delete Product
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-8">
          {/* Basic Information */}
          <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 className="text-xl font-semibold text-gray-900 mb-6 flex items-center gap-2">
              <FiPackage className="w-5 h-5" />
              Basic Information
            </h2>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  SKU <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  value={data.sku}
                  onChange={(e) => setData('sku', e.target.value)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="e.g., BIO-Q4154"
                />
                {errors.sku && <p className="text-red-500 text-sm mt-1">{errors.sku}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Q-Code
                </label>
                <input
                  type="text"
                  value={data.q_code}
                  onChange={(e) => setData('q_code', e.target.value)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="e.g., 4154"
                />
                {errors.q_code && <p className="text-red-500 text-sm mt-1">{errors.q_code}</p>}
              </div>

              <div className="md:col-span-2">
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Product Name <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  value={data.name}
                  onChange={(e) => setData('name', e.target.value)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="e.g., Biovance"
                />
                {errors.name && <p className="text-red-500 text-sm mt-1">{errors.name}</p>}
              </div>

              <div className="md:col-span-2">
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Description
                </label>
                <textarea
                  value={data.description}
                  onChange={(e) => setData('description', e.target.value)}
                  rows={4}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="Product description..."
                />
                {errors.description && <p className="text-red-500 text-sm mt-1">{errors.description}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Manufacturer
                </label>
                <select
                  value={data.manufacturer}
                  onChange={(e) => setData('manufacturer', e.target.value)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="">Select Manufacturer</option>
                  {manufacturers.map((manufacturer) => (
                    <option key={manufacturer} value={manufacturer}>
                      {manufacturer}
                    </option>
                  ))}
                </select>
                {errors.manufacturer && <p className="text-red-500 text-sm mt-1">{errors.manufacturer}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Category
                </label>
                <select
                  value={data.category}
                  onChange={(e) => setData('category', e.target.value)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="">Select Category</option>
                  {categories.map((category) => (
                    <option key={category} value={category}>
                      {category}
                    </option>
                  ))}
                </select>
                {errors.category && <p className="text-red-500 text-sm mt-1">{errors.category}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Graph Type
                </label>
                <input
                  type="text"
                  value={data.graph_type}
                  onChange={(e) => setData('graph_type', e.target.value)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="e.g., Amniotic Membrane"
                />
                {errors.graph_type && <p className="text-red-500 text-sm mt-1">{errors.graph_type}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Status
                </label>
                <label className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    checked={data.is_active}
                    onChange={(e) => setData('is_active', e.target.checked)}
                    className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  />
                  <span className="text-sm text-gray-700">Active</span>
                </label>
              </div>
            </div>
          </div>

          {/* Pricing Information */}
          <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 className="text-xl font-semibold text-gray-900 mb-6">Pricing Information</h2>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  National ASP ($/cm²)
                </label>
                <input
                  type="number"
                  step="0.01"
                  value={data.national_asp}
                  onChange={(e) => setData('national_asp', e.target.value)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="0.00"
                />
                {errors.national_asp && <p className="text-red-500 text-sm mt-1">{errors.national_asp}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Price per cm²
                </label>
                <input
                  type="number"
                  step="0.01"
                  value={data.price_per_sq_cm}
                  onChange={(e) => setData('price_per_sq_cm', e.target.value)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="0.00"
                />
                {errors.price_per_sq_cm && <p className="text-red-500 text-sm mt-1">{errors.price_per_sq_cm}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Commission Rate (%)
                </label>
                <input
                  type="number"
                  step="0.1"
                  min="0"
                  max="100"
                  value={data.commission_rate}
                  onChange={(e) => setData('commission_rate', e.target.value)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="0.0"
                />
                {errors.commission_rate && <p className="text-red-500 text-sm mt-1">{errors.commission_rate}</p>}
              </div>
            </div>
          </div>

          {/* Available Sizes */}
          <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-xl font-semibold text-gray-900">Available Sizes (cm²)</h2>
              <button
                type="button"
                onClick={addSize}
                className="flex items-center gap-2 px-3 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
              >
                <FiPlus className="w-4 h-4" />
                Add Size
              </button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              {data.available_sizes.map((size, index) => (
                <div key={index} className="flex gap-2">
                  <input
                    type="number"
                    step="0.01"
                    value={size}
                    onChange={(e) => updateSize(index, e.target.value)}
                    className="flex-1 border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Size in cm²"
                  />
                  {data.available_sizes.length > 1 && (
                    <button
                      type="button"
                      onClick={() => removeSize(index)}
                      className="px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                    >
                      <FiTrash2 className="w-4 h-4" />
                    </button>
                  )}
                </div>
              ))}
            </div>
            {errors.available_sizes && <p className="text-red-500 text-sm mt-2">{errors.available_sizes}</p>}
          </div>

          {/* Media & Documents */}
          <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 className="text-xl font-semibold text-gray-900 mb-6">Media & Documents</h2>

            <div className="space-y-6">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Product Image URL
                </label>
                <input
                  type="url"
                  value={data.image_url}
                  onChange={(e) => setData('image_url', e.target.value)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="https://example.com/image.jpg"
                />
                {errors.image_url && <p className="text-red-500 text-sm mt-1">{errors.image_url}</p>}
              </div>

              <div>
                <div className="flex items-center justify-between mb-4">
                  <label className="block text-sm font-medium text-gray-700">
                    Document URLs
                  </label>
                  <button
                    type="button"
                    onClick={addDocumentUrl}
                    className="flex items-center gap-2 px-3 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                  >
                    <FiPlus className="w-4 h-4" />
                    Add Document
                  </button>
                </div>

                <div className="space-y-3">
                  {data.document_urls.map((url, index) => (
                    <div key={index} className="flex gap-2">
                      <input
                        type="url"
                        value={url}
                        onChange={(e) => updateDocumentUrl(index, e.target.value)}
                        className="flex-1 border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="https://example.com/document.pdf"
                      />
                      {data.document_urls.length > 1 && (
                        <button
                          type="button"
                          onClick={() => removeDocumentUrl(index)}
                          className="px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                        >
                          <FiTrash2 className="w-4 h-4" />
                        </button>
                      )}
                    </div>
                  ))}
                </div>
                {errors.document_urls && <p className="text-red-500 text-sm mt-2">{errors.document_urls}</p>}
              </div>
            </div>
          </div>

          {/* Submit Buttons */}
          <div className="flex items-center justify-end gap-4 pt-6 border-t border-gray-200">
            <Link
              href="/products"
              className="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
            >
              Cancel
            </Link>
            <button
              type="submit"
              disabled={processing}
              className="flex items-center gap-2 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              <FiSave className="w-4 h-4" />
              {processing ? 'Updating...' : 'Update Product'}
            </button>
          </div>
        </form>

        {/* Delete Confirmation Modal */}
        {showDeleteConfirm && (
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg p-6 max-w-md w-full mx-4">
              <div className="flex items-center gap-3 mb-4">
                <FiAlertTriangle className="w-6 h-6 text-red-600" />
                <h3 className="text-lg font-semibold text-gray-900">Delete Product</h3>
              </div>
              <p className="text-gray-600 mb-6">
                Are you sure you want to delete "{product.name}"? This action cannot be undone.
              </p>
              <div className="flex gap-3 justify-end">
                <button
                  onClick={() => setShowDeleteConfirm(false)}
                  className="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
                >
                  Cancel
                </button>
                <button
                  onClick={handleDelete}
                  disabled={processing}
                  className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 transition-colors"
                >
                  {processing ? 'Deleting...' : 'Delete'}
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </MainLayout>
  );
}
