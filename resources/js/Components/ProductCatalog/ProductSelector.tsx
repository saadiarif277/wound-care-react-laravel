import React, { useState, useEffect } from 'react';
import {
  Search,
  Filter,
  Plus,
  Minus,
  X,
  ShoppingCart,
  Tag,
  Building,
  Info,
  ShoppingCartIcon,
  MinusIcon,
  PlusIcon,
  TagIcon,
  Sparkles,
  TrendingUp,
  AlertCircle,
  CheckCircle,
  Clock,
  DollarSign
} from 'lucide-react';

interface Product {
  id: number;
  name: string;
  sku: string;
  q_code: string;
  manufacturer: string;
  category: string;
  description: string;
  price_per_sq_cm: number;
  msc_price: number;
  available_sizes: number[];
  image_url?: string;
  commission_rate: number;
}

interface SelectedProduct {
  product_id: number;
  quantity: number;
  size?: string;
  product?: Product;
}

interface AIRecommendation {
  product_id: number;
  product_name: string;
  q_code: string;
  manufacturer: string;
  category: string;
  rank: number;
  confidence_score: number;
  reasoning: string;
  suggested_size?: number;
  estimated_cost: {
    msc_price?: number; // Optional for office managers
    national_asp: number;
    savings?: number; // Optional for office managers
    savings_percentage?: number; // Optional for office managers
  };
  key_benefits: string[];
  clinical_evidence?: string;
  contraindications: string[];
  product_details: {
    msc_price?: number; // Optional for office managers
    national_asp: number;
    available_sizes: number[];
    image_url?: string;
    document_urls: string[];
  };
}

interface Props {
  selectedProducts: SelectedProduct[];
  onProductsChange: (products: SelectedProduct[]) => void;
  showCart?: boolean;
  recommendationContext?: string; // For wound type based recommendations
  productRequestId?: number; // For AI recommendations
  userRole?: string; // For role-based pricing display
  className?: string;
  title?: string;
  description?: string;
}

const ProductSelector: React.FC<Props> = ({
  selectedProducts,
  onProductsChange,
  showCart = true,
  recommendationContext,
  productRequestId,
  userRole = 'provider',
  className = '',
  title = 'Product Catalog Selection',
  description = 'Choose products from our comprehensive catalog'
}) => {
  const [products, setProducts] = useState<Product[]>([]);
  const [filteredProducts, setFilteredProducts] = useState<Product[]>([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('');
  const [selectedManufacturer, setSelectedManufacturer] = useState('');
  const [loading, setLoading] = useState(true);
  const [categories, setCategories] = useState<string[]>([]);
  const [manufacturers, setManufacturers] = useState<string[]>([]);
  const [showFilters, setShowFilters] = useState(false);

  // AI Recommendations state
  const [aiRecommendations, setAiRecommendations] = useState<AIRecommendation[]>([]);
  const [loadingRecommendations, setLoadingRecommendations] = useState(false);
  const [recommendationError, setRecommendationError] = useState<string | null>(null);
  const [showAIRecommendations, setShowAIRecommendations] = useState(true);

  // Fetch products and catalog data
  useEffect(() => {
    fetchProducts();
  }, []);

  // Fetch AI recommendations when productRequestId is available
  useEffect(() => {
    if (productRequestId) {
      fetchAIRecommendations();
    }
  }, [productRequestId]);

  // Filter products based on search and filters
  useEffect(() => {
    let filtered = products;

    if (searchTerm) {
      filtered = filtered.filter(product =>
        product.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        product.q_code.toLowerCase().includes(searchTerm.toLowerCase()) ||
        product.manufacturer.toLowerCase().includes(searchTerm.toLowerCase()) ||
        product.sku.toLowerCase().includes(searchTerm.toLowerCase())
      );
    }

    if (selectedCategory) {
      filtered = filtered.filter(product => product.category === selectedCategory);
    }

    if (selectedManufacturer) {
      filtered = filtered.filter(product => product.manufacturer === selectedManufacturer);
    }

    setFilteredProducts(filtered);
  }, [products, searchTerm, selectedCategory, selectedManufacturer]);

  const fetchProducts = async () => {
    try {
      setLoading(true);
      const response = await fetch('/api/products/search');
      const data = await response.json();

      setProducts(data.products || []);
      setCategories(data.categories || []);
      setManufacturers(data.manufacturers || []);
    } catch (error) {
      console.error('Failed to fetch products:', error);
    } finally {
      setLoading(false);
    }
  };

  const fetchAIRecommendations = async () => {
    if (!productRequestId) return;

    try {
      setLoadingRecommendations(true);
      setRecommendationError(null);

      const response = await fetch(`/api/product-requests/${productRequestId}/recommendations`);
      const data = await response.json();

      if (data.success) {
        setAiRecommendations(data.recommendations || []);
      } else {
        setRecommendationError(data.message || 'Failed to load recommendations');
        // Still show basic recommendations as fallback
        setShowAIRecommendations(false);
      }
    } catch (error) {
      console.error('Failed to fetch AI recommendations:', error);
      setRecommendationError('Unable to load AI recommendations');
      setShowAIRecommendations(false);
    } finally {
      setLoadingRecommendations(false);
    }
  };

  const addProductToSelection = (product: Product, quantity: number = 1, size?: string) => {
    const existingIndex = selectedProducts.findIndex(
      p => p.product_id === product.id && p.size === size
    );

    let updatedProducts = [...selectedProducts];

    if (existingIndex >= 0) {
      updatedProducts[existingIndex].quantity += quantity;
    } else {
      updatedProducts.push({
        product_id: product.id,
        quantity,
        size,
        product
      });
    }

    onProductsChange(updatedProducts);
  };

  const addRecommendationToSelection = (recommendation: AIRecommendation, quantity: number = 1) => {
    // Convert AI recommendation to Product for selection
    const product: Product = {
      id: recommendation.product_id,
      name: recommendation.product_name,
      sku: '', // Not available from AI recommendation
      q_code: recommendation.q_code,
      manufacturer: recommendation.manufacturer,
      category: recommendation.category,
      description: recommendation.reasoning, // Use reasoning as description
      price_per_sq_cm: recommendation.product_details.msc_price ?? recommendation.estimated_cost.national_asp,
      msc_price: recommendation.product_details.msc_price ?? recommendation.estimated_cost.national_asp,
      available_sizes: recommendation.product_details.available_sizes,
      image_url: recommendation.product_details.image_url,
      commission_rate: 0 // Not available from AI recommendation
    };

    const suggestedSize = recommendation.suggested_size?.toString();
    addProductToSelection(product, quantity, suggestedSize);
  };

  const updateProductQuantity = (productId: number, size: string | undefined, newQuantity: number) => {
    if (newQuantity <= 0) {
      removeProduct(productId, size);
      return;
    }

    const updatedProducts = selectedProducts.map(p =>
      p.product_id === productId && p.size === size
        ? { ...p, quantity: newQuantity }
        : p
    );

    onProductsChange(updatedProducts);
  };

  const removeProduct = (productId: number, size: string | undefined) => {
    const updatedProducts = selectedProducts.filter(
      p => !(p.product_id === productId && p.size === size)
    );

    onProductsChange(updatedProducts);
  };

  const calculateTotal = () => {
    return selectedProducts.reduce((total, item) => {
      const product = item.product || products.find(p => p.id === item.product_id);
      if (!product) return total;

      const pricePerUnit = item.size
        ? product.msc_price * parseFloat(item.size)
        : product.msc_price;

      return total + (pricePerUnit * item.quantity);
    }, 0);
  };

  const getRecommendedProducts = () => {
    if (!recommendationContext) return [];

    // Basic recommendation logic based on context (e.g., wound type)
    const recommendations = {
      'DFU': ['Biologic', 'SkinSubstitute'],
      'VLU': ['SkinSubstitute', 'Biologic'],
      'PU': ['SkinSubstitute'],
      'TW': ['Biologic'],
      'AU': ['SkinSubstitute', 'Biologic'],
      'OTHER': ['SkinSubstitute', 'Biologic']
    };

    const recommendedCategories = recommendations[recommendationContext as keyof typeof recommendations] || [];
    return products.filter(p => recommendedCategories.includes(p.category));
  };

  const formatPrice = (price: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(price);
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span className="ml-2 text-gray-600">Loading product catalog...</span>
      </div>
    );
  }

  return (
    <div className={`space-y-6 ${className}`}>
      {/* Header with search and filters */}
      <div className="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-4">
        <div className="flex items-center justify-between mb-4">
          <div>
            <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
            <p className="text-sm text-gray-600">
              {description} {recommendationContext && `(Optimized for ${recommendationContext})`}
            </p>
          </div>
          <div className="flex items-center space-x-2">
            <span className="text-sm text-gray-500">
              {filteredProducts.length} products available
            </span>
            <button
              onClick={() => setShowFilters(!showFilters)}
              className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
            >
              <Filter className="w-4 h-4 mr-2" />
              Filters
            </button>
          </div>
        </div>

        {/* Search */}
        <div className="relative">
          <Search className="w-5 h-5 absolute left-3 top-3 text-gray-400" />
          <input
            type="text"
            placeholder="Search products by name, Q-code, manufacturer, or SKU..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        {/* Filters */}
        {showFilters && (
          <div className="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Category</label>
              <select
                value={selectedCategory}
                onChange={(e) => setSelectedCategory(e.target.value)}
                className="w-full border border-gray-300 rounded-md py-2 px-3 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">All Categories</option>
                {categories.map(category => (
                  <option key={category} value={category}>{category}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Manufacturer</label>
              <select
                value={selectedManufacturer}
                onChange={(e) => setSelectedManufacturer(e.target.value)}
                className="w-full border border-gray-300 rounded-md py-2 px-3 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">All Manufacturers</option>
                {manufacturers.map(manufacturer => (
                  <option key={manufacturer} value={manufacturer}>{manufacturer}</option>
                ))}
              </select>
            </div>
          </div>
        )}
      </div>

      {/* AI-Powered Recommendations Section */}
      {productRequestId && showAIRecommendations && (
        <div className="bg-gradient-to-r from-blue-50 to-purple-50 rounded-lg p-6">
          <div className="flex items-center justify-between mb-4">
            <h4 className="text-lg font-semibold text-gray-900 flex items-center">
              <Sparkles className="w-5 h-5 mr-2 text-purple-600" />
              AI-Powered Clinical Recommendations
            </h4>
            {loadingRecommendations && (
              <div className="flex items-center text-sm text-gray-600">
                <Clock className="w-4 h-4 mr-1 animate-spin" />
                Analyzing...
              </div>
            )}
          </div>

          {recommendationError && (
            <div className="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md flex items-center">
              <AlertCircle className="w-4 h-4 mr-2 text-yellow-600" />
              <span className="text-sm text-yellow-800">{recommendationError}</span>
            </div>
          )}

          {!loadingRecommendations && aiRecommendations.length > 0 && (
            <div className="space-y-4">
              <p className="text-sm text-gray-600 mb-4">
                Based on clinical analysis of wound characteristics, patient factors, and evidence-based protocols.
              </p>
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {aiRecommendations.slice(0, 4).map((recommendation, index) => (
                  <AIRecommendationCard
                    key={`ai-rec-${recommendation.product_id}-${index}`}
                    recommendation={recommendation}
                    onAdd={addRecommendationToSelection}
                    rank={index + 1}
                    userRole={userRole}
                  />
                ))}
              </div>
            </div>
          )}

          {!loadingRecommendations && aiRecommendations.length === 0 && !recommendationError && (
            <div className="text-center py-6">
              <Info className="w-8 h-8 mx-auto text-gray-400 mb-2" />
              <p className="text-gray-600">No AI recommendations available for this request.</p>
            </div>
          )}
        </div>
      )}

      {/* Basic Recommended Products Section (Fallback) */}
      {recommendationContext && !productRequestId && getRecommendedProducts().length > 0 && (
        <div className="bg-green-50 rounded-lg p-4">
          <h4 className="text-md font-semibold text-green-900 mb-3 flex items-center">
            <Info className="w-5 h-5 mr-2" />
            Recommended for {recommendationContext}
          </h4>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {getRecommendedProducts().slice(0, 6).map(product => (
              <ProductCard
                key={`rec-${product.id}`}
                product={product}
                onAdd={addProductToSelection}
                isRecommended={true}
                userRole={userRole}
              />
            ))}
          </div>
        </div>
      )}

      <div className={`grid gap-6 ${showCart ? 'grid-cols-1 lg:grid-cols-3' : 'grid-cols-1'}`}>
        {/* Product Grid */}
        <div className={showCart ? 'lg:col-span-2' : 'col-span-1'}>
          <div className="space-y-4">
            {filteredProducts.length === 0 ? (
              <div className="text-center py-12 bg-gray-50 rounded-lg">
                <p className="text-gray-500">No products found matching your criteria.</p>
                <button
                  onClick={() => {
                    setSearchTerm('');
                    setSelectedCategory('');
                    setSelectedManufacturer('');
                  }}
                  className="mt-2 text-blue-600 hover:text-blue-800"
                >
                  Clear filters
                </button>
              </div>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {filteredProducts.map(product => (
                                  <ProductCard
                  key={product.id}
                  product={product}
                  onAdd={addProductToSelection}
                  userRole={userRole}
                />
                ))}
              </div>
            )}
          </div>
        </div>

        {/* Selected Products Cart */}
        {showCart && (
          <div className="lg:col-span-1">
            <div className="bg-white border border-gray-200 rounded-lg p-4 sticky top-4">
              <div className="flex items-center justify-between mb-4">
                <h4 className="text-lg font-semibold text-gray-900 flex items-center">
                  <ShoppingCart className="w-5 h-5 mr-2" />
                  Selected Products
                </h4>
                <span className="text-sm text-gray-500">
                  {selectedProducts.length} items
                </span>
              </div>

              {selectedProducts.length === 0 ? (
                <p className="text-gray-500 text-center py-8">
                  No products selected yet
                </p>
              ) : (
                <div className="space-y-3">
                  {selectedProducts.map((item, index) => {
                    const product = item.product || products.find(p => p.id === item.product_id);
                    if (!product) return null;

                    const unitPrice = item.size
                      ? product.msc_price * parseFloat(item.size)
                      : product.msc_price;
                    const totalPrice = unitPrice * item.quantity;

                    return (
                      <div key={`${item.product_id}-${item.size || 'no-size'}`} className="border border-gray-200 rounded-md p-3">
                        <div className="flex items-start justify-between mb-2">
                          <div className="flex-1">
                            <h5 className="text-sm font-medium text-gray-900 line-clamp-1">
                              {product.name}
                            </h5>
                            <p className="text-xs text-gray-500">Q{product.q_code}</p>
                            {item.size && (
                              <p className="text-xs text-blue-600">Size: {item.size} cm²</p>
                            )}
                          </div>
                          <button
                            onClick={() => removeProduct(item.product_id, item.size)}
                            className="text-red-500 hover:text-red-700"
                          >
                            <X className="w-4 h-4" />
                          </button>
                        </div>

                        <div className="flex items-center justify-between">
                          <div className="flex items-center space-x-2">
                            <button
                              onClick={() => updateProductQuantity(item.product_id, item.size, item.quantity - 1)}
                              className="w-6 h-6 rounded-full border border-gray-300 flex items-center justify-center hover:bg-gray-50"
                            >
                              <Minus className="w-3 h-3" />
                            </button>
                            <span className="text-sm font-medium w-8 text-center">
                              {item.quantity}
                            </span>
                            <button
                              onClick={() => updateProductQuantity(item.product_id, item.size, item.quantity + 1)}
                              className="w-6 h-6 rounded-full border border-gray-300 flex items-center justify-center hover:bg-gray-50"
                            >
                              <Plus className="w-3 h-3" />
                            </button>
                          </div>
                          <div className="text-right">
                            <p className="text-sm font-semibold text-gray-900">
                              {formatPrice(totalPrice)}
                            </p>
                            <p className="text-xs text-gray-500">
                              {formatPrice(unitPrice)} each
                            </p>
                          </div>
                        </div>
                      </div>
                    );
                  })}

                  <div className="border-t border-gray-200 pt-3">
                    <div className="flex items-center justify-between">
                      <span className="text-base font-semibold text-gray-900">Total:</span>
                      <span className="text-lg font-bold text-blue-600">
                        {formatPrice(calculateTotal())}
                      </span>
                    </div>
                  </div>
                </div>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

// Product Card Component
const ProductCard: React.FC<{
  product: Product;
  onAdd: (product: Product, quantity: number, size?: string) => void;
  isRecommended?: boolean;
  userRole?: string;
}> = ({ product, onAdd, isRecommended = false, userRole = 'provider' }) => {
  const [selectedSize, setSelectedSize] = useState<string>('');
  const [quantity, setQuantity] = useState(1);

  const formatPrice = (price: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(price);
  };

  const handleAddProduct = () => {
    onAdd(product, quantity, selectedSize || undefined);
    setQuantity(1);
    setSelectedSize('');
  };

  const calculatePrice = () => {
    const pricePerUnit = userRole === 'office_manager' ? product.price_per_sq_cm : product.msc_price;
    if (selectedSize) {
      return pricePerUnit * parseFloat(selectedSize) * quantity;
    }
    return pricePerUnit * quantity;
  };

  return (
    <div className={`border rounded-lg p-4 hover:shadow-md transition-shadow ${
      isRecommended ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-white'
    }`}>
      {isRecommended && (
        <div className="mb-2">
          <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
            Recommended
          </span>
        </div>
      )}

      <div className="flex items-start justify-between mb-3">
        <div className="flex-1">
          <h3 className="text-sm font-semibold text-gray-900 line-clamp-2 mb-1">
            {product.name}
          </h3>
          <div className="flex items-center space-x-2 text-xs text-gray-500 mb-1">
            <Tag className="w-3 h-3" />
            <span>Q{product.q_code}</span>
            <span>•</span>
            <span>{product.sku}</span>
          </div>
          <div className="flex items-center text-xs text-gray-500">
            <Building className="w-3 h-3 mr-1" />
            <span>{product.manufacturer}</span>
          </div>
        </div>
      </div>

      <p className="text-xs text-gray-600 mb-3 line-clamp-2">
        {product.description}
      </p>

      <div className="space-y-2 mb-3">
        {/* Always show National ASP for office managers, MSC price for others */}
        {userRole === 'office_manager' ? (
          <div className="flex justify-between text-xs">
            <span className="text-gray-500">National ASP:</span>
            <span className="font-medium text-gray-900">
              {formatPrice(product.price_per_sq_cm)}/cm²
            </span>
          </div>
        ) : (
          <>
            <div className="flex justify-between text-xs">
              <span className="text-gray-500">MSC Price:</span>
              <span className="font-medium text-blue-600">
                {formatPrice(product.msc_price)}/cm²
              </span>
            </div>
            <div className="flex justify-between text-xs">
              <span className="text-gray-500">Commission:</span>
              <span className="font-medium text-green-600">
                {product.commission_rate}%
              </span>
            </div>
          </>
        )}
      </div>

      {/* Size Selection */}
      {product.available_sizes?.length > 0 && (
        <div className="mb-3">
          <label className="block text-xs font-medium text-gray-700 mb-1">
            Size (cm²)
          </label>
          <select
            value={selectedSize}
            onChange={(e) => setSelectedSize(e.target.value)}
            className="w-full text-xs border border-gray-300 rounded-md py-1 px-2 focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="">Select size...</option>
            {product.available_sizes.map(size => (
              <option key={size} value={size.toString()}>
                {size} cm² - {formatPrice((userRole === 'office_manager' ? product.price_per_sq_cm : product.msc_price) * size)}
              </option>
            ))}
          </select>
        </div>
      )}

      {/* Quantity Selection */}
      <div className="mb-3">
        <label className="block text-xs font-medium text-gray-700 mb-1">
          Quantity
        </label>
        <input
          type="number"
          min="1"
          value={quantity}
          onChange={(e) => setQuantity(Math.max(1, parseInt(e.target.value) || 1))}
          className="w-full text-xs border border-gray-300 rounded-md py-1 px-2 focus:ring-blue-500 focus:border-blue-500"
        />
      </div>

      {/* Total Price Display */}
      <div className="mb-3 p-2 bg-gray-50 rounded text-center">
        <span className="text-sm font-semibold text-gray-900">
          Total: {formatPrice(calculatePrice())}
        </span>
      </div>

      <button
        onClick={handleAddProduct}
        disabled={product.available_sizes?.length > 0 && !selectedSize}
        className="w-full bg-blue-600 text-white text-sm font-medium py-2 px-3 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
      >
        Add to Selection
      </button>
    </div>
  );
};

// AI Recommendation Card Component
const AIRecommendationCard: React.FC<{
  recommendation: AIRecommendation;
  onAdd: (recommendation: AIRecommendation, quantity: number) => void;
  rank: number;
  userRole?: string;
}> = ({ recommendation, onAdd, rank, userRole = 'provider' }) => {
  const [quantity, setQuantity] = useState(1);

  const formatPrice = (price: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(price);
  };

  const getConfidenceColor = (score: number) => {
    if (score >= 0.8) return 'text-green-600 bg-green-50';
    if (score >= 0.6) return 'text-yellow-600 bg-yellow-50';
    return 'text-red-600 bg-red-50';
  };

  const handleAddProduct = () => {
    onAdd(recommendation, quantity);
    setQuantity(1);
  };

  return (
    <div className="border border-blue-200 rounded-lg p-4 bg-white hover:shadow-md transition-all duration-200">
      {/* Header with rank and confidence */}
      <div className="flex items-center justify-between mb-3">
        <div className="flex items-center space-x-2">
          <span className="inline-flex items-center justify-center w-6 h-6 bg-blue-600 text-white text-xs font-bold rounded-full">
            {rank}
          </span>
          <div className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getConfidenceColor(recommendation.confidence_score)}`}>
            {Math.round(recommendation.confidence_score * 100)}% confidence
          </div>
        </div>
        <TrendingUp className="w-4 h-4 text-blue-600" />
      </div>

      {/* Product Info */}
      <div className="mb-3">
        <h3 className="text-sm font-semibold text-gray-900 mb-1">
          {recommendation.product_name}
        </h3>
        <div className="flex items-center space-x-2 text-xs text-gray-500 mb-1">
          <Tag className="w-3 h-3" />
          <span>Q{recommendation.q_code}</span>
          <span>•</span>
          <span>{recommendation.manufacturer}</span>
        </div>
        <span className="inline-block px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">
          {recommendation.category}
        </span>
      </div>

      {/* Clinical Reasoning */}
      <div className="mb-3 p-3 bg-gray-50 rounded-md">
        <h4 className="text-xs font-medium text-gray-700 mb-1">Clinical Rationale:</h4>
        <p className="text-xs text-gray-600 line-clamp-3">
          {recommendation.reasoning}
        </p>
      </div>

      {/* Key Benefits */}
      {recommendation.key_benefits && recommendation.key_benefits.length > 0 && (
        <div className="mb-3">
          <h4 className="text-xs font-medium text-gray-700 mb-1">Key Benefits:</h4>
          <div className="flex flex-wrap gap-1">
            {recommendation.key_benefits.slice(0, 3).map((benefit, index) => (
              <span key={index} className="inline-block px-2 py-1 text-xs bg-green-100 text-green-700 rounded">
                {benefit}
              </span>
            ))}
          </div>
        </div>
      )}

      {/* Pricing Information */}
      <div className="mb-3 space-y-1">
        {/* Always show National ASP */}
        <div className="flex justify-between text-xs">
          <span className="text-gray-500">National ASP:</span>
          <span className="font-medium text-gray-900">
            {formatPrice(recommendation.estimated_cost.national_asp)}
          </span>
        </div>

        {/* Only show MSC pricing and savings for non-office managers */}
        {userRole !== 'office_manager' && recommendation.estimated_cost.msc_price && (
          <>
            <div className="flex justify-between text-xs">
              <span className="text-gray-500">MSC Price:</span>
              <span className="font-medium text-blue-600">
                {formatPrice(recommendation.estimated_cost.msc_price)}
              </span>
            </div>
            {recommendation.estimated_cost.savings && recommendation.estimated_cost.savings > 0 && (
              <div className="flex justify-between text-xs">
                <span className="text-gray-500">Savings vs ASP:</span>
                <span className="font-medium text-green-600">
                  {formatPrice(recommendation.estimated_cost.savings)}
                  ({recommendation.estimated_cost.savings_percentage}%)
                </span>
              </div>
            )}
          </>
        )}

        {recommendation.suggested_size && (
          <div className="flex justify-between text-xs">
            <span className="text-gray-500">Suggested Size:</span>
            <span className="font-medium text-purple-600">
              {recommendation.suggested_size} cm²
            </span>
          </div>
        )}
      </div>

      {/* Contraindications */}
      {recommendation.contraindications && recommendation.contraindications.length > 0 && (
        <div className="mb-3 p-2 bg-red-50 border border-red-200 rounded">
          <div className="flex items-center mb-1">
            <AlertCircle className="w-3 h-3 mr-1 text-red-600" />
            <h4 className="text-xs font-medium text-red-700">Contraindications:</h4>
          </div>
          <ul className="text-xs text-red-600 space-y-1">
            {recommendation.contraindications.slice(0, 2).map((contraindication, index) => (
              <li key={index} className="flex items-start">
                <span className="mr-1">•</span>
                <span>{contraindication}</span>
              </li>
            ))}
          </ul>
        </div>
      )}

      {/* Quantity Selection */}
      <div className="mb-3">
        <label className="block text-xs font-medium text-gray-700 mb-1">
          Quantity
        </label>
        <input
          type="number"
          min="1"
          value={quantity}
          onChange={(e) => setQuantity(Math.max(1, parseInt(e.target.value) || 1))}
          className="w-full text-xs border border-gray-300 rounded-md py-1 px-2 focus:ring-blue-500 focus:border-blue-500"
        />
      </div>

      {/* Add Button */}
      <button
        onClick={handleAddProduct}
        className="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white text-sm font-medium py-2 px-3 rounded-md hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200"
      >
        <div className="flex items-center justify-center">
          <CheckCircle className="w-4 h-4 mr-2" />
          Add Recommended Product
        </div>
      </button>
    </div>
  );
};

export default ProductSelector;
