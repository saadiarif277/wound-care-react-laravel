import React, { useState, useEffect, useMemo } from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import {
  FiCalendar, FiDollarSign, FiInfo, FiPlus, FiSave, FiX,
  FiFileText, FiCheck, FiChevronRight, FiChevronLeft, FiHome, FiUser, FiMapPin,
  FiSearch, FiEdit2
} from 'react-icons/fi';
import Select from 'react-select/async';
import { components } from 'react-select';

// Types
interface Rep {
  id: string;
  name: string;
  type: 'rep' | 'sub-rep';
  parentId: string | null;
  commissionStructure: number;
}

interface Product {
  value: string;
  label: string;
  sku: string;
  nationalAsp: number;
  pricePerSqCm: number;
  qCode: string;
  graphTypes: string[];
  graphSizes: string[];
}

interface SelectOption {
  value: string;
  label: string;
  commissionStructure?: number;
  type?: 'rep' | 'sub-rep';
}

interface DoctorFacility {
  value: string;
  label: string;
  type: 'doctor' | 'facility';
}

const CreateOrderPage = () => {
  // Form state
  const [step, setStep] = useState(1);
  const [orderNumber, setOrderNumber] = useState(`ORD-${new Date().getTime()}`);
  const [doctorFacilityName, setDoctorFacilityName] = useState('');
  const [patientHash, setPatientHash] = useState('');
  const [dateOfService, setDateOfService] = useState<Date | null>(new Date());
  const [creditTerms, setCreditTerms] = useState('net60');

  // Product details
  const [sku, setSku] = useState('');
  const [nationalAsp, setNationalAsp] = useState<number>(0);
  const [pricePerSqCm, setPricePerSqCm] = useState<number>(0);
  const [expectedReimbursement, setExpectedReimbursement] = useState<number>(0);
  const [graphType, setGraphType] = useState<string>('');
  const [productName, setProductName] = useState<string>('');
  const [graphSize, setGraphSize] = useState<string>('');
  const [units, setUnits] = useState<number>(1);
  const [qCode, setQCode] = useState<string>('');

  // Payment details
  const [invoiceAmountMedicare, setInvoiceAmountMedicare] = useState<number>(0);
  const [secondaryPayer, setSecondaryPayer] = useState<string>('0');
  const [invoiceToDoc, setInvoiceToDoc] = useState<number>(0);
  const [expectedCollectionDate, setExpectedCollectionDate] = useState<Date | null>(new Date(Date.now() + 60 * 24 * 60 * 60 * 1000));
  const [paidToManufacturer, setPaidToManufacturer] = useState<number>(0);
  const [manufacturerPaidDate, setManufacturerPaidDate] = useState<Date | null>(null);
  const [balanceOwedToManufacturer, setBalanceOwedToManufacturer] = useState<number>(0);
  const [manufacturerPaymentNotes, setManufacturerPaymentNotes] = useState<string>('');

  // Commission details
  const [mscCommissionStructure, setMscCommissionStructure] = useState<number>(40);
  const [mscCommission, setMscCommission] = useState<number>(0);
  const [paymentStatus, setPaymentStatus] = useState<string>('pending');
  const [mscPaidDate, setMscPaidDate] = useState<Date | null>(null);
  const [repsTotalCommission, setRepsTotalCommission] = useState<number>(0);

  // Rep details
  const [hasRep, setHasRep] = useState<boolean>(false);
  const [repName, setRepName] = useState<string>('');
  const [repId, setRepId] = useState<string>('');
  const [repCommissionStructure, setRepCommissionStructure] = useState<number>(0);
  const [repCommissionTotal, setRepCommissionTotal] = useState<number>(0);
  const [repPaidDate, setRepPaidDate] = useState<Date | null>(null);

  const [hasSubRep, setHasSubRep] = useState<boolean>(false);
  const [subRepName, setSubRepName] = useState<string>('');
  const [subRepId, setSubRepId] = useState<string>('');
  const [subRepCommissionStructure, setSubRepCommissionStructure] = useState<number>(0);
  const [subRepCommissionTotal, setSubRepCommissionTotal] = useState<number>(0);
  const [subRepPaidDate, setSubRepPaidDate] = useState<Date | null>(null);

  const [hasSubSubRep, setHasSubSubRep] = useState<boolean>(false);
  const [subSubRepName, setSubSubRepName] = useState<string>('');
  const [subSubRepId, setSubSubRepId] = useState<string>('');
  const [subSubRepCommissionStructure, setSubSubRepCommissionStructure] = useState<number>(0);
  const [subSubRepCommissionTotal, setSubSubRepCommissionTotal] = useState<number>(0);
  const [subSubRepPaidDate, setSubSubRepPaidDate] = useState<Date | null>(null);

  // Searchable dropdowns
  const [selectedDoctorFacility, setSelectedDoctorFacility] = useState<DoctorFacility | null>(null);
  const [selectedProduct, setSelectedProduct] = useState<Product | null>(null);
  const [showNewDoctorFacilityModal, setShowNewDoctorFacilityModal] = useState<boolean>(false);
  const [showNewProductModal, setShowNewProductModal] = useState<boolean>(false);

  // New entries
  const [newDoctorFacility, setNewDoctorFacility] = useState({
    name: '',
    type: 'doctor' as 'doctor' | 'facility',
    address: '',
    phone: '',
    email: ''
  });

  const [newProduct, setNewProduct] = useState({
    name: '',
    sku: '',
    nationalAsp: 0,
    pricePerSqCm: 0,
    qCode: '',
    graphTypes: [] as string[],
    graphSizes: [] as string[]
  });

  // Dummy data
  const mockDoctorFacilities: DoctorFacility[] = [
    { value: '1', label: 'Dr. John Smith - Main Hospital', type: 'doctor' },
    { value: '2', label: 'Downtown Medical Center', type: 'facility' },
    { value: '3', label: 'Dr. Sarah Johnson - Northside Clinic', type: 'doctor' }
  ];

  const mockProducts: Product[] = [
    {
      value: '1',
      label: 'Wound Dressing Advanced',
      sku: 'WD-ADV-001',
      nationalAsp: 125.50,
      pricePerSqCm: 2.50,
      qCode: 'WD-ADV',
      graphTypes: ['Type A', 'Type B'],
      graphSizes: ['2x2', '4x4', '6x6']
    },
    {
      value: '2',
      label: 'Antimicrobial Foam',
      sku: 'AM-FOAM-001',
      nationalAsp: 89.75,
      pricePerSqCm: 1.75,
      qCode: 'AM-FOAM',
      graphTypes: ['Type C'],
      graphSizes: ['3x3', '5x5']
    }
  ];

  // Update the availableReps state with a simpler structure
  const [availableReps, setAvailableReps] = useState<Rep[]>([
    // Main Reps
    { id: 'R1', name: 'John Smith', type: 'rep', parentId: null, commissionStructure: 50 },
    { id: 'R2', name: 'Sarah Johnson', type: 'rep', parentId: null, commissionStructure: 45 },
    { id: 'R3', name: 'Michael Brown', type: 'rep', parentId: null, commissionStructure: 48 },
    { id: 'R4', name: 'Lisa Davis', type: 'rep', parentId: null, commissionStructure: 52 },
    { id: 'R5', name: 'David Wilson', type: 'rep', parentId: null, commissionStructure: 47 },

    // Sub-Reps under John Smith (R1)
    { id: 'SR1', name: 'Robert Taylor', type: 'sub-rep', parentId: 'R1', commissionStructure: 35 },
    { id: 'SR2', name: 'Emily Chen', type: 'sub-rep', parentId: 'R1', commissionStructure: 30 },
    { id: 'SR3', name: 'James Anderson', type: 'sub-rep', parentId: 'R1', commissionStructure: 32 },

    // Sub-Reps under Sarah Johnson (R2)
    { id: 'SR4', name: 'Jennifer Lee', type: 'sub-rep', parentId: 'R2', commissionStructure: 32 },
    { id: 'SR5', name: 'William Clark', type: 'sub-rep', parentId: 'R2', commissionStructure: 28 },

    // Sub-Reps under Michael Brown (R3)
    { id: 'SR6', name: 'Patricia Martinez', type: 'sub-rep', parentId: 'R3', commissionStructure: 40 },
    { id: 'SR7', name: 'Thomas Moore', type: 'sub-rep', parentId: 'R3', commissionStructure: 38 },

    // Sub-Reps under Lisa Davis (R4)
    { id: 'SR8', name: 'Richard White', type: 'sub-rep', parentId: 'R4', commissionStructure: 42 },
    { id: 'SR9', name: 'Susan Garcia', type: 'sub-rep', parentId: 'R4', commissionStructure: 38 },

    // Sub-Reps under David Wilson (R5)
    { id: 'SR10', name: 'Elizabeth Wright', type: 'sub-rep', parentId: 'R5', commissionStructure: 36 },
    { id: 'SR11', name: 'Christopher Hall', type: 'sub-rep', parentId: 'R5', commissionStructure: 34 }
  ]);

  // Update the state for selected reps
  const [selectedReps, setSelectedReps] = useState<Rep[]>([]);

  // Update the rep options to include all reps
  const repOptions = useMemo(() =>
    availableReps.map(rep => ({
      value: rep.id,
      label: `${rep.name} (${rep.commissionStructure}%)`,
      commissionStructure: rep.commissionStructure,
      type: rep.type
    })), [availableReps]);

  // Load options for dropdowns
  const loadDoctorFacilityOptions = (inputValue: string): Promise<DoctorFacility[]> => {
    return new Promise((resolve) => {
      setTimeout(() => {
        const filtered = mockDoctorFacilities.filter(option =>
          option.label.toLowerCase().includes(inputValue.toLowerCase())
        );
        resolve(filtered);
      }, 300);
    });
  };

  const loadProductOptions = (inputValue: string): Promise<Product[]> => {
    return new Promise((resolve) => {
      setTimeout(() => {
        const filtered = mockProducts.filter(option =>
          option.label.toLowerCase().includes(inputValue.toLowerCase())
        );
        resolve(filtered);
      }, 300);
    });
  };

  // Handlers
  const handleDoctorFacilityChange = (selected: DoctorFacility | null) => {
    setSelectedDoctorFacility(selected);
    setDoctorFacilityName(selected?.label || '');
  };

  const handleProductChange = (selected: Product | null) => {
    setSelectedProduct(selected);
    if (selected) {
      setProductName(selected.label);
      setSku(selected.sku);
      setNationalAsp(selected.nationalAsp);
      setPricePerSqCm(selected.pricePerSqCm);
      setQCode(selected.qCode);
      if (selected.graphTypes.length > 0) setGraphType(selected.graphTypes[0]);
      if (selected.graphSizes.length > 0) setGraphSize(selected.graphSizes[0]);
    } else {
      setProductName('');
      setSku('');
      setNationalAsp(0);
      setPricePerSqCm(0);
      setQCode('');
      setGraphType('');
      setGraphSize('');
    }
  };

  const handleNewDoctorFacilitySubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const newOption: DoctorFacility = {
      value: Date.now().toString(),
      label: `${newDoctorFacility.type === 'doctor' ? 'Dr. ' : ''}${newDoctorFacility.name}`,
      type: newDoctorFacility.type
    };
    mockDoctorFacilities.push(newOption);
    setSelectedDoctorFacility(newOption);
    setDoctorFacilityName(newOption.label);
    setShowNewDoctorFacilityModal(false);
    setNewDoctorFacility({ name: '', type: 'doctor', address: '', phone: '', email: '' });
  };

  const handleNewProductSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const newOption: Product = {
      value: Date.now().toString(),
      label: newProduct.name,
      sku: newProduct.sku,
      nationalAsp: newProduct.nationalAsp,
      pricePerSqCm: newProduct.pricePerSqCm,
      qCode: newProduct.qCode,
      graphTypes: newProduct.graphTypes,
      graphSizes: newProduct.graphSizes
    };
    mockProducts.push(newOption);
    setSelectedProduct(newOption);
    handleProductChange(newOption);
    setShowNewProductModal(false);
    setNewProduct({
      name: '',
      sku: '',
      nationalAsp: 0,
      pricePerSqCm: 0,
      qCode: '',
      graphTypes: [],
      graphSizes: []
    });
  };

  // Calculations
  const calculateTotalArea = (size: string): number => {
    if (!size) return 0;
    const [width, height] = size.split('x').map(Number);
    if (isNaN(width) || isNaN(height)) {
      console.error('Invalid graph size format:', size);
      return 0;
    }
    return width * height;
  };

  useEffect(() => {
    // Calculate total area for all units
    const totalArea = calculateTotalArea(graphSize) * (units || 0);

    // Calculate expected reimbursement (National ASP * total area)
    const reimbursement = (nationalAsp || 0) * totalArea;
    setExpectedReimbursement(reimbursement);

    // Calculate Medicare amount (80% of National ASP * total area)
    const medicareAmount = reimbursement * 0.8;
    setInvoiceAmountMedicare(medicareAmount);

    // Calculate Secondary Payer amount (20% of National ASP * total area)
    const secondaryAmount = reimbursement * 0.2;
    setSecondaryPayer(secondaryAmount.toFixed(2));

    // Calculate MSC amount (40% of total reimbursement)
    const mscAmount = reimbursement * 0.4;
    setInvoiceToDoc(mscAmount);

    // Calculate MSC commission (40% of MSC amount)
    const mscComm = mscAmount * ((mscCommissionStructure || 0) / 100);
    setMscCommission(mscComm);

    // Calculate rep commissions with proper hierarchy
    let totalRepCommission = 0;

    if (selectedReps.length > 0) {
      // Calculate main rep commission
      selectedReps.forEach(rep => {
        const repComm = mscComm * (rep.commissionStructure / 100);
        totalRepCommission += repComm;
      });

      // Update total reps commission
      setRepsTotalCommission(totalRepCommission);
    }

    // Calculate balance owed to manufacturer
    const balanceOwed = mscAmount - (paidToManufacturer || 0);
    setBalanceOwedToManufacturer(balanceOwed);
  }, [
    nationalAsp,
    units,
    graphSize,
    mscCommissionStructure,
    selectedReps,
    paidToManufacturer
  ]);

  // Rep selection handlers
  const handleRepChange = (option: SelectOption | null) => {
    console.log('Rep change:', option); // Debug log
    if (option) {
      const selected = availableReps.find(r => r.id === option.value);
      if (selected) {
        setSelectedReps([selected]);
        setRepName(selected.name);
        setRepId(selected.id);
        setRepCommissionStructure(selected.commissionStructure);
        setHasRep(true);
      }
    } else {
      setSelectedReps([]);
      setRepName('');
      setRepId('');
      setRepCommissionStructure(0);
      setHasRep(false);
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const payload = {
      order_number: orderNumber,
      doctor_facility_name: doctorFacilityName,
      patient_hash: patientHash,
      date_of_service: dateOfService?.toISOString().split('T')[0],
      credit_terms: creditTerms,
      sku: sku,
      national_asp: nationalAsp,
      price_per_sq_cm: pricePerSqCm,
      expected_reimbursement: expectedReimbursement,
      graph_type: graphType,
      product_name: productName,
      graph_size: graphSize,
      units: units,
      q_code: qCode,
      invoice_amount_medicare: invoiceAmountMedicare,
      secondary_payer: secondaryPayer,
      invoice_to_doc: invoiceToDoc,
      expected_collection_date: expectedCollectionDate?.toISOString().split('T')[0],
      paid_to_manufacturer: paidToManufacturer,
      manufacturer_paid_date: manufacturerPaidDate?.toISOString().split('T')[0],
      balance_owed_to_manufacturer: balanceOwedToManufacturer,
      manufacturer_payment_notes: manufacturerPaymentNotes,
      msc_commission_structure: mscCommissionStructure,
      msc_commission: mscCommission,
      payment_status: paymentStatus,
      msc_paid_date: mscPaidDate?.toISOString().split('T')[0],
      reps_total_commission: repsTotalCommission,
      has_rep: hasRep,
      rep_name: repName,
      rep_id: repId,
      rep_commission_structure: repCommissionStructure,
      rep_commission_total: repCommissionTotal,
      rep_paid_date: repPaidDate?.toISOString().split('T')[0],
      has_sub_rep: hasSubRep,
      sub_rep_name: subRepName,
      sub_rep_id: subRepId,
      sub_rep_commission_structure: subRepCommissionStructure,
      sub_rep_commission_total: subRepCommissionTotal,
      sub_rep_paid_date: subRepPaidDate?.toISOString().split('T')[0]
    };

    console.log('Submitting order:', payload);
    alert('Order created successfully!');
  };

  const nextStep = () => setStep(prev => Math.min(prev + 1, 4));
  const prevStep = () => setStep(prev => Math.max(prev - 1, 1));

  // Custom components for react-select
  const DoctorFacilityOption = (props: any) => (
    <components.Option {...props}>
      <div className="flex items-center gap-2">
        <span className={props.data.type === 'doctor' ? 'text-blue-600' : 'text-green-600'}>
          {props.data.type === 'doctor' ? '👨‍⚕️' : '🏥'}
        </span>
        <span>{props.data.label}</span>
      </div>
    </components.Option>
  );

  const ProductOption = (props: any) => (
    <components.Option {...props}>
      <div className="flex flex-col">
        <span className="font-medium">{props.data.label}</span>
        <span className="text-sm text-gray-500">
          SKU: {props.data.sku} | ASP: ${props.data.nationalAsp.toFixed(2)}
        </span>
      </div>
    </components.Option>
  );

  const RepOption = (props: any) => (
    <components.Option {...props}>
      <div className="flex flex-col">
        <span className="font-medium">{props.data.label}</span>
        <span className="text-sm text-gray-500">
          Commission: {props.data.commissionStructure}%
        </span>
      </div>
    </components.Option>
  );

  // Reusable form components
  const Input = ({ label, value, onChange, required = false, icon = null, disabled = false }: any) => (
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
          className={`w-full py-2 ${icon ? 'pl-10' : 'pl-3'} pr-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 ${disabled ? 'bg-gray-100' : ''}`}
          required={required}
          disabled={disabled}
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

  const NumberInput = ({ label, value, onChange, min = 0, max, required = false, disabled = false }: any) => (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">
        {label}
        {required && <span className="text-red-500">*</span>}
      </label>
      <input
        type="number"
        min={min}
        max={max}
        value={value}
        onChange={(e) => onChange(parseFloat(e.target.value) || 0)}
        className={`w-full py-2 pl-3 pr-3 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 ${disabled ? 'bg-gray-100' : ''}`}
        disabled={disabled}
      />
    </div>
  );

  const SelectInput = ({ label, value, onChange, options, icon = null }: any) => (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
      <div className="relative">
        {icon && (
          <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            {icon}
          </div>
        )}
        <select
          value={value}
          onChange={(e) => onChange(e.target.value)}
          className={`w-full py-2 ${icon ? 'pl-10' : 'pl-3'} pr-8 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500`}
        >
          {options.map((option: any) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      </div>
    </div>
  );

  const DetailItem = ({ label, value, className = '' }: { label: string; value: string; className?: string }) => (
    <div className={className}>
      <p className="text-sm text-gray-500">{label}</p>
      <p className="font-medium">{value}</p>
    </div>
  );

  return (
    <MainLayout>
      <Head title="Create Wound Care Order" />
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">Wound Care Order Create</h1>
          <p className="text-gray-500">
            Create wound care product orders with detailed reimbursement tracking
          </p>
        </div>
      </div>

      <div className="max-w-4xl mx-auto p-4">
        {/* Stepper */}
        <div className="flex items-center justify-between mb-8 relative">
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
                {['Order Info', 'Product Details', 'Payment & Commission', 'Review'][stepNumber - 1]}
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
                  label="Order Number"
                  value={orderNumber}
                  onChange={setOrderNumber}
                  required
                  disabled
                  icon={<FiFileText className="text-gray-400" />}
                />
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Doctor/Facility Name *
                  </label>
                  <div className="flex gap-2">
                    <div className="flex-grow">
                      <Select
                        value={selectedDoctorFacility}
                        onChange={handleDoctorFacilityChange}
                        loadOptions={loadDoctorFacilityOptions}
                        defaultOptions
                        components={{ Option: DoctorFacilityOption }}
                        placeholder="Search doctor or facility..."
                        className="react-select-container"
                        classNamePrefix="react-select"
                        isClearable
                  required
                      />
                    </div>
                    <button
                      type="button"
                      onClick={() => setShowNewDoctorFacilityModal(true)}
                      className="px-3 py-2 bg-indigo-100 text-indigo-700 rounded-lg hover:bg-indigo-200"
                    >
                      <FiPlus />
                    </button>
                  </div>
                </div>
                <Input
                  label="Patient Hash (De-Identified)"
                  value={patientHash}
                  onChange={setPatientHash}
                  required
                  icon={<FiUser className="text-gray-400" />}
                />
                <DateInput
                  label="Date of Service"
                  value={dateOfService}
                  onChange={setDateOfService}
                  required
                  icon={<FiCalendar className="text-gray-400" />}
                />
                <SelectInput
                  label="Credit Terms"
                  value={creditTerms}
                  onChange={setCreditTerms}
                  options={[
                    { value: 'net60', label: 'Net-60 Terms' },
                    { value: 'net30', label: 'Net-30 Terms' },
                    { value: 'net90', label: 'Net-90 Terms' }
                  ]}
                  icon={<FiDollarSign className="text-gray-400" />}
                />
              </div>
            </div>
          )}

          {/* Step 2: Product Details */}
          {step === 2 && (
            <div className="p-6">
              <h2 className="text-xl font-semibold text-indigo-800 mb-6 flex items-center gap-2">
                <FiPlus className="text-indigo-600" />
                Product Details
              </h2>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="md:col-span-2">
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Product *
                  </label>
                  <div className="flex gap-2">
                    <div className="flex-grow">
                      <Select
                        value={selectedProduct}
                        onChange={handleProductChange}
                        loadOptions={loadProductOptions}
                        defaultOptions
                        components={{ Option: ProductOption }}
                        placeholder="Search product..."
                        className="react-select-container"
                        classNamePrefix="react-select"
                        isClearable
                        required
                      />
                    </div>
                      <button
                        type="button"
                      onClick={() => setShowNewProductModal(true)}
                      className="px-3 py-2 bg-indigo-100 text-indigo-700 rounded-lg hover:bg-indigo-200"
                      >
                      <FiPlus />
                      </button>
                  </div>
                </div>
                <NumberInput
                  label="National ASP"
                  value={nationalAsp}
                  onChange={setNationalAsp}
                        min="0"
                  required
                />
                <NumberInput
                  label="Price per sq cm"
                  value={pricePerSqCm}
                  onChange={setPricePerSqCm}
                  min="0"
                  required
                />
                <Input
                  label="Graph Type"
                  value={graphType}
                  onChange={setGraphType}
                  required
                />
                <Input
                  label="Product Name"
                  value={productName}
                  onChange={setProductName}
                  required
                />
                <Input
                  label="Graph Size"
                  value={graphSize}
                  onChange={setGraphSize}
                  required
                  placeholder="e.g. 2x2, 4x4, 6x6"
                />
                <NumberInput
                  label="Units"
                  value={units}
                  onChange={setUnits}
                  min="1"
                  required
                />
                <Input
                  label="Q Code"
                  value={qCode}
                  onChange={setQCode}
                  required
                />
                <NumberInput
                  label="Expected Reimbursement"
                  value={expectedReimbursement}
                  onChange={setExpectedReimbursement}
                  min="0"
                  required
                  disabled
                />
                <NumberInput
                  label="Invoice Amount to Medicare (80%)"
                  value={invoiceAmountMedicare}
                  onChange={setInvoiceAmountMedicare}
                  min="0"
                  required
                  disabled
                />
                <NumberInput
                  label="Secondary Payer Amount (20%)"
                  value={parseFloat(secondaryPayer) || 0}
                  onChange={(value: number) => setSecondaryPayer(value.toString())}
                  min="0"
                  required
                  disabled
                />
                <NumberInput
                  label="Invoice to Doc from Manufacturer (40%)"
                  value={invoiceToDoc}
                  onChange={setInvoiceToDoc}
                  min="0"
                  required
                  disabled
                />
              </div>
            </div>
          )}

          {/* Step 3: Payment & Commission */}
          {step === 3 && (
            <div className="p-6">
              <h2 className="text-xl font-semibold text-indigo-800 mb-6 flex items-center gap-2">
                <FiDollarSign className="text-indigo-600" />
                Payment & Commission Details
              </h2>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <DateInput
                  label="Expected Collection Date"
                  value={expectedCollectionDate}
                  onChange={setExpectedCollectionDate}
                  icon={<FiCalendar className="text-gray-400" />}
                />
                <NumberInput
                  label="Paid to Manufacturer"
                  value={paidToManufacturer}
                  onChange={setPaidToManufacturer}
                  min="0"
                />
                <DateInput
                  label="Manufacturer Paid Date"
                  value={manufacturerPaidDate}
                  onChange={setManufacturerPaidDate}
                  icon={<FiCalendar className="text-gray-400" />}
                />
                <NumberInput
                  label="Balance Owed to Manufacturer"
                  value={balanceOwedToManufacturer}
                  onChange={setBalanceOwedToManufacturer}
                  min="0"
                  disabled
                />
                <div className="md:col-span-2">
                  <label className="block text-sm font-medium text-gray-700 mb-1">Notes about Manufacturer Payment</label>
                  <textarea
                    value={manufacturerPaymentNotes}
                    onChange={(e) => setManufacturerPaymentNotes(e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                    rows={3}
                  />
                </div>
                <NumberInput
                  label="MSC Commission Structure (%)"
                  value={mscCommissionStructure}
                  onChange={setMscCommissionStructure}
                  min="0"
                  max="100"
                  required
                />
                <NumberInput
                  label="MSC Commission"
                  value={mscCommission}
                  onChange={setMscCommission}
                  min="0"
                  disabled
                />
                <SelectInput
                  label="Payment Status"
                  value={paymentStatus}
                  onChange={setPaymentStatus}
                  options={[
                    { value: 'paid', label: 'Paid' },
                    { value: 'pending', label: 'Pending' },
                    { value: 'overdue', label: 'Overdue' }
                  ]}
                />
                <DateInput
                  label="MSC Paid Date"
                  value={mscPaidDate}
                  onChange={setMscPaidDate}
                  icon={<FiCalendar className="text-gray-400" />}
                />
                <NumberInput
                  label="Reps Total Commission"
                  value={repsTotalCommission}
                  onChange={setRepsTotalCommission}
                  min="0"
                  disabled
                />

                {/* Rep Selection Section */}
                <div className="md:col-span-2 border-t pt-4">
                  <h3 className="font-medium text-lg mb-4">Rep Commission Structure</h3>

                  {/* List-based Rep Selection */}
                  <div className="mb-6">
                    <label className="block text-sm font-medium text-gray-700 mb-2">Available Reps</label>
                    <div className="border rounded-lg divide-y max-h-96 overflow-y-auto">
                      {availableReps.map(rep => (
                        <div
                          key={rep.id}
                          className={`p-4 hover:bg-gray-50 cursor-pointer transition-colors ${
                            selectedReps.some(selected => selected.id === rep.id)
                              ? 'bg-indigo-50 border-l-4 border-indigo-500'
                              : ''
                          }`}
                          onClick={() => {
                            const isSelected = selectedReps.some(selected => selected.id === rep.id);
                            if (isSelected) {
                              setSelectedReps(selectedReps.filter(selected => selected.id !== rep.id));
                            } else {
                              setSelectedReps([...selectedReps, rep]);
                            }
                          }}
                        >
                          <div className="flex items-center justify-between">
                            <div>
                              <h4 className="font-medium text-gray-900">{rep.name}</h4>
                              <p className="text-sm text-gray-500">Commission: {rep.commissionStructure}%</p>
                  </div>
                            <div className="flex items-center">
                              {selectedReps.some(selected => selected.id === rep.id) ? (
                                <span className="text-indigo-600">
                                  <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                                  </svg>
                                </span>
                              ) : (
                                <span className="text-gray-400">
                                  <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                                  </svg>
                                </span>
                              )}
                </div>
                          </div>
                        </div>
                      ))}
                    </div>
                    {selectedReps.length > 0 && (
                      <div className="mt-4">
                        <p className="text-sm text-gray-500">
                          Selected Reps: {selectedReps.length}
                        </p>
                        <button
                          type="button"
                          onClick={() => setSelectedReps([])}
                          className="mt-2 text-sm text-red-600 hover:text-red-800"
                        >
                          Clear Selection
                        </button>
                      </div>
                    )}
              </div>

                  {/* Commission Details */}
                  <div className="mt-6 space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <DetailItem
                        label="MSC Commission Structure"
                        value={`${mscCommissionStructure}%`}
                      />
                      <DetailItem
                        label="MSC Commission"
                        value={`$${mscCommission.toFixed(2)}`}
                      />
                    </div>

                    {selectedReps.length > 0 && (
                      <div className="border-t pt-4">
                        <h4 className="font-medium mb-2">Selected Reps Commission Details</h4>
                        <div className="space-y-4">
                          {selectedReps.map(rep => (
                            <div key={rep.id} className="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-gray-50 rounded-lg">
                              <DetailItem
                                label="Rep Name"
                                value={rep.name}
                              />
                              <DetailItem
                                label="Commission Structure"
                                value={`${rep.commissionStructure}%`}
                              />
                              <DetailItem
                                label="Commission Total"
                                value={`$${(mscCommission * (rep.commissionStructure / 100)).toFixed(2)}`}
                              />
                            </div>
                          ))}
                        </div>
                      </div>
                    )}
                  </div>
                </div>
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
                {/* Order Information */}
                <div className="border rounded-lg p-4">
                  <h3 className="font-medium text-lg mb-3 text-indigo-700">Order Information</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <DetailItem label="Order Number" value={orderNumber} />
                    <DetailItem label="Doctor/Facility Name" value={doctorFacilityName} />
                    <DetailItem label="Patient Hash" value={patientHash} />
                    <DetailItem label="Date of Service" value={dateOfService?.toLocaleDateString() || ''} />
                    <DetailItem label="Credit Terms" value={creditTerms.toUpperCase()} />
                  </div>
                </div>

                {/* Product Details */}
                <div className="border rounded-lg p-4">
                  <h3 className="font-medium text-lg mb-3 text-indigo-700">Product Details</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <DetailItem label="SKU" value={sku} />
                    <DetailItem label="National ASP" value={`$${nationalAsp.toFixed(2)}`} />
                    <DetailItem label="Price per sq cm" value={`$${pricePerSqCm.toFixed(2)}`} />
                    <DetailItem label="Graph Type" value={graphType} />
                    <DetailItem label="Product Name" value={productName} />
                    <DetailItem label="Graph Size" value={graphSize} />
                    <DetailItem label="Units" value={units.toString()} />
                    <DetailItem label="Q Code" value={qCode} />
                    <DetailItem label="Expected Reimbursement" value={`$${expectedReimbursement.toFixed(2)}`} />
                    <DetailItem label="Invoice Amount to Medicare (80%)" value={`$${invoiceAmountMedicare.toFixed(2)}`} />
                    <DetailItem label="Secondary Payer Amount (20%)" value={`$${parseFloat(secondaryPayer).toFixed(2)}`} />
                    <DetailItem label="Invoice to Doc from Manufacturer (40%)" value={`$${invoiceToDoc.toFixed(2)}`} />
                  </div>
                </div>

                {/* Payment & Commission Summary */}
                <div className="border rounded-lg p-4">
                  <h3 className="font-medium text-lg mb-3 text-indigo-700">Payment & Commission Summary</h3>
                  <div className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <DetailItem label="Expected Collection Date" value={expectedCollectionDate?.toLocaleDateString() || ''} />
                      <DetailItem label="Payment Status" value={paymentStatus.charAt(0).toUpperCase() + paymentStatus.slice(1)} />
                      <DetailItem label="Paid to Manufacturer" value={`$${paidToManufacturer.toFixed(2)}`} />
                      <DetailItem label="Balance Owed to Manufacturer" value={`$${balanceOwedToManufacturer.toFixed(2)}`} />
                      <DetailItem label="MSC Commission Structure" value={`${mscCommissionStructure}%`} />
                      <DetailItem label="MSC Commission" value={`$${mscCommission.toFixed(2)}`} />
                      <DetailItem label="Reps Total Commission" value={`$${repsTotalCommission.toFixed(2)}`} />
                    </div>
                  </div>
                </div>

                {manufacturerPaymentNotes && (
                <div className="border rounded-lg p-4">
                    <h3 className="font-medium text-lg mb-3 text-indigo-700">Manufacturer Payment Notes</h3>
                    <p className="whitespace-pre-wrap">{manufacturerPaymentNotes}</p>
                    </div>
                )}
                    </div>
                    </div>
          )}

          {/* New Doctor/Facility Modal */}
          {showNewDoctorFacilityModal && (
            <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4">
              <div className="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 className="text-lg font-semibold mb-4">Add New Doctor/Facility</h3>
                <form onSubmit={handleNewDoctorFacilitySubmit}>
                  <div className="space-y-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Type</label>
                      <select
                        value={newDoctorFacility.type}
                        onChange={(e) => setNewDoctorFacility(prev => ({ ...prev, type: e.target.value as 'doctor' | 'facility' }))}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg"
                        required
                      >
                        <option value="doctor">Doctor</option>
                        <option value="facility">Facility</option>
                      </select>
                    </div>
                    <Input
                      label="Name"
                      value={newDoctorFacility.name}
                      onChange={(value: string) => setNewDoctorFacility(prev => ({ ...prev, name: value }))}
                      required
                    />
                    <Input
                      label="Address"
                      value={newDoctorFacility.address}
                      onChange={(value: string) => setNewDoctorFacility(prev => ({ ...prev, address: value }))}
                    />
                    <Input
                      label="Phone"
                      value={newDoctorFacility.phone}
                      onChange={(value: string) => setNewDoctorFacility(prev => ({ ...prev, phone: value }))}
                    />
                    <Input
                      label="Email"
                      value={newDoctorFacility.email}
                      onChange={(value: string) => setNewDoctorFacility(prev => ({ ...prev, email: value }))}
                      type="email"
                    />
                    </div>
                  <div className="mt-6 flex justify-end gap-2">
                    <button
                      type="button"
                      onClick={() => setShowNewDoctorFacilityModal(false)}
                      className="px-4 py-2 text-gray-700 hover:text-gray-900"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      className="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700"
                    >
                      Add
                    </button>
                  </div>
                </form>
                </div>
            </div>
          )}

          {/* New Product Modal */}
          {showNewProductModal && (
            <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4">
              <div className="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 className="text-lg font-semibold mb-4">Add New Product</h3>
                <form onSubmit={handleNewProductSubmit}>
                  <div className="space-y-4">
                    <Input
                      label="Product Name"
                      value={newProduct.name}
                      onChange={(value: string) => setNewProduct(prev => ({ ...prev, name: value }))}
                      required
                    />
                    <Input
                      label="SKU"
                      value={newProduct.sku}
                      onChange={(value: string) => setNewProduct(prev => ({ ...prev, sku: value }))}
                      required
                    />
                    <NumberInput
                      label="National ASP"
                      value={newProduct.nationalAsp}
                      onChange={(value: number) => setNewProduct(prev => ({ ...prev, nationalAsp: value }))}
                      min="0"
                      required
                    />
                    <NumberInput
                      label="Price per sq cm"
                      value={newProduct.pricePerSqCm}
                      onChange={(value: number) => setNewProduct(prev => ({ ...prev, pricePerSqCm: value }))}
                      min="0"
                      required
                    />
                    <Input
                      label="Q Code"
                      value={newProduct.qCode}
                      onChange={(value: string) => setNewProduct(prev => ({ ...prev, qCode: value }))}
                      required
                    />
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Graph Types</label>
                      <input
                        type="text"
                        value={newProduct.graphTypes.join(', ')}
                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => setNewProduct(prev => ({
                          ...prev,
                          graphTypes: e.target.value.split(',').map(t => t.trim()).filter(Boolean)
                        }))}
                        placeholder="Type A, Type B, Type C"
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg"
                      />
                </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Graph Sizes</label>
                      <input
                        type="text"
                        value={newProduct.graphSizes.join(', ')}
                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => setNewProduct(prev => ({
                          ...prev,
                          graphSizes: e.target.value.split(',').map(s => s.trim()).filter(Boolean)
                        }))}
                        placeholder="2x2, 4x4, 6x6"
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg"
                      />
                    </div>
                  </div>
                  <div className="mt-6 flex justify-end gap-2">
                    <button
                      type="button"
                      onClick={() => setShowNewProductModal(false)}
                      className="px-4 py-2 text-gray-700 hover:text-gray-900"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      className="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700"
                    >
                      Add
                    </button>
                  </div>
                </form>
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

export default CreateOrderPage;