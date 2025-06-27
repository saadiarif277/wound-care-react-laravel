import React, { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { User, Order } from '@/types';
import { FiDollarSign, FiCalendar, FiCreditCard, FiFileText, FiCheck } from 'react-icons/fi';
import { formatCurrency } from '@/lib/utils';
import TextInput from '@/Components/Form/TextInput';
import SelectInput from '@/Components/Form/SelectInput';
import DateInput from '@/Components/Form/DateInput';
import TextAreaInput from '@/Components/Form/TextAreaInput';
import LoadingButton from '@/Components/Button/LoadingButton';
import { Card } from '@/Components/ui/card';

interface PaymentsPageProps {
  providers: User[];
  flash: {
    success?: string;
    error?: string;
  };
}

interface OrderWithBalance extends Order {
  total_amount: number;
  paid_amount: number;
  payment_status: 'unpaid' | 'partial' | 'paid';
  outstanding_balance: number;
}

export default function PaymentsIndex({ providers, flash }: PaymentsPageProps) {
  const [selectedProviderId, setSelectedProviderId] = useState('');
  const [providerOrders, setProviderOrders] = useState<OrderWithBalance[]>([]);
  const [selectedOrderId, setSelectedOrderId] = useState('');
  const [loadingOrders, setLoadingOrders] = useState(false);
  const [processing, setProcessing] = useState(false);
  
  const [formData, setFormData] = useState({
    amount: '',
    payment_method: 'check',
    reference_number: '',
    payment_date: new Date().toISOString().split('T')[0],
    notes: ''
  });

  const selectedProvider = providers.find(p => p.id === parseInt(selectedProviderId));
  const selectedOrder = providerOrders.find(o => o.id === parseInt(selectedOrderId));

  // Fetch orders when provider is selected
  useEffect(() => {
    if (selectedProviderId) {
      setLoadingOrders(true);
      fetch(`/api/providers/${selectedProviderId}/outstanding-orders`)
        .then(res => res.json())
        .then(data => {
          setProviderOrders(data.orders);
          setSelectedOrderId('');
          setLoadingOrders(false);
        })
        .catch(err => {
          console.error('Error fetching orders:', err);
          setLoadingOrders(false);
        });
    } else {
      setProviderOrders([]);
      setSelectedOrderId('');
    }
  }, [selectedProviderId]);

  // Auto-fill amount when order is selected
  useEffect(() => {
    if (selectedOrder) {
      setFormData(prev => ({
        ...prev,
        amount: selectedOrder.outstanding_balance.toFixed(2)
      }));
    }
  }, [selectedOrderId]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!selectedProviderId || !selectedOrderId || !formData.amount) {
      alert('Please select a provider, order, and enter an amount');
      return;
    }

    setProcessing(true);

    router.post('/admin/payments', {
      provider_id: selectedProviderId,
      order_id: selectedOrderId,
      amount: parseFloat(formData.amount),
      payment_method: formData.payment_method,
      reference_number: formData.reference_number,
      payment_date: formData.payment_date,
      notes: formData.notes
    }, {
      onSuccess: () => {
        // Reset form
        setFormData({
          amount: '',
          payment_method: 'check',
          reference_number: '',
          payment_date: new Date().toISOString().split('T')[0],
          notes: ''
        });
        setSelectedProviderId('');
        setSelectedOrderId('');
        setProviderOrders([]);
      },
      onFinish: () => {
        setProcessing(false);
      }
    });
  };

  return (
    <MainLayout>
      <Head title="Record Payment" />
      
      <div className="max-w-4xl mx-auto">
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">Record Payment</h1>
          <p className="mt-2 text-gray-600">Record payments from providers to reduce their outstanding balances</p>
        </div>

        {flash.success && (
          <div className="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg flex items-center">
            <FiCheck className="w-5 h-5 mr-2" />
            {flash.success}
          </div>
        )}

        {flash.error && (
          <div className="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
            {flash.error}
          </div>
        )}

        {(!providers || providers.length === 0) && (
          <div className="mb-6 p-4 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded-lg">
            <p className="font-semibold">No providers available</p>
            <p className="text-sm mt-1">There are currently no providers in the system or no providers with outstanding balances.</p>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Provider Selection */}
          <Card>
            <div className="p-6">
              <h2 className="text-lg font-semibold mb-4 flex items-center">
                <FiFileText className="w-5 h-5 mr-2" />
                Select Provider
              </h2>
              
              <SelectInput
                label="Provider"
                value={selectedProviderId}
                onChange={(e) => setSelectedProviderId(e.target.value)}
                required
              >
                <option value="">Select a provider...</option>
                {providers && providers.length > 0 ? (
                  providers.map(provider => (
                    <option key={provider.id} value={provider.id}>
                      {provider.name} - {provider.email}
                    </option>
                  ))
                ) : (
                  <option value="" disabled>No providers found</option>
                )}
              </SelectInput>

              {selectedProvider && (
                <div className="mt-4 p-4 bg-gray-50 rounded-lg">
                  <p className="text-sm text-gray-600">NPI: {selectedProvider.npi_number || 'N/A'}</p>
                  <p className="text-sm text-gray-600">Organization: {selectedProvider.current_organization?.name || 'N/A'}</p>
                </div>
              )}
            </div>
          </Card>

          {/* Order Selection */}
          {selectedProviderId && (
            <Card>
              <div className="p-6">
                <h2 className="text-lg font-semibold mb-4 flex items-center">
                  <FiDollarSign className="w-5 h-5 mr-2" />
                  Select Order
                </h2>
                
                {loadingOrders ? (
                  <div className="text-center py-8 text-gray-500">Loading orders...</div>
                ) : providerOrders.length === 0 ? (
                  <div className="text-center py-8 text-gray-500">No outstanding orders found for this provider</div>
                ) : (
                  <>
                    <SelectInput
                      label="Order"
                      value={selectedOrderId}
                      onChange={(e) => setSelectedOrderId(e.target.value)}
                      required
                    >
                      <option value="">Select an order...</option>
                      {providerOrders.map(order => (
                        <option key={order.id} value={order.id}>
                          Order #{order.order_number} - {formatCurrency(order.outstanding_balance)} outstanding
                        </option>
                      ))}
                    </SelectInput>

                    {selectedOrder && (
                      <div className="mt-4 p-4 bg-gray-50 rounded-lg space-y-2">
                        <div className="flex justify-between">
                          <span className="text-sm text-gray-600">Total Amount:</span>
                          <span className="font-semibold">{formatCurrency(selectedOrder.total_amount)}</span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-sm text-gray-600">Paid Amount:</span>
                          <span className="font-semibold">{formatCurrency(selectedOrder.paid_amount)}</span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-sm text-gray-600">Outstanding Balance:</span>
                          <span className="font-semibold text-red-600">{formatCurrency(selectedOrder.outstanding_balance)}</span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-sm text-gray-600">Status:</span>
                          <span className={`px-2 py-1 text-xs rounded-full ${
                            selectedOrder.payment_status === 'paid' ? 'bg-green-100 text-green-700' :
                            selectedOrder.payment_status === 'partial' ? 'bg-yellow-100 text-yellow-700' :
                            'bg-red-100 text-red-700'
                          }`}>
                            {selectedOrder.payment_status.toUpperCase()}
                          </span>
                        </div>
                      </div>
                    )}
                  </>
                )}
              </div>
            </Card>
          )}

          {/* Payment Details */}
          {selectedOrderId && (
            <Card>
              <div className="p-6">
                <h2 className="text-lg font-semibold mb-4 flex items-center">
                  <FiCreditCard className="w-5 h-5 mr-2" />
                  Payment Details
                </h2>
                
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <TextInput
                    label="Payment Amount"
                    type="number"
                    step="0.01"
                    min="0.01"
                    max={selectedOrder?.outstanding_balance}
                    value={formData.amount}
                    onChange={(e) => setFormData(prev => ({ ...prev, amount: e.target.value }))}
                    required
                    placeholder="0.00"
                  />

                  <SelectInput
                    label="Payment Method"
                    value={formData.payment_method}
                    onChange={(e) => setFormData(prev => ({ ...prev, payment_method: e.target.value }))}
                    required
                  >
                    <option value="check">Check</option>
                    <option value="wire">Wire Transfer</option>
                    <option value="ach">ACH</option>
                    <option value="credit_card">Credit Card</option>
                    <option value="other">Other</option>
                  </SelectInput>

                  <TextInput
                    label="Reference Number"
                    value={formData.reference_number}
                    onChange={(e) => setFormData(prev => ({ ...prev, reference_number: e.target.value }))}
                    placeholder="Check # or Transaction ID"
                  />

                  <DateInput
                    label="Payment Date"
                    value={formData.payment_date}
                    onChange={(value) => setFormData(prev => ({ ...prev, payment_date: value }))}
                    required
                  />
                </div>

                <div className="mt-4">
                  <TextAreaInput
                      label="Notes (Optional)"
                      value={formData.notes}
                      onChange={(value) => setFormData(prev => ({ ...prev, notes: value }))}
                      rows={3}
                      placeholder="Any additional notes about this payment..."
                  />
                </div>

                <div className="mt-6 flex justify-end">
                  <LoadingButton
                    type="submit"
                    loading={processing}
                    className="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700"
                  >
                    Record Payment
                  </LoadingButton>
                </div>
              </div>
            </Card>
          )}
        </form>
      </div>
    </MainLayout>
  );
}