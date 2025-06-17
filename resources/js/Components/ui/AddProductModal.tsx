import React, { useState, useEffect } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Modal } from '@/Components/Modal';
import TextInput from '@/Components/Form/TextInput';
import SelectInput from '@/Components/Form/SelectInput';
import DateInput from '@/Components/Form/DateInput';
import TextAreaInput from '@/Components/Form/TextAreaInput';
import LoadingButton from '@/Components/Button/LoadingButton';
import { X, Search } from 'lucide-react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface Product {
  id: number;
  name: string;
  sku: string;
  manufacturer: string;
  category: string;
  q_code?: string;
}

interface AddProductModalProps {
  isOpen: boolean;
  onClose: () => void;
  providerId: number;
}

export default function AddProductModal({ isOpen, onClose, providerId }: AddProductModalProps) {
  // Theme setup with fallback
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // If not in ThemeProvider, use dark theme
  }

  const [allProducts, setAllProducts] = useState<Product[]>([]);
  const [filteredProducts, setFilteredProducts] = useState<Product[]>([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [loading, setLoading] = useState(false);
  const [initialLoadComplete, setInitialLoadComplete] = useState(false);

  const { data, setData, post, processing, errors, reset } = useForm({
    product_id: '',
    onboarding_status: 'active',
    expiration_date: '',
    notes: '',
  });

  // Load all products when modal opens
  useEffect(() => {
    if (isOpen && !initialLoadComplete) {
      loadAllProducts();
    }
  }, [isOpen, initialLoadComplete]);

  // Filter products based on search term
  useEffect(() => {
    if (searchTerm.length === 0) {
      setFilteredProducts(allProducts);
    } else {
      const filtered = allProducts.filter(product =>
        product.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        product.sku.toLowerCase().includes(searchTerm.toLowerCase()) ||
        product.manufacturer.toLowerCase().includes(searchTerm.toLowerCase()) ||
        (product.q_code && product.q_code.toLowerCase().includes(searchTerm.toLowerCase()))
      );
      setFilteredProducts(filtered);
    }
  }, [searchTerm, allProducts]);

  const loadAllProducts = async () => {
    setLoading(true);
    try {
      const response = await fetch('/api/products/search');
      const data = await response.json();
      const products = data.products || [];
      setAllProducts(products);
      setFilteredProducts(products);
      setInitialLoadComplete(true);
    } catch (error) {
      console.error('Error fetching products:', error);
      setAllProducts([]);
      setFilteredProducts([]);
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    post(`/api/providers/${providerId}/products`, {
      onSuccess: () => {
        reset();
        setSearchTerm('');
        onClose();
        // Instead of router.reload(), we'll let the parent component handle the refresh
        // or use a callback to notify the parent that a product was added
      },
      onError: (errors) => {
        console.error('Error adding product:', errors);
      },
    });
  };

  const handleClose = () => {
    reset();
    setSearchTerm('');
    setInitialLoadComplete(false);
    onClose();
  };

  return (
    <Modal show={isOpen} onClose={handleClose} maxWidth="md">
      <div className={cn(t.modal.body, "relative overflow-visible")}>
        <div className="flex items-center justify-between mb-4">
          <h2 className={cn("text-lg font-semibold", t.text.primary)}>Add Product Onboarding</h2>
          <button
            onClick={handleClose}
            className={cn(
              "transition-colors rounded-lg p-1",
              theme === 'dark'
                ? 'text-white/60 hover:text-white/90 hover:bg-white/10'
                : 'text-gray-400 hover:text-gray-600 hover:bg-gray-100'
            )}
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Product Search */}
          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Search Products
            </label>
            <div className="relative">
              <Search className={cn("absolute left-3 top-3 w-4 h-4", t.text.muted)} />
              <input
                type="text"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                placeholder="Search by name, SKU, or manufacturer..."
                className={cn("pl-10 pr-4", t.input.base, t.input.focus)}
              />
            </div>
            {loading && (
              <p className={cn("text-sm mt-1", t.text.muted)}>Loading products...</p>
            )}
            {!loading && searchTerm.length > 0 && filteredProducts.length === 0 && (
              <p className={cn("text-sm mt-1", t.text.muted)}>No products found matching your search</p>
            )}
          </div>

          {/* Product Selection */}
          <div className="relative">
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Select Product
            </label>
            <div className="relative z-50">
              <SelectInput
                name="product_id"
                value={data.product_id}
                onChange={(e) => setData('product_id', e.target.value)}
                error={errors.product_id}
                required
                className="relative"
                options={[
                  { value: '', label: 'Choose a product...' },
                  ...filteredProducts.map((product) => ({
                    value: String(product.id),
                    label: `${product.name} - ${product.sku} (${product.manufacturer})`,
                  })),
                ]}
              />
            </div>
          </div>

          <div>
            <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
              Onboarding Status
            </label>
            <SelectInput
              name="onboarding_status"
              value={data.onboarding_status}
              onChange={(e) => setData('onboarding_status', e.target.value)}
              error={errors.onboarding_status}
              required
              options={[
                { value: 'active', label: 'Active' },
                { value: 'pending', label: 'Pending' },
                { value: 'expired', label: 'Expired' },
                { value: 'suspended', label: 'Suspended' },
              ]}
            />
          </div>

          <DateInput
            label="Expiration Date (Optional)"
            value={data.expiration_date}
            onChange={(value) => setData('expiration_date', value)}
            error={errors.expiration_date}
          />

          <TextAreaInput
            label="Notes (Optional)"
            value={data.notes}
            onChange={(value) => setData('notes', value)}
            error={errors.notes}
            rows={3}
            placeholder="Any additional notes about this product onboarding..."
          />

          <div className={cn("flex justify-end gap-3 pt-4")}>
            <button
              type="button"
              onClick={handleClose}
              className={cn(
                "px-4 py-2 rounded-lg font-medium transition-all",
                theme === 'dark'
                  ? 'bg-white/10 text-white hover:bg-white/20 border border-white/20'
                  : 'bg-gray-100 text-gray-800 hover:bg-gray-200 border border-gray-300'
              )}
            >
              Cancel
            </button>
            <LoadingButton
              type="submit"
              loading={processing}
              className={cn(
                "px-4 py-2 rounded-lg font-medium transition-all",
                theme === 'dark'
                  ? 'bg-gradient-to-r from-[#1925c3] to-[#c71719] text-white hover:shadow-lg disabled:opacity-50'
                  : 'bg-gradient-to-r from-[#1925c3] to-[#c71719] text-white hover:shadow-lg disabled:opacity-50'
              )}
              disabled={!data.product_id}
            >
              Add Product
            </LoadingButton>
          </div>
        </form>
      </div>
    </Modal>
  );
}
