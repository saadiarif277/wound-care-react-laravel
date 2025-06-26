
import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { OrderTable } from './OrderTable';
import { OrderDetailView } from './OrderDetailView';
import { AdminOrderData, DashboardFilter, AdminViewMode } from '../../types/adminTypes';
import { mockAdminOrders } from '../data/mockAdminOrderData';
import { Plus } from 'lucide-react';

export const OrderDashboard: React.FC = () => {
  const [viewMode, setViewMode] = useState<AdminViewMode>('dashboard');
  const [selectedOrder, setSelectedOrder] = useState<AdminOrderData | null>(null);
  const [filter, setFilter] = useState<DashboardFilter>('requiring-action');

  const filteredOrders = mockAdminOrders.filter(order => 
    filter === 'requiring-action' ? order.actionRequired : true
  );

  const handleRowClick = (order: AdminOrderData) => {
    setSelectedOrder(order);
    setViewMode('detail');
  };

  const handleBackToDashboard = () => {
    setViewMode('dashboard');
    setSelectedOrder(null);
  };

  const handleStatusChange = (orderNumber: string, newStatus: any, notes?: string) => {
    console.log(`Status changed for ${orderNumber} to ${newStatus}`, notes);
    // Implementation would update the order status
  };

  const handleGenerateIVR = (orderNumber: string, skipIVR?: boolean, reason?: string) => {
    console.log(`Generate IVR for ${orderNumber}`, { skipIVR, reason });
    // Implementation would generate IVR
  };

  const handleSubmitToManufacturer = (orderNumber: string) => {
    console.log(`Submit to manufacturer: ${orderNumber}`);
    // Implementation would submit to manufacturer
  };

  const handleUploadDocument = (orderNumber: string, file: File, documentType: string) => {
    console.log(`Upload document for ${orderNumber}`, { file: file.name, documentType });
    // Implementation would handle file upload
  };

  const handleUpdateIVRStatus = (orderNumber: string, status: any, notes?: string) => {
    console.log(`Update IVR status for ${orderNumber} to ${status}`, notes);
    // Implementation would update IVR status
  };

  const handleUpdateOrderFormStatus = (orderNumber: string, status: any, notes?: string) => {
    console.log(`Update Order Form status for ${orderNumber} to ${status}`, notes);
    // Implementation would update Order Form status
  };

  const handleUploadIVRResults = (orderNumber: string, file: File) => {
    console.log(`Upload IVR results for ${orderNumber}`, file.name);
    // Implementation would handle IVR results upload
  };

  if (viewMode === 'detail' && selectedOrder) {
    return (
      <OrderDetailView
        order={selectedOrder}
        onBack={handleBackToDashboard}
        onStatusChange={handleStatusChange}
        onGenerateIVR={handleGenerateIVR}
        onSubmitToManufacturer={handleSubmitToManufacturer}
        onUploadDocument={handleUploadDocument}
        onUpdateIVRStatus={handleUpdateIVRStatus}
        onUpdateOrderFormStatus={handleUpdateOrderFormStatus}
        onUploadIVRResults={handleUploadIVRResults}
      />
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
      <div className="container mx-auto px-4 py-8 max-w-7xl">
        {/* Header */}
        <div className="flex justify-between items-center mb-8">
          <div>
            <h1 className="text-3xl font-bold text-slate-900 mb-2">
              Order Management Center
            </h1>
            <p className="text-muted-foreground">
              Manage provider-submitted orders and track order lifecycle
            </p>
          </div>
          <Button className="bg-primary hover:bg-primary/90">
            <Plus className="h-4 w-4 mr-2" />
            Create Order
          </Button>
        </div>

        {/* Filter Tabs */}
        <div className="sticky top-0 z-10 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 border-b mb-6">
          <div className="flex space-x-1 p-1 bg-muted rounded-lg w-fit">
            <button
              onClick={() => setFilter('requiring-action')}
              className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                filter === 'requiring-action'
                  ? 'bg-background text-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              Orders Requiring Action ({filteredOrders.filter(o => o.actionRequired).length})
            </button>
            <button
              onClick={() => setFilter('all-orders')}
              className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                filter === 'all-orders'
                  ? 'bg-background text-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              All Orders ({mockAdminOrders.length})
            </button>
          </div>
        </div>

        {/* Orders Table */}
        <OrderTable orders={filteredOrders} onRowClick={handleRowClick} />
      </div>
    </div>
  );
};
