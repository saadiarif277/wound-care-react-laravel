import React, { useState, useEffect, useMemo, useCallback, useRef } from 'react';
import debounce from 'lodash/debounce';
import { router, useForm } from '@inertiajs/react';
import { Modal } from '@/Components/Modal';
import SelectInput from '@/Components/Form/SelectInput';
import DateInput from '@/Components/Form/DateInput';
import TextAreaInput from '@/Components/Form/TextAreaInput';
import LoadingButton from '@/Components/Button/LoadingButton';
import { X, Search, CheckCircle2, AlertTriangle, Plus, Minus, ChevronDown } from 'lucide-react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import api from '@/lib/api';

interface Product {
  id: number;
  name: string;
  sku: string;
  manufacturer: string;
  category: string;
  q_code?: string;
  onboarding_status?: string;
  onboarded_at?: string;
  expiration_date?: string | null;
  pivot?: {
    onboarding_status?: string;
    expiration_date?: string | null;
  };
}

interface AddProductModalProps {
  isOpen: boolean;
  onClose: () => void;
  providerId: number;
}

export default function AddProductModal({ isOpen, onClose, providerId }: AddProductModalProps) {
  // Theme management with fallback
  let theme: 'dark' | 'light' = 'dark';
  try {
    theme = useTheme()?.theme || 'dark';
  } catch (e) {
    console.warn('Theme context not available, using dark theme');
  }
  const t = themes[theme];

  // State management
  const [products, setProducts] = useState<Product[]>([]);              // Display list (scored + annotated)
  const [rawResults, setRawResults] = useState<Product[]>([]);          // Raw server results
  const [allProducts, setAllProducts] = useState<Product[]>([]);        // Popular / fallback
  const [searchTerm, setSearchTerm] = useState('');
  const [loading, setLoading] = useState(false);
  const [dropdownOpen, setDropdownOpen] = useState(false);
  const [selectedProducts, setSelectedProducts] = useState<Product[]>([]);
  const [existingOnboardedMap, setExistingOnboardedMap] = useState<Record<number, Product>>({});
  const [fetchingExisting, setFetchingExisting] = useState(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [keyboardIndex, setKeyboardIndex] = useState(-1);
  const searchCache = useRef<Record<string, Product[]>>({});
  const abortRef = useRef<AbortController | null>(null);
  
  // Refs for focus management
  const searchInputRef = useRef<HTMLInputElement>(null);
  const dropdownRef = useRef<HTMLDivElement>(null);

  // Form data for shared metadata
  const { data, setData, post, processing, errors, reset } = useForm({
    product_ids: [] as number[],
    onboarding_status: 'active',
    expiration_date: '',
    notes: '',
  });

  // Check if a product is already onboarded
  const isAlreadyOnboarded = useCallback(
    (productId: number) => {
      return !!existingOnboardedMap[productId];
    },
    [existingOnboardedMap]
  );

  // Fetch existing onboarded products when modal opens
  useEffect(() => {
    if (!isOpen) return;

    const fetchExistingProducts = async () => {
      setFetchingExisting(true);
      setErrorMsg(null);
      
      try {
        // Fetch provider's current products
        const response = await api.get(`/api/providers/${providerId}/products?limit=500`);
        
        if (response.data?.products || response.products) {
          const products = response.data?.products || response.products || [];
          const map: Record<number, Product> = {};
          
          products.forEach((p: any) => {
            // Only consider 'active' products as already onboarded
            if (p.pivot?.onboarding_status === 'active' || p.onboarding_status === 'active') {
              map[p.id] = {
                ...p,
                onboarding_status: p.pivot?.onboarding_status || p.onboarding_status || 'active',
                expiration_date: p.pivot?.expiration_date || p.expiration_date || null
              };
            }
          });
          
          setExistingOnboardedMap(map);
          console.log(`Loaded ${Object.keys(map).length} active onboarded products for provider ${providerId}`);
        }
      } catch (error) {
        console.error('Error fetching provider products:', error);
        // Don't show error to user - just continue without the check
      } finally {
        setFetchingExisting(false);
      }
    };

    fetchExistingProducts();
  }, [isOpen, providerId]);

  // --- Improved Search Logic ---
  const scoreProduct = useCallback((p: Product, term: string) => {
    const t = term.toLowerCase();
    const fields = [p.name, p.sku, p.q_code || '', p.manufacturer, p.category].map(v => (v || '').toLowerCase());
    let score = 0;
    fields.forEach((f, idx) => {
      if (!f) return;
      if (f === t) score += 120 - idx * 2;               // exact
      else if (f.startsWith(t)) score += 70 - idx * 2;    // prefix
      else if (f.includes(t)) score += 40 - idx;          // substring
      // token partials
      t.split(/\s|[-_]/).filter(tok => tok.length > 1).forEach(tok => {
        if (f.includes(tok)) score += 8;
      });
    });
    return score;
  }, []);

  const highlight = useCallback((text: string, term: string) => {
    if (!term || term.length < 2) return text;
    try {
      const esc = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
      const re = new RegExp(`(${esc})`, 'ig');
      return text.split(re).map((chunk, i) =>
        re.test(chunk)
          ? <mark key={i} className="bg-yellow-200 dark:bg-yellow-600/40 px-0.5 rounded">{chunk}</mark>
          : chunk
      );
    } catch {
      return text;
    }
  }, []);

  const fetchServerProducts = useCallback(async (term: string) => {
    if (term.length < 2) {
      setRawResults([]);
      // fallback to popular list slice
      setProducts(allProducts.slice(0, 50));
      return;
    }
    if (searchCache.current[term]) {
      setRawResults(searchCache.current[term]);
      return;
    }
    if (abortRef.current) abortRef.current.abort();
    const ctrl = new AbortController();
    abortRef.current = ctrl;
    setLoading(true);
    try {
      const res = await fetch(`/api/products/search?q=${encodeURIComponent(term)}&show_all=true&limit=150`, { signal: ctrl.signal });
      if (!res.ok) throw new Error('Search failed');
      const json = await res.json();
      const list: Product[] = Array.isArray(json.products) ? json.products : [];
      searchCache.current[term] = list;
      setRawResults(list);
    } catch (e: any) {
      if (e?.name !== 'AbortError') {
        console.error('Search error', e);
        setRawResults([]);
      }
    } finally {
      setLoading(false);
    }
  }, [allProducts]);

  const debouncedRemote = useMemo(() => debounce((term: string) => {
    if (!isOpen) return;
    fetchServerProducts(term);
  }, 350), [isOpen, fetchServerProducts]);

  // Recompute display list when rawResults or searchTerm changes
  useEffect(() => {
    if (!searchTerm || searchTerm.length < 2) {
      // show slice of popular or raw
      setProducts(prev => {
        if (rawResults.length === 0) return allProducts.slice(0, 50).map(p => ({ ...p, onboarding_status: isAlreadyOnboarded(p.id) ? 'active' : p.onboarding_status }));
        return rawResults.slice(0, 100).map(p => ({ ...p, onboarding_status: isAlreadyOnboarded(p.id) ? 'active' : p.onboarding_status }));
      });
      return;
    }
    const scored = rawResults.map(p => ({ product: p, score: scoreProduct(p, searchTerm) }))
      .sort((a, b) => b.score - a.score)
      .filter((r, idx) => r.score > 0 || idx < 30) // keep some results even with low score
      .map(r => ({ ...r.product, onboarding_status: isAlreadyOnboarded(r.product.id) ? 'active' : r.product.onboarding_status }));
    setProducts(scored);
  }, [rawResults, searchTerm, scoreProduct, isAlreadyOnboarded, allProducts]);

  // Load popular products when modal opens
  const loadPopularProducts = useCallback(async () => {
    if (!isOpen) return;
    
    setLoading(true);
    try {
      const response = await fetch('/api/products/popular?limit=50');
      const json = await response.json();
      const popularProducts: Product[] = Array.isArray(json.products) ? json.products : [];
      
      // Mark already onboarded products
      const processedProducts = popularProducts.map(p => ({
        ...p,
        onboarding_status: isAlreadyOnboarded(p.id) ? 'active' : p.onboarding_status
      }));
      
      setProducts(processedProducts);
      setAllProducts(processedProducts);
    } catch (error) {
      console.error('Error loading popular products:', error);
      // Try fallback to all products
      try {
        const response = await fetch('/api/products/search?show_all=true&limit=50');
        const json = await response.json();
        const fallbackProducts: Product[] = Array.isArray(json.products) ? json.products : [];
        
        const processedProducts = fallbackProducts.map(p => ({
          ...p,
          onboarding_status: isAlreadyOnboarded(p.id) ? 'active' : p.onboarding_status
        }));
        
        setProducts(processedProducts);
        setAllProducts(processedProducts);
      } catch {
        setProducts([]);
      }
    } finally {
      setLoading(false);
    }
  }, [isOpen, isAlreadyOnboarded]);

  // Effects: trigger remote when term changes
  useEffect(() => {
    if (!isOpen) return;
    if (!searchTerm) {
      loadPopularProducts();
      return;
    }
    debouncedRemote(searchTerm);
    return () => debouncedRemote.cancel();
  }, [searchTerm, isOpen, debouncedRemote, loadPopularProducts]);

  // Handle clicking outside dropdown
  useEffect(() => {
    if (!dropdownOpen) return;
    
    const handleClickOutside = (e: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) {
        setDropdownOpen(false);
        setKeyboardIndex(-1);
      }
    };
    
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [dropdownOpen]);

  // Toggle product selection
  const toggleProductSelection = (product: Product) => {
    if (isAlreadyOnboarded(product.id)) {
      setErrorMsg(`${product.name} is already actively onboarded for this provider.`);
      setTimeout(() => setErrorMsg(null), 3000);
      return;
    }
    
    setErrorMsg(null);
    setSelectedProducts(prev => {
      const isSelected = prev.some(p => p.id === product.id);
      if (isSelected) {
        return prev.filter(p => p.id !== product.id);
      } else {
        return [...prev, product];
      }
    });
  };

  // Remove product from selection
  const removeSelectedProduct = (productId: number) => {
    setSelectedProducts(prev => prev.filter(p => p.id !== productId));
  };

  // Handle keyboard navigation
  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (!dropdownOpen || products.length === 0) return;
    
    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setKeyboardIndex(prev => (prev + 1) % products.length);
        break;
      case 'ArrowUp':
        e.preventDefault();
        setKeyboardIndex(prev => (prev - 1 + products.length) % products.length);
        break;
      case 'Enter':
        e.preventDefault();
        if (keyboardIndex >= 0 && keyboardIndex < products.length) {
          const product = products[keyboardIndex];
          if (!isAlreadyOnboarded(product.id)) {
            toggleProductSelection(product);
          }
        }
        break;
      case 'Escape':
        setDropdownOpen(false);
        setKeyboardIndex(-1);
        break;
    }
  };

  // Handle form submission
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (selectedProducts.length === 0) {
      setErrorMsg('Please select at least one product.');
      return;
    }
    
    setErrorMsg(null);
    
    // Prepare the data
    const productIds = selectedProducts.map(p => p.id);
    setData('product_ids', productIds);
    
    // Try bulk endpoint first
    try {
      const bulkResponse = await fetch(`/admin/providers/${providerId}/products/bulk`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify({
          product_ids: productIds,
          onboarding_status: data.onboarding_status,
          expiration_date: data.expiration_date || null,
          notes: data.notes || ''
        })
      });
      
      if (bulkResponse.ok) {
        // Success - refresh the page
        reset();
        setSelectedProducts([]);
        setSearchTerm('');
        router.reload({ only: ['provider'] });
        onClose();
        return;
      }
    } catch (error) {
      console.log('Bulk endpoint not available, falling back to sequential...');
    }
    
    // Fallback to sequential posts
    const errors: string[] = [];
    
    for (const product of selectedProducts) {
      try {
        await fetch(`/admin/providers/${providerId}/products`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
          },
          body: JSON.stringify({
            product_id: product.id,
            onboarding_status: data.onboarding_status,
            expiration_date: data.expiration_date || null,
            notes: data.notes || ''
          })
        });
      } catch (error) {
        errors.push(`Failed to add ${product.name}`);
      }
    }
    
    if (errors.length > 0) {
      setErrorMsg(errors.join(', '));
    } else {
      // Success - refresh the page
      reset();
      setSelectedProducts([]);
      setSearchTerm('');
      router.reload({ only: ['provider'] });
      onClose();
    }
  };

  // Handle modal close
  const handleClose = () => {
    reset();
    setSelectedProducts([]);
    setSearchTerm('');
    setProducts([]);
    setAllProducts([]);
    setErrorMsg(null);
    setKeyboardIndex(-1);
    setDropdownOpen(false);
    onClose();
  };

  // Get status badge color
  const getStatusBadgeClass = (status: string) => {
    const statusClasses: Record<string, string> = {
      active: theme === 'dark' 
        ? 'bg-green-500/10 text-green-300 border-green-400/30' 
        : 'bg-green-50 text-green-600 border-green-300',
      pending: theme === 'dark'
        ? 'bg-yellow-500/10 text-yellow-300 border-yellow-400/30'
        : 'bg-yellow-50 text-yellow-600 border-yellow-300',
      expired: theme === 'dark'
        ? 'bg-red-500/10 text-red-300 border-red-400/30'
        : 'bg-red-50 text-red-600 border-red-300',
      suspended: theme === 'dark'
        ? 'bg-gray-500/10 text-gray-300 border-gray-400/30'
        : 'bg-gray-50 text-gray-600 border-gray-300'
    };
    
    return statusClasses[status] || statusClasses.active;
  };

  return (
    <Modal show={isOpen} onClose={handleClose} maxWidth="2xl">
      <div 
        className={cn(t.modal.body, "relative flex flex-col w-full")} 
        style={{ maxHeight: '90vh', minHeight: '520px', overflow: 'visible', zIndex: 180 }}
      >
        {/* Header */}
        <div className="flex items-center justify-between mb-4 flex-shrink-0">
          <h2 className={cn("text-lg font-semibold", t.text.primary)}>
            Add Product Onboarding
          </h2>
          <button
            onClick={handleClose}
            className={cn(
              "transition-colors rounded-lg p-1",
              theme === 'dark'
                ? 'text-white/60 hover:text-white/90 hover:bg-white/10'
                : 'text-gray-400 hover:text-gray-600 hover:bg-gray-100'
            )}
            aria-label="Close"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Error/Loading Messages */}
        {(errorMsg || fetchingExisting) && (
          <div className={cn(
            'mb-3 p-3 rounded-md text-sm flex items-start gap-2',
            errorMsg 
              ? (theme === 'dark' 
                  ? 'bg-red-900/30 text-red-300 border border-red-800/50' 
                  : 'bg-red-50 text-red-700 border border-red-200')
              : (theme === 'dark'
                  ? 'bg-blue-900/30 text-blue-300 border border-blue-800/50'
                  : 'bg-blue-50 text-blue-700 border border-blue-200')
          )}>
            <AlertTriangle className="w-4 h-4 mt-0.5 flex-shrink-0" />
            <span>{errorMsg || 'Loading provider\'s existing products...'}</span>
          </div>
        )}

        <form onSubmit={handleSubmit} className="flex flex-col flex-1 overflow-hidden">
          <div className="space-y-4 overflow-y-auto flex-1 px-1" style={{ maxHeight: 'calc(90vh - 250px)' }}>
            
            {/* Product Search */}
            <div ref={dropdownRef} className="relative" style={{ zIndex: 200 }}>
              <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                Search and Select Products (Multi-select)
              </label>
              
              <div className="relative">
                <Search className={cn("absolute left-3 top-3 w-4 h-4", t.text.muted)} />
                <input
                  ref={searchInputRef}
                  type="text"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  onFocus={() => setDropdownOpen(true)}
                  onKeyDown={handleKeyDown}
                  placeholder="Type at least 2 characters to search..."
                  className={cn("pl-10 pr-10", t.input.base, t.input.focus)}
                  aria-expanded={dropdownOpen}
                  aria-haspopup="listbox"
                />
                <button
                  type="button"
                  onClick={() => setDropdownOpen(!dropdownOpen)}
                  className={cn(
                    "absolute right-2 top-2.5 p-1 rounded",
                    theme === 'dark' ? 'hover:bg-white/10' : 'hover:bg-gray-100'
                  )}
                  aria-label="Toggle dropdown"
                >
                  <ChevronDown className={cn("w-4 h-4 transition-transform", dropdownOpen ? 'rotate-180' : '')} />
                </button>
              </div>

              {/* Dropdown */}
              {dropdownOpen && (
                <div className={cn(
                  "absolute w-full mt-1 rounded-lg shadow-2xl overflow-hidden ring-1",
                  theme === 'dark' 
                    ? 'bg-gray-900 border border-white/10 ring-black/40' 
                    : 'bg-white border border-gray-200 ring-black/10'
                )}
                style={{ maxHeight: '340px', zIndex: 210 }}
                role="listbox">
                  {loading ? (
                    <div className="p-4 text-center">
                      <div className="w-5 h-5 border-2 border-current border-t-transparent rounded-full animate-spin mx-auto mb-2" />
                      <p className={cn("text-sm", t.text.muted)}>Searching...</p>
                    </div>
                  ) : products.length === 0 ? (
                    <div className="p-4 text-center">
                      <p className={cn("text-sm", t.text.muted)}>
                        {searchTerm.length >= 2 ? 'No products found' : 'Start typing to search products'}
                      </p>
                    </div>
                  ) : (
                    <div className="overflow-y-auto" style={{ maxHeight: '300px' }}>
                      {products.map((product, index) => {
                        const isOnboarded = isAlreadyOnboarded(product.id);
                        const isSelected = selectedProducts.some(p => p.id === product.id);
                        const isHighlighted = index === keyboardIndex;
                        
                        return (
                          <button
                            key={product.id}
                            type="button"
                            onClick={() => !isOnboarded && toggleProductSelection(product)}
                            disabled={isOnboarded}
                            className={cn(
                              "w-full text-left px-3 py-2 flex items-center justify-between gap-2 transition-colors",
                              isOnboarded && 'opacity-50 cursor-not-allowed',
                              isHighlighted && (theme === 'dark' ? 'bg-white/10' : 'bg-gray-100'),
                              isSelected && !isOnboarded && (theme === 'dark' ? 'bg-blue-900/30' : 'bg-blue-50'),
                              !isOnboarded && !isSelected && (theme === 'dark' ? 'hover:bg-white/5' : 'hover:bg-gray-50')
                            )}
                            role="option"
                            aria-selected={isSelected}
                            aria-disabled={isOnboarded}
                          >
                            <div className="flex-1 min-w-0">
                              <div className={cn("text-sm font-medium truncate", t.text.primary)}>
                                {highlight(product.name, searchTerm)}
                              </div>
                              <div className={cn("text-xs truncate", t.text.muted)}>
                                {highlight(`${product.sku} • ${product.manufacturer} • ${product.category}`, searchTerm)}
                              </div>
                            </div>
                            
                            <div className="flex items-center gap-2 flex-shrink-0">
                              {isOnboarded && (
                                <span className={cn(
                                  'text-[10px] px-2 py-0.5 rounded-full border',
                                  getStatusBadgeClass('active')
                                )}>
                                  Onboarded
                                </span>
                              )}
                              {isSelected && !isOnboarded && (
                                <CheckCircle2 className="w-4 h-4 text-blue-500" />
                              )}
                            </div>
                          </button>
                        );
                      })}
                    </div>
                  )}
                </div>
              )}
            </div>

            {/* Selected Products */}
            {selectedProducts.length > 0 && (
              <div>
                <div className="flex items-center justify-between mb-2">
                  <label className={cn("text-sm font-medium", t.text.secondary)}>
                    Selected Products ({selectedProducts.length})
                  </label>
                  <button
                    type="button"
                    onClick={() => setSelectedProducts([])}
                    className="text-xs text-red-500 hover:text-red-600"
                  >
                    Clear All
                  </button>
                </div>
                
                <div className="space-y-2 max-h-40 overflow-y-auto p-2 rounded-lg border" 
                  style={{
                    borderColor: theme === 'dark' ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)',
                    backgroundColor: theme === 'dark' ? 'rgba(255,255,255,0.02)' : 'rgba(0,0,0,0.02)'
                  }}
                >
                  {selectedProducts.map(product => (
                    <div
                      key={product.id}
                      className={cn(
                        "flex items-center justify-between p-2 rounded-md",
                        theme === 'dark' ? 'bg-white/5' : 'bg-gray-50'
                      )}
                    >
                      <div className="flex-1 min-w-0">
                        <div className={cn("text-sm font-medium truncate", t.text.primary)}>
                          {product.name}
                        </div>
                        <div className={cn("text-xs truncate", t.text.muted)}>
                          {product.sku} • {product.manufacturer}
                        </div>
                      </div>
                      <button
                        type="button"
                        onClick={() => removeSelectedProduct(product.id)}
                        className={cn(
                          "p-1 rounded hover:bg-red-500/20",
                          theme === 'dark' ? 'text-red-400' : 'text-red-600'
                        )}
                        aria-label={`Remove ${product.name}`}
                      >
                        <X className="w-4 h-4" />
                      </button>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Shared Metadata Fields */}
            <div className="space-y-4 pt-4 border-t" style={{
              borderColor: theme === 'dark' ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)'
            }}>
              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                  Onboarding Status (applies to all selected)
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
                label="Expiration Date (Optional - applies to all)"
                value={data.expiration_date}
                onChange={(value) => setData('expiration_date', value)}
                error={errors.expiration_date}
              />

              <TextAreaInput
                label="Notes (Optional - applies to all)"
                value={data.notes}
                onChange={(value) => setData('notes', value)}
                error={errors.notes}
                rows={3}
                placeholder="Any additional notes about these product onboardings..."
              />
            </div>
          </div>

          {/* Footer Actions */}
          <div className={cn(
            "flex justify-between items-center gap-3 pt-4 mt-4 border-t flex-shrink-0",
            theme === 'dark' ? 'border-white/10' : 'border-gray-200'
          )}>
            <div className={cn("text-sm", t.text.muted)}>
              {selectedProducts.length > 0 
                ? `${selectedProducts.length} product${selectedProducts.length > 1 ? 's' : ''} selected`
                : 'No products selected'
              }
            </div>
            
            <div className="flex gap-3">
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
                disabled={selectedProducts.length === 0}
                className={cn(
                  "px-4 py-2 rounded-lg font-medium transition-all",
                  theme === 'dark'
                    ? 'bg-gradient-to-r from-[#1925c3] to-[#c71719] text-white hover:shadow-lg disabled:opacity-50'
                    : 'bg-gradient-to-r from-[#1925c3] to-[#c71719] text-white hover:shadow-lg disabled:opacity-50'
                )}
              >
                Add {selectedProducts.length > 0 ? `${selectedProducts.length} ` : ''}Product{selectedProducts.length !== 1 ? 's' : ''}
              </LoadingButton>
            </div>
          </div>
        </form>
      </div>
    </Modal>
  );
}