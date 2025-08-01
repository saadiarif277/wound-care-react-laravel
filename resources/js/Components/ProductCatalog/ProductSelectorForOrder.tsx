import React, { useState, useEffect } from 'react';
import {
  Search,
  Filter,
  Check,
  ShoppingCart,
  Tag,
  Building,
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
  value: string;
  label: string;
  sku: string;
  nationalAsp: number;
  pricePerSqCm: number;
  qCode: string;
  graphTypes: string[];
  graphSizes: string[];
  product?: Product;
}

interface Props {
  selectedProduct: SelectedProduct | null;
  onProductChange: (product: SelectedProduct | null) => void;
  graphSize: string;
  onGraphSizeChange: (size: string) => void;
  units: number;
  onUnitsChange: (units: number) => void;
  expectedReimbursement: number;
  onExpectedReimbursementChange: (amount: number) => void;
  invoiceAmountMedicare: number;
  onInvoiceAmountMedicareChange: (amount: number) => void;
  secondaryPayer: string;
  onSecondaryPayerChange: (amount: string) => void;
  invoiceToDoc: number;
  onInvoiceToDocChange: (amount: number) => void;
}

const ProductSelectorForOrder: React.FC<Props> = ({
  selectedProduct,
  onProductChange,
  graphSize,
  onGraphSizeChange,
  units,
  onUnitsChange,
  expectedReimbursement,
  onExpectedReimbursementChange,
  invoiceAmountMedicare,
  onInvoiceAmountMedicareChange,
  secondaryPayer,
  onSecondaryPayerChange,
  invoiceToDoc,
  onInvoiceToDocChange,
}) => {
  const [products, setProducts] = useState<Product[]>([]);
  const [filteredProducts, setFilteredProducts] = useState<Product[]>([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('');
  const [selectedManufacturer, setSelectedManufacturer] = useState('');
  const [loading, setLoading] = useState(true);
  const [categories, setCategories] = useState<string[]>([]);
  const [manufacturers, setManufacturers] = useState<string[]>([]);
  const [showCatalog, setShowCatalog] = useState(false);

  // Fetch products and catalog data
  useEffect(() => {
    fetchProducts();
  }, []);

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

  const handleProductSelect = (product: Product) => {
    const selectedProductData: SelectedProduct = {
      value: product.id.toString(),
      label: product.name,
      sku: product.sku,
      nationalAsp: product.price_per_sq_cm,
      pricePerSqCm: product.msc_price,
      qCode: product.q_code,
      graphTypes: [product.category],
      graphSizes: product.available_sizes.map(size => size.toString()),
      product: product
    };

    onProductChange(selectedProductData);
    setShowCatalog(false);
  };

  const formatPrice = (price: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(price);
  };

  // Calculate automatic values based on selected product and size
  useEffect(() => {
    if (selectedProduct?.product && graphSize) {
      const size = parseFloat(graphSize);
      if (!isNaN(size)) {
        // Price = size × per unit price × quantity
        const totalCost = size * selectedProduct.pricePerSqCm * units;
        onExpectedReimbursementChange(totalCost);
        onInvoiceAmountMedicareChange(totalCost * 0.8);
        onSecondaryPayerChange((totalCost * 0.2).toString());
        onInvoiceToDocChange(totalCost * 0.4);
      }
    }
  }, [selectedProduct, graphSize, units]);

  return (
    <div className="space-y-6">
      {/* Product Selection Section */}
      <div className="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-4">
        <div className="flex items-center justify-between mb-4">
          <div>
            <h3 className="text-lg font-semibold text-gray-900">Product Selection</h3>
            <p className="text-sm text-gray-600">
              Choose a product from our catalog or search for specific items
            </p>
          </div>
          <button
            onClick={() => setShowCatalog(!showCatalog)}
            className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <ShoppingCart className="w-4 h-4 mr-2" />
            {showCatalog ? 'Hide Catalog' : 'Browse Catalog'}
          </button>
        </div>

        {/* Current Selection Display */}
        {selectedProduct && (
          <div className="bg-white rounded-lg p-4 border border-gray-200">
            <div className="flex items-start justify-between">
              <div className="flex-1">
                <h4 className="text-lg font-semibold text-gray-900">{selectedProduct.label}</h4>
                <div className="flex items-center space-x-4 mt-2 text-sm text-gray-600">
                  <span>SKU: {selectedProduct.sku}</span>
                  <span>Q-Code: {selectedProduct.qCode}</span>
                  <span>National ASP: {formatPrice(selectedProduct.nationalAsp)}/cm²</span>
                  <span>MSC Price: {formatPrice(selectedProduct.pricePerSqCm)}/cm²</span>
                </div>
              </div>
              <button
                onClick={() => onProductChange(null)}
                className="text-red-600 hover:text-red-800"
              >
                Remove
              </button>
            </div>
          </div>
        )}

        {/* Product Catalog */}
        {showCatalog && (
          <div className="mt-4 bg-white rounded-lg border border-gray-200">
            <div className="p-4 border-b border-gray-200">
              <div className="flex items-center justify-between mb-4">
                <h4 className="text-md font-semibold text-gray-900">Product Catalog</h4>
                <span className="text-sm text-gray-500">
                  {filteredProducts.length} products available
                </span>
              </div>

              {/* Search */}
              <div className="relative mb-4">
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
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
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
            </div>

            {/* Product List */}
            <div className="p-4 max-h-96 overflow-y-auto">
              {loading ? (
                <div className="flex items-center justify-center py-8">
                  <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                  <span className="ml-2 text-gray-600">Loading products...</span>
                </div>
              ) : filteredProducts.length === 0 ? (
                <div className="text-center py-8 text-gray-500">
                  No products found matching your criteria.
                </div>
              ) : (
                <div className="space-y-2">
                  {filteredProducts.map(product => (
                    <div
                      key={product.id}
                      className={`border rounded-lg p-3 cursor-pointer hover:bg-gray-50 transition-colors ${
                        selectedProduct?.value === product.id.toString() ? 'border-blue-500 bg-blue-50' : 'border-gray-200'
                      }`}
                      onClick={() => handleProductSelect(product)}
                    >
                      <div className="flex items-start justify-between">
                        <div className="flex-1">
                          <h5 className="font-medium text-gray-900">{product.name}</h5>
                          <div className="flex items-center space-x-4 mt-1 text-sm text-gray-600">
                            <span className="flex items-center">
                              <Tag className="w-3 h-3 mr-1" />
                              Q{product.q_code}
                            </span>
                            <span className="flex items-center">
                              <Building className="w-3 h-3 mr-1" />
                              {product.manufacturer}
                            </span>
                            <span className="flex items-center">
                              <DollarSign className="w-3 h-3 mr-1" />
                              {formatPrice(product.msc_price)}/cm²
                            </span>
                          </div>
                          <p className="text-xs text-gray-500 mt-1 line-clamp-1">
                            {product.description}
                          </p>
                        </div>
                        {selectedProduct?.value === product.id.toString() && (
                          <Check className="w-5 h-5 text-blue-600" />
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        )}
      </div>

      {/* Order Details Section */}
      {selectedProduct && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Graph Size (cm²) *</label>
            <select
              value={graphSize}
              onChange={(e) => onGraphSizeChange(e.target.value)}
              className="w-full border border-gray-300 rounded-md py-2 px-3 focus:ring-blue-500 focus:border-blue-500"
              required
            >
              <option value="">Select size...</option>
              {selectedProduct.graphSizes.map(size => (
                <option key={size} value={size}>
                  {size} cm² - {formatPrice(selectedProduct.pricePerSqCm * parseFloat(size))}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Units *</label>
            <input
              type="number"
              min="1"
              value={units}
              onChange={(e) => onUnitsChange(Math.max(1, parseInt(e.target.value) || 1))}
              className="w-full border border-gray-300 rounded-md py-2 px-3 focus:ring-blue-500 focus:border-blue-500"
              required
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Expected Reimbursement *</label>
            <input
              type="number"
              min="0"
              step="0.01"
              value={expectedReimbursement}
              onChange={(e) => onExpectedReimbursementChange(parseFloat(e.target.value) || 0)}
              className="w-full border border-gray-300 rounded-md py-2 px-3 focus:ring-blue-500 focus:border-blue-500"
              required
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Invoice Amount to Medicare (80%) *</label>
            <input
              type="number"
              min="0"
              step="0.01"
              value={invoiceAmountMedicare}
              onChange={(e) => onInvoiceAmountMedicareChange(parseFloat(e.target.value) || 0)}
              className="w-full border border-gray-300 rounded-md py-2 px-3 focus:ring-blue-500 focus:border-blue-500"
              required
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Secondary Payer Amount (20%) *</label>
            <input
              type="number"
              min="0"
              step="0.01"
              value={parseFloat(secondaryPayer) || 0}
              onChange={(e) => onSecondaryPayerChange(e.target.value)}
              className="w-full border border-gray-300 rounded-md py-2 px-3 focus:ring-blue-500 focus:border-blue-500"
              required
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Invoice to Doc from Manufacturer (40%) *</label>
            <input
              type="number"
              min="0"
              step="0.01"
              value={invoiceToDoc}
              onChange={(e) => onInvoiceToDocChange(parseFloat(e.target.value) || 0)}
              className="w-full border border-gray-300 rounded-md py-2 px-3 focus:ring-blue-500 focus:border-blue-500"
              required
            />
          </div>
        </div>
      )}

      {/* Summary */}
      {selectedProduct && graphSize && (
        <div className="bg-gray-50 rounded-lg p-4">
          <h4 className="text-md font-semibold text-gray-900 mb-3">Order Summary</h4>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
              <span className="text-gray-600">Product:</span>
              <span className="ml-2 font-medium">{selectedProduct.label}</span>
            </div>
            <div>
              <span className="text-gray-600">Size:</span>
              <span className="ml-2 font-medium">{graphSize} cm²</span>
            </div>
            <div>
              <span className="text-gray-600">Units:</span>
              <span className="ml-2 font-medium">{units}</span>
            </div>
            <div>
              <span className="text-gray-600">Total Area:</span>
              <span className="ml-2 font-medium">{parseFloat(graphSize) * units} cm²</span>
            </div>
            <div>
              <span className="text-gray-600">Unit Price:</span>
              <span className="ml-2 font-medium">
                {formatPrice(selectedProduct.pricePerSqCm * parseFloat(graphSize))}
              </span>
            </div>
            <div>
              <span className="text-gray-600">Total Cost:</span>
              <span className="ml-2 font-medium text-blue-600">
                {formatPrice(selectedProduct.pricePerSqCm * parseFloat(graphSize) * units)}
              </span>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default ProductSelectorForOrder;
