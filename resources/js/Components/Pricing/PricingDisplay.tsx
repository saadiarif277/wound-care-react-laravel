import React from 'react';

// Utility function to safely format currency values
const formatCurrency = (value: number | string | undefined | null): string => {
  if (value === null || value === undefined) return '0.00';
  const numericValue = typeof value === 'number' ? value : parseFloat(value) || 0;
  return numericValue.toFixed(2);
};

interface RoleRestrictions {
  can_view_financials: boolean;
  can_see_discounts: boolean;
  can_see_msc_pricing: boolean;
  can_see_order_totals: boolean;
  pricing_access_level: string;
  commission_access_level: string;
}

interface PricingDisplayProps {
  roleRestrictions: RoleRestrictions;
  product: {
    nationalAsp?: number | string;
    mscPrice?: number | string;
    discountPrice?: number | string;
    specialRate?: number | string;
    listPrice?: number | string;
  };
  showLabel?: boolean;
  className?: string;
}

interface OrderTotalProps {
  roleRestrictions: RoleRestrictions;
  total?: number | string;
  amountOwed?: number | string;
  discount?: number | string;
  className?: string;
}

export function PricingDisplay({ roleRestrictions, product, showLabel = true, className = '' }: PricingDisplayProps) {
  // Users with no pricing access at all (e.g., office managers)
  if (roleRestrictions.pricing_access_level === 'none') {
    return (
      <div className={`text-gray-500 ${className}`}>
        {showLabel && <span className="text-sm">Price: </span>}
        <span>Not available for your role</span>
      </div>
    );
  }

  // Office Manager or users without MSC pricing access - ONLY show National ASP
  if (!roleRestrictions.can_see_msc_pricing) {
    if (!product.nationalAsp) {
      return (
        <div className={`text-gray-500 ${className}`}>
          {showLabel && <span className="text-sm">National ASP: </span>}
          <span>Not Available</span>
        </div>
      );
    }

    return (
      <div className={`text-gray-900 ${className}`}>
        {showLabel && <span className="text-sm text-gray-600">National ASP: </span>}
        <span className="font-semibold">${formatCurrency(product.nationalAsp)}</span>
      </div>
    );
  }

  // Users with MSC pricing access - Show National ASP, MSC Price, and discounts
  if (roleRestrictions.can_see_msc_pricing) {
    const prices = [];

    // Always show National ASP first for comparison
    if (product.nationalAsp) {
      prices.push({
        label: 'National ASP',
        value: product.nationalAsp,
        primary: false
      });
    }

    // Show MSC Price if user can see discounts
    if (roleRestrictions.can_see_discounts && product.mscPrice) {
      prices.push({
        label: 'MSC Price',
        value: product.mscPrice,
        primary: true
      });
    }

    // Show discount price if different from MSC price and user can see discounts
    if (roleRestrictions.can_see_discounts && product.discountPrice && product.discountPrice !== product.mscPrice) {
      prices.push({
        label: 'Your Price',
        value: product.discountPrice,
        primary: true
      });
    }

    if (prices.length === 0) {
      return (
        <div className={`text-gray-500 ${className}`}>
          {showLabel && <span className="text-sm">Price: </span>}
          <span>Not Available</span>
        </div>
      );
    }

    return (
      <div className={className}>
        {prices.map((price, index) => (
          <div key={price.label} className={index > 0 ? 'mt-1' : ''}>
            {showLabel && <span className="text-sm text-gray-600">{price.label}: </span>}
            <span className={`font-semibold ${price.primary ? 'text-gray-900' : 'text-gray-700'}`}>
              ${formatCurrency(price.value)}
            </span>
          </div>
        ))}
      </div>
    );
  }

  // Fallback for limited access users
  const displayPrice = product.nationalAsp || product.listPrice;
  if (!displayPrice) {
    return (
      <div className={`text-gray-500 ${className}`}>
        {showLabel && <span className="text-sm">Price: </span>}
        <span>Not Available</span>
      </div>
    );
  }

  return (
    <div className={`text-gray-900 ${className}`}>
      {showLabel && <span className="text-sm text-gray-600">Price: </span>}
      <span className="font-semibold">${formatCurrency(displayPrice)}</span>
    </div>
  );
}

export function OrderTotalDisplay({ roleRestrictions, total, amountOwed, discount, className = '' }: OrderTotalProps) {
  // Users without order totals access cannot see financial totals
  if (!roleRestrictions.can_see_order_totals) {
    return (
      <div className={`text-gray-500 italic ${className}`}>
        <span className="text-sm">Financial information not available for your role</span>
      </div>
    );
  }

  // Users with limited financial access
  if (!roleRestrictions.can_view_financials) {
    return (
      <div className={`text-gray-500 italic ${className}`}>
        <span className="text-sm">Order totals not available</span>
      </div>
    );
  }

  // Users with full financial visibility
  return (
    <div className={className}>
      {total !== undefined && (
        <div className="flex justify-between">
          <span className="text-gray-600">Order Total:</span>
          <span className="font-semibold text-gray-900">${formatCurrency(total)}</span>
        </div>
      )}

      {discount !== undefined && Number(discount) > 0 && roleRestrictions.can_see_discounts && (
        <div className="flex justify-between mt-1">
          <span className="text-gray-600">Discount:</span>
          <span className="font-semibold text-green-600">-${formatCurrency(discount)}</span>
        </div>
      )}

      {amountOwed !== undefined && (
        <div className="flex justify-between mt-1 pt-1 border-t border-gray-200">
          <span className="font-semibold text-gray-900">Amount Owed:</span>
          <span className="font-bold text-gray-900">${formatCurrency(amountOwed)}</span>
        </div>
      )}
    </div>
  );
}

export function CommissionDisplay({ roleRestrictions, commission, className = '' }: {
  roleRestrictions: RoleRestrictions;
  commission?: number | string;
  className?: string;
}) {
  // Use commission_access_level to determine commission visibility (RBAC compliant)
  const commissionAccess = roleRestrictions.commission_access_level;

  // No commission access - hide commission completely
  if (commissionAccess === 'none') {
    return null;
  }

  // Limited commission access
  if (commissionAccess === 'limited') {
    return (
      <div className={`text-gray-600 ${className}`}>
        <span className="text-sm">Commission: Limited Access</span>
      </div>
    );
  }

  // Full commission access
  if (commissionAccess === 'full') {
    if (commission === undefined) {
      return (
        <div className={`text-gray-500 ${className}`}>
          <span className="text-sm">Commission: Not Available</span>
        </div>
      );
    }

    return (
      <div className={className}>
        <span className="text-sm text-gray-600">Commission: </span>
        <span className="font-semibold text-green-600">${formatCurrency(commission)}</span>
      </div>
    );
  }

  // Fallback - hide commission for unknown access levels
  return null;
}
