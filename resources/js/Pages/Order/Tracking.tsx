import React from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import TrackingInfo from '@/Components/Order/TrackingInfo';
import { ArrowLeft, Package, FileText, Calendar, Building2, User } from 'lucide-react';

interface OrderTrackingProps {
  order: {
    id: string;
    order_number: string;
    patient_display_id: string;
    order_status: string;
    expected_service_date: string;
    submitted_at: string;
    tracking_number?: string;
    tracking_carrier?: string;
    shipped_at?: string;
    delivered_at?: string;
    provider: {
      name: string;
      email: string;
    };
    facility: {
      name: string;
      city: string;
      state: string;
    };
    products: Array<{
      name: string;
      quantity: number;
      size?: string;
    }>;
  };
}

export default function OrderTracking({ order }: OrderTrackingProps) {
  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  };

  const getStatusBadge = (status: string) => {
    const statusConfig: { [key: string]: { label: string; className: string } } = {
      submitted: { label: 'Submitted', className: 'bg-gray-100 text-gray-800' },
      processing: { label: 'Processing', className: 'bg-blue-100 text-blue-800' },
      approved: { label: 'Approved', className: 'bg-green-100 text-green-800' },
      submitted_to_manufacturer: { label: 'Sent to Manufacturer', className: 'bg-purple-100 text-purple-800' },
      shipped: { label: 'Shipped', className: 'bg-indigo-100 text-indigo-800' },
      delivered: { label: 'Delivered', className: 'bg-green-100 text-green-800' },
    };

    const config = statusConfig[status] || { label: status, className: 'bg-gray-100 text-gray-800' };

    return (
      <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${config.className}`}>
        {config.label}
      </span>
    );
  };

  return (
    <MainLayout>
      <Head title={`Order Tracking - ${order.order_number}`} />

      <div className="w-full min-h-screen bg-gray-50 dark:bg-gray-900 py-6 px-4 sm:px-6 lg:px-8">
        {/* Header */}
        <div className="mb-6">
          <Link
            href="/orders/center"
            className="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mb-4"
          >
            <ArrowLeft className="w-4 h-4 mr-1" />
            Back to Orders
          </Link>
          
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                Order {order.order_number}
              </h1>
              <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Track your order status and delivery information
              </p>
            </div>
            {getStatusBadge(order.order_status)}
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Order Details */}
          <div className="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h2 className="text-lg font-medium text-gray-900 dark:text-white mb-4 flex items-center">
              <Package className="w-5 h-5 mr-2 text-gray-500" />
              Order Details
            </h2>
            
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-500 dark:text-gray-400">
                  Patient ID
                </label>
                <p className="mt-1 text-sm text-gray-900 dark:text-white">
                  {order.patient_display_id}
                </p>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-500 dark:text-gray-400">
                  Service Date
                </label>
                <p className="mt-1 text-sm text-gray-900 dark:text-white">
                  {formatDate(order.expected_service_date)}
                </p>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-500 dark:text-gray-400">
                  Submitted Date
                </label>
                <p className="mt-1 text-sm text-gray-900 dark:text-white">
                  {formatDate(order.submitted_at)}
                </p>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-500 dark:text-gray-400">
                  Products
                </label>
                <div className="mt-1 space-y-1">
                  {order.products.map((product, index) => (
                    <p key={index} className="text-sm text-gray-900 dark:text-white">
                      • {product.name} - Qty: {product.quantity}
                      {product.size && ` (${product.size})`}
                    </p>
                  ))}
                </div>
              </div>
            </div>
          </div>

          {/* Tracking Information */}
          <TrackingInfo
            trackingNumber={order.tracking_number}
            carrier={order.tracking_carrier}
            shippedAt={order.shipped_at}
            deliveredAt={order.delivered_at}
            orderStatus={order.order_status}
          />
        </div>

        {/* Facility Information */}
        <div className="mt-6 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
          <h2 className="text-lg font-medium text-gray-900 dark:text-white mb-4 flex items-center">
            <Building2 className="w-5 h-5 mr-2 text-gray-500" />
            Delivery Information
          </h2>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-500 dark:text-gray-400">
                Facility
              </label>
              <p className="mt-1 text-sm text-gray-900 dark:text-white">
                {order.facility.name}
              </p>
              <p className="text-sm text-gray-600 dark:text-gray-300">
                {order.facility.city}, {order.facility.state}
              </p>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-500 dark:text-gray-400">
                Provider
              </label>
              <p className="mt-1 text-sm text-gray-900 dark:text-white">
                {order.provider.name}
              </p>
              <p className="text-sm text-gray-600 dark:text-gray-300">
                {order.provider.email}
              </p>
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
}