import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import {
  FiCalendar, FiDollarSign, FiInfo, FiPlus, FiSave, FiX,
  FiFileText, FiCheck, FiChevronRight, FiChevronLeft
} from 'react-icons/fi';

const CreateOrderPage = () => {
  // Form state
  const [step, setStep] = useState(1);
  const [customerName, setCustomerName] = useState('');
  const [physicianName, setPhysicianName] = useState('');
  const [orderDate, setOrderDate] = useState(new Date());
  const [expectedCollectionDate, setExpectedCollectionDate] = useState<Date | null>(null);
  const [notes, setNotes] = useState('');
  const [paymentStatus, setPaymentStatus] = useState('pending');
  const [commissionRate, setCommissionRate] = useState(15);
  const [manufacturerPayment, setManufacturerPayment] = useState(0);
  const [mscPayment, setMscPayment] = useState(0);

  // Products
  const [products, setProducts] = useState([
    { id: 'prod-001', name: 'Wound Dressing Advanced', qCode: 'WD-ADV', price: 125.50, quantity: 1 },
    { id: 'prod-002', name: 'Antimicrobial Foam', qCode: 'AM-FOAM', price: 89.75, quantity: 0 },
    { id: 'prod-003', name: 'Silver Alginate', qCode: 'SA-100', price: 145.00, quantity: 0 },
    { id: 'prod-004', name: 'Hydrogel Sheet', qCode: 'HS-50', price: 75.25, quantity: 0 },
  ]);

  // Calculations
  const subtotal = products.reduce((sum, p) => sum + (p.price * p.quantity), 0);
  const commission = subtotal * (commissionRate / 100);
  const total = subtotal + commission;

  const handleQuantityChange = (id: string, value: string) => {
    const newQty = parseInt(value) || 0;
    setProducts(products.map(p =>
      p.id === id ? { ...p, quantity: newQty } : p
    ));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    console.log({
      customerName,
      physicianName,
      orderDate,
      expectedCollectionDate,
      notes,
      paymentStatus,
      commissionRate,
      products: products.filter(p => p.quantity > 0),
      subtotal,
      commission,
      total
    });
    alert('Order created successfully!');
  };

  const nextStep = () => setStep(prev => Math.min(prev + 1, 4));
  const prevStep = () => setStep(prev => Math.max(prev - 1, 1));

  return (
    <MainLayout>
      <Head title="Create New Order" />
   <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Order Create</h1>
            <p className="text-gray-500">
              Create wound care product orders
            </p>
          </div>

    </div>
      <div className="max-w-4xl mx-auto p-4">
        {/* Stepper */}
        <div className="flex items-center justify-between mb-8 relative">
          {/* Progress line */}
          <div className="absolute top-1/2 left-0 right-0 h-1 bg-gray-200 -z-10">
            <div
              className="h-full bg-indigo-600 transition-all duration-300"
              style={{ width: `${(step - 1) * 33.33}%` }}
            ></div>
          </div>

          {[1, 2, 3, 4].map((stepNumber) => (
            <div key={stepNumber} className="flex flex-col items-center">
              <div className={`w-10 h-10 rounded-full flex items-center justify-center ${step >= stepNumber ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-600'}`}>
                {step > stepNumber ? <FiCheck /> : stepNumber}
              </div>
              <span className={`text-xs mt-2 ${step >= stepNumber ? 'text-indigo-600 font-medium' : 'text-gray-500'}`}>
                {['Order Info', 'Products', 'Payment', 'Review'][stepNumber - 1]}
              </span>
            </div>
          ))}
        </div>

        {/* Form Content */}
        <form onSubmit={handleSubmit} className="bg-white rounded-xl shadow-lg overflow-hidden">
          {/* Step 1: Order Information */}
          {step === 1 && (
            <div className="p-6">
              <h2 className="text-xl font-semibold text-indigo-800 mb-6 flex items-center gap-2">
                <FiInfo className="text-indigo-600" />
                Order Information
              </h2>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <Input
                  label="Customer Name"
                  value={customerName}
                  onChange={setCustomerName}
                  required
                  icon={<span className="text-gray-400">👤</span>}
                />
                <Input
                  label="Physician Name"
                  value={physicianName}
                  onChange={setPhysicianName}
                  required
                  icon={<span className="text-gray-400">👨‍⚕️</span>}
                />
                <DateInput
                  label="Order Date"
                  value={orderDate}
                  onChange={setOrderDate}
                  icon={<FiCalendar className="text-gray-400" />}
                />
              </div>
            </div>
          )}

          {/* Step 2: Products */}
          {step === 2 && (
            <div className="p-6">
              <h2 className="text-xl font-semibold text-indigo-800 mb-6 flex items-center gap-2">
                <FiPlus className="text-indigo-600" />
                Select Products
              </h2>

              <div className="space-y-4">
                {products.map((product) => (
                  <div key={product.id} className="flex items-center gap-4 p-4 border rounded-lg hover:bg-gray-50 transition-colors">
                    <div className="flex-grow">
                      <h3 className="font-medium">{product.name}</h3>
                      <p className="text-sm text-gray-500">Q-Code: {product.qCode} | ${product.price.toFixed(2)}/unit</p>
                    </div>
                    <div className="flex items-center gap-2">
                      <button
                        type="button"
                        onClick={() => handleQuantityChange(product.id, String(Math.max(0, product.quantity - 1)))}
                        className="w-8 h-8 flex items-center justify-center rounded-full bg-gray-200 hover:bg-gray-300"
                      >
                        -
                      </button>
                      <input
                        type="number"
                        min="0"
                        value={product.quantity}
                        onChange={(e) => handleQuantityChange(product.id, e.target.value)}
                        className="w-16 px-2 py-1 border border-gray-300 rounded text-center"
                      />
                      <button
                        type="button"
                        onClick={() => handleQuantityChange(product.id, String(product.quantity + 1))}
                        className="w-8 h-8 flex items-center justify-center rounded-full bg-gray-200 hover:bg-gray-300"
                      >
                        +
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Step 3: Payment */}
          {step === 3 && (
            <div className="p-6">
              <h2 className="text-xl font-semibold text-indigo-800 mb-6 flex items-center gap-2">
                <FiDollarSign className="text-indigo-600" />
                Payment & Commission
              </h2>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <DateInput
                  label="Expected Collection"
                  value={expectedCollectionDate}
                  onChange={setExpectedCollectionDate}
                  icon={<FiCalendar className="text-gray-400" />}
                />
                <SelectInput
                  label="Payment Status"
                  value={paymentStatus}
                  onChange={setPaymentStatus}
                  options={[
                    { value: 'pending', label: 'Pending' },
                    { value: 'partial', label: 'Partial' },
                    { value: 'paid', label: 'Paid' }
                  ]}
                />
                <NumberInput
                  label="Commission Rate (%)"
                  value={commissionRate}
                  onChange={setCommissionRate}
                  min="0"
                  max="100"
                />
                <NumberInput
                  label="Manufacturer Payment ($)"
                  value={manufacturerPayment}
                  onChange={setManufacturerPayment}
                  min="0"
                />
              </div>
            </div>
          )}

          {/* Step 4: Review */}
          {step === 4 && (
            <div className="p-6">
              <h2 className="text-xl font-semibold text-indigo-800 mb-6 flex items-center gap-2">
                <FiFileText className="text-indigo-600" />
                Review & Submit
              </h2>

              <div className="space-y-6">
                <div className="border rounded-lg p-4">
                  <h3 className="font-medium text-lg mb-3 text-indigo-700">Order Summary</h3>
                  <div className="grid grid-cols-2 gap-4">
                    <DetailItem label="Customer Name" value={customerName} />
                    <DetailItem label="Physician Name" value={physicianName} />
                    <DetailItem label="Order Date" value={orderDate.toLocaleDateString()} />
                    <DetailItem
                      label="Expected Collection"
                      value={expectedCollectionDate?.toLocaleDateString() || 'Not set'}
                    />
                  </div>
                </div>

                <div className="border rounded-lg p-4">
                  <h3 className="font-medium text-lg mb-3 text-indigo-700">Products</h3>
                  {products.filter(p => p.quantity > 0).map(product => (
                    <div key={product.id} className="flex justify-between py-2 border-b last:border-b-0">
                      <span>{product.name} (x{product.quantity})</span>
                      <span className="font-medium">${(product.price * product.quantity).toFixed(2)}</span>
                    </div>
                  ))}
                </div>

                <div className="border rounded-lg p-4">
                  <h3 className="font-medium text-lg mb-3 text-indigo-700">Payment Summary</h3>
                  <div className="space-y-2">
                    <div className="flex justify-between">
                      <span>Subtotal:</span>
                      <span>${subtotal.toFixed(2)}</span>
                    </div>
                    <div className="flex justify-between">
                      <span>Commission ({commissionRate}%):</span>
                      <span>${commission.toFixed(2)}</span>
                    </div>
                    <div className="flex justify-between border-t pt-2 font-bold text-lg text-indigo-800">
                      <span>Total:</span>
                      <span>${total.toFixed(2)}</span>
                    </div>
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                  <div className="border rounded-lg p-3 min-h-20 bg-gray-50">
                    {notes || <span className="text-gray-400">No notes provided</span>}
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Navigation Buttons */}
          <div className="flex justify-between p-6 border-t bg-gray-50">
            <div>
              {step > 1 && (
                <button
                  type="button"
                  onClick={prevStep}
                  className="flex items-center gap-2 px-4 py-2 text-gray-700 hover:text-indigo-700"
                >
                  <FiChevronLeft />
                  Previous
                </button>
              )}
            </div>
            <div>
              {step < 4 ? (
                <button
                  type="button"
                  onClick={nextStep}
                  className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700"
                >
                  Next
                  <FiChevronRight />
                </button>
              ) : (
                <button
                  type="submit"
                  className="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
                >
                  <FiSave />
                  Submit Order
                </button>
              )}
            </div>
          </div>
        </form>
      </div>
    </MainLayout>
  );
};

// Reusable Components
const Input = ({ label, value, onChange, required = false, icon = null }: any) => (
  <div>
    <label className="block text-sm font-medium text-gray-700 mb-1">
      {label}
      {required && <span className="text-red-500">*</span>}
    </label>
    <div className="relative">
      {icon && (
        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          {icon}
        </div>
      )}
      <input
        type="text"
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className={`w-full py-2 ${icon ? 'pl-10' : 'pl-3'} pr-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500`}
        required={required}
      />
    </div>
  </div>
);

const DateInput = ({ label, value, onChange, icon = null }: any) => (
  <div>
    <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
    <div className="relative">
      {icon && (
        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          {icon}
        </div>
      )}
      <input
        type="date"
        value={value ? value.toISOString().split('T')[0] : ''}
        onChange={(e) => onChange(e.target.value ? new Date(e.target.value) : null)}
        className={`w-full py-2 ${icon ? 'pl-10' : 'pl-3'} pr-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500`}
      />
    </div>
  </div>
);

const NumberInput = ({ label, value, onChange, min = 0, max = undefined }: any) => (
  <div>
    <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
    <input
      type="number"
      min={min}
      max={max}
      value={value}
      onChange={(e) => onChange(parseFloat(e.target.value) || 0)}
      className="w-full py-2 pl-3 pr-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
    />
  </div>
);

const SelectInput = ({ label, value, onChange, options }: any) => (
  <div>
    <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
    <select
      value={value}
      onChange={(e) => onChange(e.target.value)}
      className="w-full py-2 pl-3 pr-8 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
    >
      {options.map((option: any) => (
        <option key={option.value} value={option.value}>
          {option.label}
        </option>
      ))}
    </select>
  </div>
);

const DetailItem = ({ label, value }: { label: string; value: string }) => (
  <div>
    <p className="text-sm text-gray-500">{label}</p>
    <p className="font-medium">{value}</p>
  </div>
);

export default CreateOrderPage;
