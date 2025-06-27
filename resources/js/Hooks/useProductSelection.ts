import { useState, useCallback, useEffect, useMemo } from 'react';
import { useForm, useFieldArray } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import axios from 'axios';
import {
  ProductSelectionData,
  SelectedProduct,
  ProductSize,
  ClinicalBillingData,
} from '@/types/quickRequest';

// Validation schema
const productSelectionSchema = z.object({
  manufacturer: z.object({
    id: z.string().min(1, 'Manufacturer is required'),
    name: z.string().min(1),
    code: z.string().min(1),
  }),
  products: z.array(
    z.object({
      id: z.string().min(1),
      name: z.string().min(1),
      code: z.string().min(1),
      category: z.string().min(1),
      quantity: z.number().positive('Quantity must be positive'),
      frequency: z.enum(['daily', 'weekly', 'biweekly', 'monthly', 'as_needed']),
      sizes: z.array(
        z.object({
          size: z.string().min(1),
          quantity: z.number().positive(),
          unit: z.string().min(1),
        })
      ).min(1, 'At least one size is required'),
      modifiers: z.array(z.string()).optional(),
      specialInstructions: z.string().optional(),
      mueLimits: z.object({
        quantity: z.number().positive(),
        period: z.enum(['day', 'week', 'month']),
      }).optional(),
    })
  ).min(1, 'At least one product must be selected'),
  deliveryPreferences: z.object({
    method: z.enum(['standard', 'expedited', 'overnight']),
    specialInstructions: z.string().optional(),
    preferredDeliveryDays: z.array(z.string()).optional(),
    deliveryAddress: z.object({
      use: z.enum(['home', 'work', 'temp', 'old', 'billing']),
      type: z.enum(['postal', 'physical', 'both']),
      line: z.array(z.string()).min(1),
      city: z.string().min(1),
      state: z.string().length(2),
      postalCode: z.string().regex(/^\d{5}(-\d{4})?$/),
      country: z.string().default('USA'),
    }).optional(),
  }),
});

type ProductSelectionFormData = z.infer<typeof productSelectionSchema>;

interface UseProductSelectionProps {
  initialData?: Partial<ProductSelectionData>;
  onSave?: (data: ProductSelectionData) => void;
  onNext?: (data: ProductSelectionData) => void;
  clinicalData?: ClinicalBillingData;
  patientAddress?: any;
}

interface Manufacturer {
  id: string;
  name: string;
  code: string;
  hasIVRTemplate: boolean;
  productCategories: string[];
}

interface Product {
  id: string;
  manufacturerId: string;
  name: string;
  code: string;
  category: string;
  availableSizes: ProductSize[];
  modifiers?: string[];
  mueLimits?: {
    quantity: number;
    period: 'day' | 'week' | 'month';
  };
  requiresPrescription: boolean;
  coveredByMedicare: boolean;
  description?: string;
}

interface MedicareValidation {
  product: string;
  covered: boolean;
  requiresDocumentation: string[];
  warnings: string[];
  mueLimitExceeded?: boolean;
}

export function useProductSelection({
  initialData,
  onSave,
  onNext,
  clinicalData,
  patientAddress,
}: UseProductSelectionProps) {
  const [manufacturers, setManufacturers] = useState<Manufacturer[]>([]);
  const [isLoadingManufacturers, setIsLoadingManufacturers] = useState(false);
  
  const [products, setProducts] = useState<Product[]>([]);
  const [isLoadingProducts, setIsLoadingProducts] = useState(false);
  
  const [medicareValidation, setMedicareValidation] = useState<MedicareValidation[]>([]);
  const [isValidatingMedicare, setIsValidatingMedicare] = useState(false);

  const {
    register,
    handleSubmit,
    control,
    watch,
    setValue,
    formState: { errors, isSubmitting, isDirty },
    trigger,
  } = useForm<ProductSelectionFormData>({
    resolver: zodResolver(productSelectionSchema),
    defaultValues: initialData || {
      products: [],
      deliveryPreferences: {
        method: 'standard',
        deliveryAddress: patientAddress,
      },
    },
  });

  const { fields: selectedProducts, append, remove, update } = useFieldArray({
    control,
    name: 'products',
  });

  // Load manufacturers on mount
  useEffect(() => {
    setIsLoadingManufacturers(true);
    axios
      .get('/api/v1/manufacturers', {
        params: {
          hasIVRTemplate: true,
          woundType: clinicalData?.woundDetails?.woundType,
        },
      })
      .then(response => {
        setManufacturers(response.data.data);
      })
      .catch(error => {
        console.error('Failed to load manufacturers:', error);
      })
      .finally(() => {
        setIsLoadingManufacturers(false);
      });
  }, [clinicalData?.woundDetails?.woundType]);

  // Load products when manufacturer changes
  const selectedManufacturerId = watch('manufacturer.id');
  useEffect(() => {
    if (!selectedManufacturerId) return;

    setIsLoadingProducts(true);
    axios
      .get(`/api/v1/manufacturers/${selectedManufacturerId}/products`, {
        params: {
          woundType: clinicalData?.woundDetails?.woundType,
          diagnosisCode: clinicalData?.diagnosis?.primary?.code,
        },
      })
      .then(response => {
        setProducts(response.data.data);
      })
      .catch(error => {
        console.error('Failed to load products:', error);
      })
      .finally(() => {
        setIsLoadingProducts(false);
      });
  }, [selectedManufacturerId, clinicalData]);

  // Set manufacturer details when selected
  const selectedManufacturer = useMemo(() => {
    return manufacturers.find(m => m.id === selectedManufacturerId);
  }, [selectedManufacturerId, manufacturers]);

  useEffect(() => {
    if (selectedManufacturer) {
      setValue('manufacturer.name', selectedManufacturer.name);
      setValue('manufacturer.code', selectedManufacturer.code);
    }
  }, [selectedManufacturer, setValue]);

  // Add product to selection
  const addProduct = useCallback((product: Product) => {
    const existingIndex = selectedProducts.findIndex(p => p.id === product.id);
    
    if (existingIndex >= 0) {
      // Update quantity if product already selected
      const existing = selectedProducts[existingIndex];
      update(existingIndex, {
        ...existing,
        quantity: existing.quantity + 1,
      });
    } else {
      // Add new product
      append({
        id: product.id,
        name: product.name,
        code: product.code,
        category: product.category,
        quantity: 1,
        frequency: 'monthly',
        sizes: product.availableSizes.map(size => ({
          ...size,
          quantity: 0,
        })),
        modifiers: product.modifiers || [],
        mueLimits: product.mueLimits,
      });
    }
  }, [selectedProducts, append, update]);

  // Update product size quantity
  const updateProductSize = useCallback(
    (productIndex: number, sizeIndex: number, quantity: number) => {
      const product = selectedProducts[productIndex];
      const newSizes = [...product.sizes];
      newSizes[sizeIndex] = {
        ...newSizes[sizeIndex],
        quantity,
      };
      
      update(productIndex, {
        ...product,
        sizes: newSizes,
      });
    },
    [selectedProducts, update]
  );

  // Validate Medicare coverage for selected products
  const validateMedicareCoverage = useCallback(async () => {
    if (selectedProducts.length === 0) return;

    setIsValidatingMedicare(true);
    try {
      const response = await axios.post('/api/v1/medicare/validate-products', {
        products: selectedProducts.map(p => ({
          code: p.code,
          quantity: p.quantity,
          frequency: p.frequency,
          sizes: p.sizes.filter(s => s.quantity > 0),
        })),
        diagnosis: clinicalData?.diagnosis?.primary,
        woundDetails: clinicalData?.woundDetails,
      });
      
      setMedicareValidation(response.data.data);
      return response.data.data;
    } catch (error) {
      console.error('Medicare validation failed:', error);
      return [];
    } finally {
      setIsValidatingMedicare(false);
    }
  }, [selectedProducts, clinicalData]);

  // Calculate total monthly cost estimate
  const monthlyQuantityEstimate = useMemo(() => {
    return selectedProducts.reduce((total, product) => {
      const baseQuantity = product.quantity;
      const multiplier = {
        daily: 30,
        weekly: 4,
        biweekly: 2,
        monthly: 1,
        as_needed: 1,
      }[product.frequency];
      
      return total + (baseQuantity * multiplier);
    }, 0);
  }, [selectedProducts]);

  // Check MUE limits
  const mueLimitWarnings = useMemo(() => {
    return selectedProducts
      .filter(product => {
        if (!product.mueLimits) return false;
        
        const frequencyMultiplier = {
          daily: product.mueLimits.period === 'day' ? 1 : product.mueLimits.period === 'week' ? 7 : 30,
          weekly: product.mueLimits.period === 'week' ? 1 : product.mueLimits.period === 'month' ? 4 : 7,
          biweekly: product.mueLimits.period === 'month' ? 2 : 14,
          monthly: product.mueLimits.period === 'month' ? 1 : 30,
          as_needed: 1,
        }[product.frequency];
        
        return (product.quantity * frequencyMultiplier) > product.mueLimits.quantity;
      })
      .map(product => ({
        product: product.name,
        message: `Exceeds MUE limit of ${product.mueLimits?.quantity} per ${product.mueLimits?.period}`,
      }));
  }, [selectedProducts]);

  // Handle form submission
  const onSubmit = handleSubmit(async (data) => {
    // Validate Medicare coverage
    const validation = await validateMedicareCoverage();
    const hasErrors = validation.some(v => !v.covered);
    
    if (hasErrors) {
      const shouldContinue = window.confirm(
        'Some products may not be covered by Medicare. Do you want to continue?'
      );
      if (!shouldContinue) return;
    }

    // Check MUE limits
    if (mueLimitWarnings.length > 0) {
      const warnings = mueLimitWarnings.map(w => `- ${w.product}: ${w.message}`).join('\n');
      const shouldContinue = window.confirm(
        `The following MUE limits are exceeded:\n${warnings}\n\nDo you want to continue?`
      );
      if (!shouldContinue) return;
    }

    // Save progress
    if (onSave) {
      await onSave(data as ProductSelectionData);
    }

    // Proceed to next step
    if (onNext) {
      await onNext(data as ProductSelectionData);
    }
  });

  // Auto-save functionality
  useEffect(() => {
    if (isDirty && onSave) {
      const saveTimer = setTimeout(() => {
        const formData = watch();
        onSave(formData as ProductSelectionData);
      }, 3000);

      return () => clearTimeout(saveTimer);
    }
  }, [isDirty, watch, onSave]);

  // Product search
  const [productSearchQuery, setProductSearchQuery] = useState('');
  const filteredProducts = useMemo(() => {
    if (!productSearchQuery) return products;
    
    const query = productSearchQuery.toLowerCase();
    return products.filter(
      p =>
        p.name.toLowerCase().includes(query) ||
        p.code.toLowerCase().includes(query) ||
        p.category.toLowerCase().includes(query)
    );
  }, [products, productSearchQuery]);

  return {
    // Form methods
    register,
    handleSubmit: onSubmit,
    control,
    errors,
    isSubmitting,
    isDirty,
    watch,
    setValue,
    trigger,

    // Manufacturers
    manufacturers,
    isLoadingManufacturers,
    selectedManufacturer,

    // Products
    products: filteredProducts,
    isLoadingProducts,
    selectedProducts,
    addProduct,
    removeProduct: remove,
    updateProductSize,
    productSearchQuery,
    setProductSearchQuery,

    // Validation
    medicareValidation,
    isValidatingMedicare,
    validateMedicareCoverage,
    mueLimitWarnings,

    // Computed values
    monthlyQuantityEstimate,
    hasProducts: selectedProducts.length > 0,
    canProceed: !Object.values(errors).length && !isSubmitting && selectedProducts.length > 0,
  };
}