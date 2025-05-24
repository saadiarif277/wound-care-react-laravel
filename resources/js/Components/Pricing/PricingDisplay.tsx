import React from 'react';
import { UserRole } from '@/types/roles';

interface PricingDisplayProps {
  userRole: UserRole;
  product: {
    nationalAsp?: number;
    mscPrice?: number;
    discountPrice?: number;
    specialRate?: number;
    listPrice?: number;
  };
  showLabel?: boolean;
  className?: string;
}

interface OrderTotalProps {
  userRole: UserRole;
  total?: number;
  amountOwed?: number;
  discount?: number;
  className?: string;
}

export function PricingDisplay({ userRole, product, showLabel = true, className = '' }: PricingDisplayProps) {
  // Office Manager - ONLY show National ASP, NO other pricing
  if (userRole === 'office_manager') {
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
        <span className="font-semibold">${product.nationalAsp.toFixed(2)}</span>
      </div>
    );
  }

  // MSC Sub-Rep - Limited pricing access
  if (userRole === 'msc_subrep') {
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
        <span className="font-semibold">${displayPrice.toFixed(2)}</span>
      </div>
    );
  }

  // All other roles - Full pricing access
  const prices = [];

  if (product.mscPrice) {
    prices.push({
      label: 'MSC Price',
      value: product.mscPrice,
      primary: true
    });
  }

  if (product.discountPrice && product.discountPrice !== product.mscPrice) {
    prices.push({
      label: 'Discount Price',
      value: product.discountPrice,
      primary: false
    });
  }

  if (product.nationalAsp) {
    prices.push({
      label: 'National ASP',
      value: product.nationalAsp,
      primary: prices.length === 0
    });
  }

  if (product.listPrice && prices.length === 0) {
    prices.push({
      label: 'List Price',
      value: product.listPrice,
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
            ${price.value.toFixed(2)}
          </span>
        </div>
      ))}
    </div>
  );
}

export function OrderTotalDisplay({ userRole, total, amountOwed, discount, className = '' }: OrderTotalProps) {
  // Office Manager - CANNOT see any financial totals
  if (userRole === 'office_manager') {
    return (
      <div className={`text-gray-500 italic ${className}`}>
        <span className="text-sm">Financial information not available for your role</span>
      </div>
    );
  }

  // MSC Sub-Rep - Limited financial access
  if (userRole === 'msc_subrep') {
    return (
      <div className={`text-gray-500 italic ${className}`}>
        <span className="text-sm">Order totals not available</span>
      </div>
    );
  }

  // All other roles - Full financial visibility
  return (
    <div className={className}>
      {total !== undefined && (
        <div className="flex justify-between">
          <span className="text-gray-600">Order Total:</span>
          <span className="font-semibold text-gray-900">${total.toFixed(2)}</span>
        </div>
      )}

      {discount !== undefined && discount > 0 && (
        <div className="flex justify-between mt-1">
          <span className="text-gray-600">Discount:</span>
          <span className="font-semibold text-green-600">-${discount.toFixed(2)}</span>
        </div>
      )}

      {amountOwed !== undefined && (
        <div className="flex justify-between mt-1 pt-1 border-t border-gray-200">
          <span className="font-semibold text-gray-900">Amount Owed:</span>
          <span className="font-bold text-gray-900">${amountOwed.toFixed(2)}</span>
        </div>
      )}
    </div>
  );
}

export function CommissionDisplay({ userRole, commission, className = '' }: {
  userRole: UserRole;
  commission?: number;
  className?: string;
}) {
  // Only sales roles can see commission data
  if (!['msc_rep', 'msc_subrep', 'msc_admin', 'superadmin'].includes(userRole)) {
    return null;
  }

  // MSC Sub-Rep has limited commission access
  if (userRole === 'msc_subrep') {
    return (
      <div className={`text-gray-600 ${className}`}>
        <span className="text-sm">Commission: Limited Access</span>
      </div>
    );
  }

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
      <span className="font-semibold text-green-600">${commission.toFixed(2)}</span>
    </div>
  );
}
