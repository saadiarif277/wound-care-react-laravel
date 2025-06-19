// CMS Enrichment Frontend Module
// Provides TypeScript interfaces and utilities for CMS ASP/MUE data

export interface CMSReimbursement {
  asp: number | null;
  mue: number | null;
}

export interface ProductWithCMS {
  id: number;
  name: string;
  q_code: string | null;
  national_asp: number | null;
  has_quantity_limits: boolean;
  max_allowed_quantity: number | null;
  cms_status?: 'current' | 'needs_update' | 'stale' | 'not_synced';
  cms_last_updated?: string;
}

export interface CMSSyncStatus {
  total_products_with_qcodes: number;
  synced_products: number;
  stale_products: number;
  needs_update_products: number;
  last_sync: string | null;
  sync_coverage_percentage: number;
}

export interface QuantityValidationResult {
  valid: boolean;
  warnings: string[];
  errors: string[];
  max_allowed: number | null;
  has_limits: boolean;
}

export interface OrderValidationResult {
  valid: boolean;
  results: Array<{
    product_id: number;
    product_name: string;
    q_code: string;
    valid: boolean;
    warnings: string[];
    errors: string[];
  }>;
}

/**
 * CMS Enrichment Service for Frontend
 */
export class CMSEnrichmentService {
  private baseUrl: string;

  constructor(baseUrl: string = '/api/products') {
    this.baseUrl = baseUrl;
  }

  /**
   * Get CMS sync status for admin dashboard
   */
  async getSyncStatus(): Promise<CMSSyncStatus> {
    const response = await fetch(`${this.baseUrl}/cms/status`, {
      headers: {
        'X-CSRF-TOKEN': this.getCsrfToken(),
        'Accept': 'application/json',
      },
    });

    if (!response.ok) {
      throw new Error('Failed to fetch CMS sync status');
    }

    return response.json();
  }

  /**
   * Trigger CMS pricing sync
   */
  async syncPricing(): Promise<{ success: boolean; message: string; output?: string }> {
    const response = await fetch(`${this.baseUrl}/cms/sync`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': this.getCsrfToken(),
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    });

    const result = await response.json();

    if (!response.ok) {
      throw new Error(result.message || 'Failed to sync CMS pricing');
    }

    return result;
  }

  /**
   * Validate quantity against MUE limits for a product
   */
  async validateQuantity(productId: number, quantity: number): Promise<QuantityValidationResult> {
    const response = await fetch(`/products/${productId}/validate-quantity`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': this.getCsrfToken(),
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ quantity }),
    });

    if (!response.ok) {
      throw new Error('Failed to validate quantity');
    }

    return response.json();
  }

  /**
   * Check if a product has ASP pricing visible to current user
   */
  hasAspVisible(product: ProductWithCMS): boolean {
    return product.national_asp !== null && product.national_asp !== undefined;
  }

  /**
   * Check if a product has MUE enforcement
   */
  hasMueEnforcement(product: ProductWithCMS): boolean {
    return product.has_quantity_limits === true;
  }

  /**
   * Get formatted ASP price
   */
  formatAsp(asp: number | null): string {
    if (asp === null || asp === undefined) {
      return 'N/A';
    }
    return `$${asp.toFixed(2)}`;
  }

  /**
   * Get MUE status message
   */
  getMueStatusMessage(product: ProductWithCMS): string | null {
    if (!this.hasMueEnforcement(product)) {
      return null;
    }

    return `Maximum ${product.max_allowed_quantity} units per date of service (CMS MUE)`;
  }

  /**
   * Get CMS sync status badge info
   */
  getSyncStatusBadge(status: string): { color: string; text: string; tooltip: string } {
    switch (status) {
      case 'current':
        return {
          color: 'green',
          text: 'Current',
          tooltip: 'CMS data is up-to-date'
        };
      case 'needs_update':
        return {
          color: 'yellow',
          text: 'Needs Update',
          tooltip: 'CMS data is 30+ days old'
        };
      case 'stale':
        return {
          color: 'red',
          text: 'Stale',
          tooltip: 'CMS data is 90+ days old'
        };
      case 'not_synced':
        return {
          color: 'gray',
          text: 'Not Synced',
          tooltip: 'No CMS data available'
        };
      default:
        return {
          color: 'gray',
          text: 'Unknown',
          tooltip: 'Status unknown'
        };
    }
  }

  /**
   * Validate order items against MUE limits
   */
  validateOrderQuantities(orderItems: Array<{ product: ProductWithCMS; quantity: number }>): {
    valid: boolean;
    violations: Array<{ product: ProductWithCMS; quantity: number; maxAllowed: number }>;
  } {
    const violations: Array<{ product: ProductWithCMS; quantity: number; maxAllowed: number }> = [];

    for (const item of orderItems) {
      if (this.hasMueEnforcement(item.product) &&
          item.product.max_allowed_quantity !== null &&
          item.quantity > item.product.max_allowed_quantity) {
        violations.push({
          product: item.product,
          quantity: item.quantity,
          maxAllowed: item.product.max_allowed_quantity
        });
      }
    }

    return {
      valid: violations.length === 0,
      violations
    };
  }

  /**
   * Get CSRF token from meta tag
   */
  private getCsrfToken(): string {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!token) {
      throw new Error('CSRF token not found');
    }
    return token;
  }
}

/**
 * React hook for CMS enrichment functionality
 */
export function useCMSEnrichment() {
  const service = new CMSEnrichmentService();

  const syncPricing = async () => {
    try {
      const result = await service.syncPricing();
      return { success: true, data: result };
    } catch (error) {
      return {
        success: false,
        error: error instanceof Error ? error.message : 'Unknown error'
      };
    }
  };

  const getSyncStatus = async () => {
    try {
      const status = await service.getSyncStatus();
      return { success: true, data: status };
    } catch (error) {
      return {
        success: false,
        error: error instanceof Error ? error.message : 'Unknown error'
      };
    }
  };

  const validateQuantity = async (productId: number, quantity: number) => {
    try {
      const result = await service.validateQuantity(productId, quantity);
      return { success: true, data: result };
    } catch (error) {
      return {
        success: false,
        error: error instanceof Error ? error.message : 'Unknown error'
      };
    }
  };

  return {
    service,
    syncPricing,
    getSyncStatus,
    validateQuantity,
  };
}

// Default export for convenience
export default CMSEnrichmentService;
