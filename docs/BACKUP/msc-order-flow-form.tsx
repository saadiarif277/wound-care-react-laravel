import React, { useState, useEffect } from 'react';
import { ChevronRight, AlertCircle, Check, Clock, Package, User, CreditCard, Stethoscope, ShoppingCart, Plus, Trash2, Info } from 'lucide-react';

const MSCOrderFlowForm = () => {
  const [currentSection, setCurrentSection] = useState(0);
  const [formData, setFormData] = useState({
    requestType: 'new_request',
    providerId: '',
    facilityId: '',
    salesRepId: 'AUTO-12345',
    patientSearchQuery: '',
    patientId: '',
    patientFirstName: '',
    patientLastName: '',
    patientDOB: '',
    patientGender: 'male',
    patientAddressLine1: '',
    patientAddressLine2: '',
    patientCity: '',
    patientState: '',
    patientZipCode: '',
    patientPhone: '',
    patientEmail: '',
    serviceDate: '',
    shippingSpeed: 'standard_next',
    deliveryDate: '',
    patientIsSubscriber: 'yes',
    primaryInsuranceName: '',
    primaryPolicyNumber: '',
    primaryPlanType: 'ffs',
    hasSecondaryInsurance: false,
    secondaryInsuranceName: '',
    secondaryPolicyNumber: '',
    secondarySubscriberName: '',
    secondarySubscriberDOB: '',
    secondaryPayerPhone: '',
    secondaryPlanType: '',
    priorAuthPermission: true,
    woundTypes: [],
    woundOtherSpecify: '',
    woundLocation: '',
    woundLocationDetails: '',
    yellowDiagnosisCode: '',
    orangeDiagnosisCode: '',
    woundSizeLength: '',
    woundSizeWidth: '',
    woundSizeDepth: '',
    woundDuration: '',
    previousTreatments: '',
    applicationCptCodes: [],
    priorApplications: '',
    anticipatedApplications: '',
    placeOfService: '11',
    medicarePartBAuthorized: false,
    snfDays: '',
    hospiceStatus: false,
    partAStatus: false,
    globalPeriodStatus: false,
    globalPeriodCPT: '',
    globalPeriodSurgeryDate: '',
    selectedProduct: '',
    orderItems: []
  });

  const sections = [
    { title: 'Context & Request', icon: User, estimatedTime: '15 seconds' },
    { title: 'Patient & Shipping', icon: Package, estimatedTime: '25 seconds' },
    { title: 'Insurance', icon: CreditCard, estimatedTime: '25 seconds' },
    { title: 'Clinical & Billing', icon: Stethoscope, estimatedTime: '20 seconds' },
    { title: 'Product Selection', icon: ShoppingCart, estimatedTime: '15 seconds' }
  ];

  // Sample data for dropdowns
  const providers = [
    { id: 'prov1', name: 'Dr. Sarah Johnson', credentials: 'MD', npi: '1234567890' },
    { id: 'prov2', name: 'Dr. Michael Chen', credentials: 'DO', npi: '0987654321' }
  ];

  const facilities = [
    { id: 'fac1', name: 'Healing Hands Wound Care', address: '123 Medical Center Dr' },
    { id: 'fac2', name: 'Advanced Wound Clinic', address: '456 Healthcare Blvd' }
  ];

  const insuranceCarriers = [
    'Medicare Part B',
    'Blue Cross Blue Shield',
    'Aetna',
    'United Healthcare',
    'Humana'
  ];

  // Sample diagnosis codes (subset for demo)
  const yellowCodes = [
    { code: 'E11.621', description: 'Type 2 diabetes mellitus with foot ulcer' },
    { code: 'E11.622', description: 'Type 2 diabetes mellitus with other skin ulcer' },
    { code: 'E10.621', description: 'Type 1 diabetes mellitus with foot ulcer' }
  ];

  const orangeCodes = [
    { code: 'L97.411', description: 'Non-pressure chronic ulcer of right heel and midfoot limited to breakdown of skin' },
    { code: 'L97.412', description: 'Non-pressure chronic ulcer of right heel and midfoot with fat layer exposed' },
    { code: 'L97.511', description: 'Non-pressure chronic ulcer of other part of right foot limited to breakdown of skin' }
  ];

  // Products that the provider has been onboarded with
  const providerOnboardedProducts = {
    'prov1': ['Q4271', 'Q4272', 'Q4273', 'Q4274'],
    'prov2': ['Q4271', 'Q4275', 'Q4276']
  };

  // All available products with their details
  const allProducts = [
    { 
      qCode: 'Q4271', 
      name: 'XCELLERATE', 
      manufacturer: 'Advanced Biologics',
      sizes: ['2×2 cm', '2×4 cm', '4×4 cm', '4×6 cm', '5×5 cm', '6×6 cm'],
      pricePerSqCm: 45.00
    },
    { 
      qCode: 'Q4272', 
      name: 'ADVANCED HEALING MATRIX', 
      manufacturer: 'BioHeal Corp',
      sizes: ['3×3 cm', '4×4 cm', '4×6 cm', '5×7 cm', '6×6 cm', '8×8 cm'],
      pricePerSqCm: 52.00
    },
    { 
      qCode: 'Q4273', 
      name: 'DERMACELL AWM', 
      manufacturer: 'Tissue Tech',
      sizes: ['2×2 cm', '3×3 cm', '4×4 cm', '5×5 cm', '7×7 cm'],
      pricePerSqCm: 48.00
    },
    { 
      qCode: 'Q4274', 
      name: 'BIOWOUND PLUS', 
      manufacturer: 'Regenerative Solutions',
      sizes: ['2×3 cm', '4×4 cm', '5×5 cm', '6×8 cm', '10×10 cm'],
      pricePerSqCm: 55.00
    },
    { 
      qCode: 'Q4275', 
      name: 'FLEXHD', 
      manufacturer: 'MTF Biologics',
      sizes: ['2×2 cm', '3×3 cm', '4×5 cm', '5×5 cm', '6×8 cm'],
      pricePerSqCm: 50.00
    },
    { 
      qCode: 'Q4276', 
      name: 'NEOX CORD RT', 
      manufacturer: 'Amniox Medical',
      sizes: ['2×2 cm', '2×3 cm', '3×3 cm', '3×6 cm', '4×6 cm'],
      pricePerSqCm: 58.00
    }
  ];

  const handleInputChange = (field, value) => {
    setFormData(prev => ({ ...prev, [field]: value }));
  };

  const handleNext = () => {
    if (currentSection < sections.length - 1) {
      setCurrentSection(currentSection + 1);
    }
  };

  const handlePrevious = () => {
    if (currentSection > 0) {
      setCurrentSection(currentSection - 1);
    }
  };

  // Get products available to the selected provider
  const getAvailableProducts = () => {
    if (!formData.providerId) return [];
    const onboardedCodes = providerOnboardedProducts[formData.providerId] || [];
    return allProducts.filter(product => onboardedCodes.includes(product.qCode));
  };

  // Add a new product line item
  const addProductLine = () => {
    const newItem = {
      id: Date.now(),
      productCode: '',
      size: '',
      quantity: 1,
      unitPrice: 0,
      totalPrice: 0
    };
    setFormData(prev => ({
      ...prev,
      orderItems: [...prev.orderItems, newItem]
    }));
  };

  // Update a specific product line item
  const updateOrderItem = (itemId, field, value) => {
    setFormData(prev => ({
      ...prev,
      orderItems: prev.orderItems.map(item => {
        if (item.id === itemId) {
          const updated = { ...item, [field]: value };
          
          // Calculate price when product or size changes
          if (field === 'productCode' || field === 'size' || field === 'quantity') {
            const product = allProducts.find(p => p.qCode === updated.productCode);
            if (product && updated.size) {
              const [length, width] = updated.size.split('×').map(s => parseFloat(s));
              const sqCm = length * width;
              updated.unitPrice = sqCm * product.pricePerSqCm;
              updated.totalPrice = updated.unitPrice * updated.quantity;
            }
          }
          
          return updated;
        }
        return item;
      })
    }));
  };

  // Remove a product line item
  const removeOrderItem = (itemId) => {
    setFormData(prev => ({
      ...prev,
      orderItems: prev.orderItems.filter(item => item.id !== itemId)
    }));
  };

  // Calculate total order value
  const calculateOrderTotal = () => {
    return formData.orderItems.reduce((sum, item) => sum + (item.totalPrice || 0), 0);
  };

  // Calculate delivery date based on shipping speed
  useEffect(() => {
    if (formData.serviceDate && formData.shippingSpeed) {
      const serviceDate = new Date(formData.serviceDate);
      const today = new Date();
      const daysToAdd = formData.shippingSpeed === 'standard_2day' ? 2 : 1;
      
      const deliveryDate = new Date(today);
      deliveryDate.setDate(deliveryDate.getDate() + daysToAdd);
      
      if (deliveryDate >= serviceDate) {
        // Show warning
      }
      
      handleInputChange('deliveryDate', deliveryDate.toISOString().split('T')[0]);
    }
  }, [formData.serviceDate, formData.shippingSpeed]);

  // Calculate wound area
  const woundArea = formData.woundSizeLength && formData.woundSizeWidth 
    ? (parseFloat(formData.woundSizeLength) * parseFloat(formData.woundSizeWidth)).toFixed(2)
    : '0';

  const renderSection = () => {
    switch (currentSection) {
      case 0: // Context & Request
        return (
          <div className="space-y-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Request Type</label>
              <select 
                className="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                value={formData.requestType}
                onChange={(e) => handleInputChange('requestType', e.target.value)}
              >
                <option value="new_request">New Request</option>
                <option value="reverification">Re-verification</option>
                <option value="additional_applications">Additional Applications</option>
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Provider</label>
              <select 
                className="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                value={formData.providerId}
                onChange={(e) => handleInputChange('providerId', e.target.value)}
              >
                <option value="">Select a provider...</option>
                {providers.map(p => (
                  <option key={p.id} value={p.id}>
                    {p.name}, {p.credentials} (NPI: {p.npi})
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Facility</label>
              <select 
                className="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                value={formData.facilityId}
                onChange={(e) => handleInputChange('facilityId', e.target.value)}
              >
                <option value="">Select a facility...</option>
                {facilities.map(f => (
                  <option key={f.id} value={f.id}>
                    {f.name} ({f.address})
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Sales Representative</label>
              <input 
                type="text"
                className="w-full p-3 border border-gray-300 rounded-lg bg-gray-100"
                value={formData.salesRepId}
                readOnly
              />
            </div>
          </div>
        );

      case 1: // Patient & Shipping
        return (
          <div className="space-y-6">
            <div className="bg-blue-50 p-4 rounded-lg">
              <h3 className="font-medium text-blue-900 mb-3">Patient Information</h3>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                  <input 
                    type="text"
                    className="w-full p-2 border border-gray-300 rounded"
                    value={formData.patientFirstName}
                    onChange={(e) => handleInputChange('patientFirstName', e.target.value)}
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                  <input 
                    type="text"
                    className="w-full p-2 border border-gray-300 rounded"
                    value={formData.patientLastName}
                    onChange={(e) => handleInputChange('patientLastName', e.target.value)}
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                  <input 
                    type="date"
                    className="w-full p-2 border border-gray-300 rounded"
                    value={formData.patientDOB}
                    onChange={(e) => handleInputChange('patientDOB', e.target.value)}
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                  <select 
                    className="w-full p-2 border border-gray-300 rounded"
                    value={formData.patientGender}
                    onChange={(e) => handleInputChange('patientGender', e.target.value)}
                  >
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="other">Other</option>
                  </select>
                </div>
              </div>

              <div className="mt-4 grid grid-cols-1 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Address Line 1</label>
                  <input 
                    type="text"
                    className="w-full p-2 border border-gray-300 rounded"
                    value={formData.patientAddressLine1 || ''}
                    onChange={(e) => handleInputChange('patientAddressLine1', e.target.value)}
                    placeholder="Street address"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Address Line 2</label>
                  <input 
                    type="text"
                    className="w-full p-2 border border-gray-300 rounded"
                    value={formData.patientAddressLine2 || ''}
                    onChange={(e) => handleInputChange('patientAddressLine2', e.target.value)}
                    placeholder="Apartment, suite, etc. (optional)"
                  />
                </div>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                  <div className="md:col-span-2">
                    <label className="block text-sm font-medium text-gray-700 mb-1">City</label>
                    <input 
                      type="text"
                      className="w-full p-2 border border-gray-300 rounded"
                      value={formData.patientCity || ''}
                      onChange={(e) => handleInputChange('patientCity', e.target.value)}
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">State</label>
                    <select 
                      className="w-full p-2 border border-gray-300 rounded"
                      value={formData.patientState || ''}
                      onChange={(e) => handleInputChange('patientState', e.target.value)}
                    >
                      <option value="">Select...</option>
                      <option value="AL">AL</option>
                      <option value="AK">AK</option>
                      <option value="AZ">AZ</option>
                      <option value="AR">AR</option>
                      <option value="CA">CA</option>
                      <option value="CO">CO</option>
                      <option value="CT">CT</option>
                      <option value="DE">DE</option>
                      <option value="FL">FL</option>
                      <option value="GA">GA</option>
                      <option value="HI">HI</option>
                      <option value="ID">ID</option>
                      <option value="IL">IL</option>
                      <option value="IN">IN</option>
                      <option value="IA">IA</option>
                      <option value="KS">KS</option>
                      <option value="KY">KY</option>
                      <option value="LA">LA</option>
                      <option value="ME">ME</option>
                      <option value="MD">MD</option>
                      <option value="MA">MA</option>
                      <option value="MI">MI</option>
                      <option value="MN">MN</option>
                      <option value="MS">MS</option>
                      <option value="MO">MO</option>
                      <option value="MT">MT</option>
                      <option value="NE">NE</option>
                      <option value="NV">NV</option>
                      <option value="NH">NH</option>
                      <option value="NJ">NJ</option>
                      <option value="NM">NM</option>
                      <option value="NY">NY</option>
                      <option value="NC">NC</option>
                      <option value="ND">ND</option>
                      <option value="OH">OH</option>
                      <option value="OK">OK</option>
                      <option value="OR">OR</option>
                      <option value="PA">PA</option>
                      <option value="RI">RI</option>
                      <option value="SC">SC</option>
                      <option value="SD">SD</option>
                      <option value="TN">TN</option>
                      <option value="TX">TX</option>
                      <option value="UT">UT</option>
                      <option value="VT">VT</option>
                      <option value="VA">VA</option>
                      <option value="WA">WA</option>
                      <option value="WV">WV</option>
                      <option value="WI">WI</option>
                      <option value="WY">WY</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">ZIP Code</label>
                    <input 
                      type="text"
                      className="w-full p-2 border border-gray-300 rounded"
                      value={formData.patientZipCode || ''}
                      onChange={(e) => handleInputChange('patientZipCode', e.target.value)}
                      placeholder="12345"
                      maxLength="10"
                    />
                  </div>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                    <input 
                      type="tel"
                      className="w-full p-2 border border-gray-300 rounded"
                      value={formData.patientPhone || ''}
                      onChange={(e) => handleInputChange('patientPhone', e.target.value)}
                      placeholder="(555) 123-4567"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Email Address (Optional)</label>
                    <input 
                      type="email"
                      className="w-full p-2 border border-gray-300 rounded"
                      value={formData.patientEmail || ''}
                      onChange={(e) => handleInputChange('patientEmail', e.target.value)}
                      placeholder="patient@email.com"
                    />
                  </div>
                </div>
              </div>
            </div>

            <div className="bg-green-50 p-4 rounded-lg">
              <h3 className="font-medium text-green-900 mb-3">Service Date & Shipping</h3>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Service Date</label>
                  <input 
                    type="date"
                    className="w-full p-2 border border-gray-300 rounded"
                    value={formData.serviceDate}
                    onChange={(e) => handleInputChange('serviceDate', e.target.value)}
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Shipping Speed</label>
                  <select 
                    className="w-full p-2 border border-gray-300 rounded"
                    value={formData.shippingSpeed}
                    onChange={(e) => handleInputChange('shippingSpeed', e.target.value)}
                  >
                    <option value="1st_am">1st AM (before 9AM) - Next business day</option>
                    <option value="early_next">Early Next Day (9AM-12PM)</option>
                    <option value="standard_next">Standard Next Day</option>
                    <option value="standard_2day">Standard 2 Day</option>
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Delivery Date (Auto-calculated)</label>
                  <input 
                    type="date"
                    className="w-full p-2 border border-gray-300 rounded bg-gray-100"
                    value={formData.deliveryDate}
                    readOnly
                  />
                </div>
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Is the patient the insurance subscriber?</label>
              <div className="space-x-4">
                <label className="inline-flex items-center">
                  <input 
                    type="radio"
                    className="form-radio"
                    name="subscriber"
                    value="yes"
                    checked={formData.patientIsSubscriber === 'yes'}
                    onChange={(e) => handleInputChange('patientIsSubscriber', e.target.value)}
                  />
                  <span className="ml-2">Yes</span>
                </label>
                <label className="inline-flex items-center">
                  <input 
                    type="radio"
                    className="form-radio"
                    name="subscriber"
                    value="no"
                    checked={formData.patientIsSubscriber === 'no'}
                    onChange={(e) => handleInputChange('patientIsSubscriber', e.target.value)}
                  />
                  <span className="ml-2">No</span>
                </label>
              </div>
            </div>
          </div>
        );

      case 2: // Insurance
        return (
          <div className="space-y-6">
            <div className="bg-purple-50 p-4 rounded-lg">
              <h3 className="font-medium text-purple-900 mb-3">Primary Insurance</h3>
              <div className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Insurance Name</label>
                    <select 
                      className="w-full p-2 border border-gray-300 rounded"
                      value={formData.primaryInsuranceName}
                      onChange={(e) => handleInputChange('primaryInsuranceName', e.target.value)}
                    >
                      <option value="">Select insurance...</option>
                      {insuranceCarriers.map(carrier => (
                        <option key={carrier} value={carrier}>{carrier}</option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Member ID</label>
                    <input 
                      type="text"
                      className="w-full p-2 border border-gray-300 rounded"
                      value={formData.primaryPolicyNumber}
                      onChange={(e) => handleInputChange('primaryPolicyNumber', e.target.value)}
                      placeholder="1234567890A"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Payer Phone (Auto-filled)</label>
                    <input 
                      type="tel"
                      className="w-full p-2 border border-gray-300 rounded bg-gray-100"
                      value={formData.primaryInsuranceName === 'Medicare Part B' ? '1-800-MEDICARE' : '1-800-555-0100'}
                      readOnly
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Plan Type</label>
                    <select 
                      className="w-full p-2 border border-gray-300 rounded"
                      value={formData.primaryPlanType || 'ffs'}
                      onChange={(e) => handleInputChange('primaryPlanType', e.target.value)}
                    >
                      <option value="ffs">FFS (Fee for Service)</option>
                      <option value="hmo">HMO</option>
                      <option value="ppo">PPO</option>
                      <option value="pos">POS</option>
                      <option value="other">Other</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <div className="space-y-4">
              <div className="p-4 border border-gray-200 rounded-lg">
                <label className="flex items-center">
                  <input 
                    type="checkbox"
                    className="form-checkbox h-4 w-4 text-blue-600"
                    checked={formData.hasSecondaryInsurance}
                    onChange={(e) => handleInputChange('hasSecondaryInsurance', e.target.checked)}
                  />
                  <span className="ml-2 text-gray-700 font-medium">Patient has secondary insurance</span>
                </label>
              </div>

              {formData.hasSecondaryInsurance && (
                <div className="bg-indigo-50 p-4 rounded-lg">
                  <h3 className="font-medium text-indigo-900 mb-3">Secondary Insurance</h3>
                  <div className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Insurance Name</label>
                        <select 
                          className="w-full p-2 border border-gray-300 rounded"
                          value={formData.secondaryInsuranceName || ''}
                          onChange={(e) => handleInputChange('secondaryInsuranceName', e.target.value)}
                        >
                          <option value="">Select insurance...</option>
                          {insuranceCarriers.map(carrier => (
                            <option key={carrier} value={carrier}>{carrier}</option>
                          ))}
                        </select>
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Member ID</label>
                        <input 
                          type="text"
                          className="w-full p-2 border border-gray-300 rounded"
                          value={formData.secondaryPolicyNumber || ''}
                          onChange={(e) => handleInputChange('secondaryPolicyNumber', e.target.value)}
                          placeholder="Secondary policy number"
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Subscriber Name</label>
                        <input 
                          type="text"
                          className="w-full p-2 border border-gray-300 rounded"
                          value={formData.secondarySubscriberName || ''}
                          onChange={(e) => handleInputChange('secondarySubscriberName', e.target.value)}
                          placeholder="If different from patient"
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Subscriber DOB</label>
                        <input 
                          type="date"
                          className="w-full p-2 border border-gray-300 rounded"
                          value={formData.secondarySubscriberDOB || ''}
                          onChange={(e) => handleInputChange('secondarySubscriberDOB', e.target.value)}
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Payer Phone</label>
                        <input 
                          type="tel"
                          className="w-full p-2 border border-gray-300 rounded"
                          value={formData.secondaryPayerPhone || ''}
                          onChange={(e) => handleInputChange('secondaryPayerPhone', e.target.value)}
                          placeholder="(800) 555-0100"
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Plan Type</label>
                        <select 
                          className="w-full p-2 border border-gray-300 rounded"
                          value={formData.secondaryPlanType || ''}
                          onChange={(e) => handleInputChange('secondaryPlanType', e.target.value)}
                        >
                          <option value="">Select plan type...</option>
                          <option value="hmo">HMO</option>
                          <option value="ppo">PPO</option>
                          <option value="medicare_supplement">Medicare Supplement</option>
                          <option value="other">Other</option>
                        </select>
                      </div>
                    </div>
                  </div>
                </div>
              )}

              <div className="p-4 bg-green-50 border border-green-200 rounded-lg">
                <label className="flex items-center">
                  <input 
                    type="checkbox"
                    className="form-checkbox h-4 w-4 text-green-600"
                    checked={formData.priorAuthPermission !== false}
                    onChange={(e) => handleInputChange('priorAuthPermission', e.target.checked)}
                  />
                  <span className="ml-2 text-gray-700">MSC may initiate/follow up on prior authorization</span>
                </label>
              </div>
            </div>
          </div>
        );

      case 3: // Clinical & Billing
        return (
          <div className="space-y-6">
            <div className="bg-red-50 p-4 rounded-lg">
              <h3 className="font-medium text-red-900 mb-3">Wound Information</h3>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Wound Type (Select all that apply)</label>
                  <div className="space-y-2">
                    <label className="flex items-center">
                      <input 
                        type="checkbox"
                        className="form-checkbox h-4 w-4 text-blue-600"
                        checked={formData.woundTypes?.includes('diabetic_foot_ulcer') || false}
                        onChange={(e) => {
                          const types = formData.woundTypes || [];
                          if (e.target.checked) {
                            handleInputChange('woundTypes', [...types, 'diabetic_foot_ulcer']);
                          } else {
                            handleInputChange('woundTypes', types.filter(t => t !== 'diabetic_foot_ulcer'));
                          }
                        }}
                      />
                      <span className="ml-2 text-sm text-gray-700">Diabetic Foot Ulcer</span>
                    </label>
                    <label className="flex items-center">
                      <input 
                        type="checkbox"
                        className="form-checkbox h-4 w-4 text-blue-600"
                        checked={formData.woundTypes?.includes('venous_leg_ulcer') || false}
                        onChange={(e) => {
                          const types = formData.woundTypes || [];
                          if (e.target.checked) {
                            handleInputChange('woundTypes', [...types, 'venous_leg_ulcer']);
                          } else {
                            handleInputChange('woundTypes', types.filter(t => t !== 'venous_leg_ulcer'));
                          }
                        }}
                      />
                      <span className="ml-2 text-sm text-gray-700">Venous Leg Ulcer</span>
                    </label>
                    <label className="flex items-center">
                      <input 
                        type="checkbox"
                        className="form-checkbox h-4 w-4 text-blue-600"
                        checked={formData.woundTypes?.includes('pressure_ulcer') || false}
                        onChange={(e) => {
                          const types = formData.woundTypes || [];
                          if (e.target.checked) {
                            handleInputChange('woundTypes', [...types, 'pressure_ulcer']);
                          } else {
                            handleInputChange('woundTypes', types.filter(t => t !== 'pressure_ulcer'));
                          }
                        }}
                      />
                      <span className="ml-2 text-sm text-gray-700">Pressure Ulcer</span>
                    </label>
                    <label className="flex items-center">
                      <input 
                        type="checkbox"
                        className="form-checkbox h-4 w-4 text-blue-600"
                        checked={formData.woundTypes?.includes('surgical_wound') || false}
                        onChange={(e) => {
                          const types = formData.woundTypes || [];
                          if (e.target.checked) {
                            handleInputChange('woundTypes', [...types, 'surgical_wound']);
                          } else {
                            handleInputChange('woundTypes', types.filter(t => t !== 'surgical_wound'));
                          }
                        }}
                      />
                      <span className="ml-2 text-sm text-gray-700">Surgical Wound</span>
                    </label>
                    <label className="flex items-center">
                      <input 
                        type="checkbox"
                        className="form-checkbox h-4 w-4 text-blue-600"
                        checked={formData.woundTypes?.includes('other') || false}
                        onChange={(e) => {
                          const types = formData.woundTypes || [];
                          if (e.target.checked) {
                            handleInputChange('woundTypes', [...types, 'other']);
                          } else {
                            handleInputChange('woundTypes', types.filter(t => t !== 'other'));
                          }
                        }}
                      />
                      <span className="ml-2 text-sm text-gray-700">Other</span>
                    </label>
                  </div>
                  
                  {formData.woundTypes?.includes('other') && (
                    <div className="mt-3">
                      <label className="block text-sm font-medium text-gray-700 mb-1">Specify Other Wound Type</label>
                      <input 
                        type="text"
                        className="w-full p-2 border border-gray-300 rounded"
                        value={formData.woundOtherSpecify || ''}
                        onChange={(e) => handleInputChange('woundOtherSpecify', e.target.value)}
                        placeholder="Please specify..."
                      />
                    </div>
                  )}
                </div>

                {(formData.woundTypes?.includes('diabetic_foot_ulcer') || formData.woundTypes?.includes('venous_leg_ulcer')) && (
                  <div className="bg-yellow-100 border-l-4 border-yellow-500 p-4">
                    <div className="flex items-start">
                      <AlertCircle className="h-5 w-5 text-yellow-600 mt-0.5" />
                      <div className="ml-3">
                        <h4 className="text-sm font-medium text-yellow-800">Diagnosis Codes</h4>
                        <p className="mt-1 text-sm text-yellow-700">
                          {formData.woundTypes?.includes('diabetic_foot_ulcer') && 
                            'Must select 1 Yellow (Diabetes) AND 1 Orange (Chronic Ulcer) code for DFU'}
                          {formData.woundTypes?.includes('diabetic_foot_ulcer') && formData.woundTypes?.includes('venous_leg_ulcer') && 
                            ' | '}
                          {formData.woundTypes?.includes('venous_leg_ulcer') && 
                            'Must select appropriate venous insufficiency codes for VLU'}
                        </p>
                      </div>
                    </div>
                  </div>
                )}

                {formData.woundTypes?.includes('diabetic_foot_ulcer') && (
                  <>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Yellow Diagnosis Code (Diabetes)
                      </label>
                      <select 
                        className="w-full p-2 border border-yellow-400 rounded bg-yellow-50"
                        value={formData.yellowDiagnosisCode}
                        onChange={(e) => handleInputChange('yellowDiagnosisCode', e.target.value)}
                      >
                        <option value="">Select yellow code...</option>
                        {yellowCodes.map(code => (
                          <option key={code.code} value={code.code}>
                            {code.code} - {code.description}
                          </option>
                        ))}
                      </select>
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Orange Diagnosis Code (Chronic Ulcer)
                      </label>
                      <select 
                        className="w-full p-2 border border-orange-400 rounded bg-orange-50"
                        value={formData.orangeDiagnosisCode}
                        onChange={(e) => handleInputChange('orangeDiagnosisCode', e.target.value)}
                      >
                        <option value="">Select orange code...</option>
                        {orangeCodes.map(code => (
                          <option key={code.code} value={code.code}>
                            {code.code} - {code.description}
                          </option>
                        ))}
                      </select>
                    </div>
                  </>
                )}

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Wound Location</label>
                  <select 
                    className="w-full p-2 border border-gray-300 rounded"
                    value={formData.woundLocation}
                    onChange={(e) => handleInputChange('woundLocation', e.target.value)}
                  >
                    <option value="">Select location...</option>
                    <option value="trunk_arms_legs_small">Legs/Arms/Trunk ≤ 100 sq cm</option>
                    <option value="trunk_arms_legs_large">Legs/Arms/Trunk > 100 sq cm</option>
                    <option value="hands_feet_head_small">Feet/Hands/Head ≤ 100 sq cm</option>
                    <option value="hands_feet_head_large">Feet/Hands/Head > 100 sq cm</option>
                  </select>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Specific Wound Location (Optional)</label>
                  <input 
                    type="text"
                    className="w-full p-2 border border-gray-300 rounded"
                    value={formData.woundLocationDetails || ''}
                    onChange={(e) => handleInputChange('woundLocationDetails', e.target.value)}
                    placeholder="e.g., Right foot, plantar surface, 1st metatarsal"
                  />
                </div>

                <div className="grid grid-cols-3 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Length (cm)</label>
                    <input 
                      type="number"
                      step="0.1"
                      className="w-full p-2 border border-gray-300 rounded"
                      value={formData.woundSizeLength}
                      onChange={(e) => handleInputChange('woundSizeLength', e.target.value)}
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Width (cm)</label>
                    <input 
                      type="number"
                      step="0.1"
                      className="w-full p-2 border border-gray-300 rounded"
                      value={formData.woundSizeWidth}
                      onChange={(e) => handleInputChange('woundSizeWidth', e.target.value)}
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Depth (cm)</label>
                    <input 
                      type="number"
                      step="0.1"
                      className="w-full p-2 border border-gray-300 rounded"
                      value={formData.woundSizeDepth}
                      onChange={(e) => handleInputChange('woundSizeDepth', e.target.value)}
                    />
                  </div>
                </div>

                <div className="bg-gray-100 p-3 rounded">
                  <p className="text-sm font-medium text-gray-700">
                    Total Wound Area: <span className="text-lg font-bold text-blue-600">{woundArea} sq cm</span>
                  </p>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Wound Duration</label>
                  <input 
                    type="text"
                    className="w-full p-2 border border-gray-300 rounded"
                    value={formData.woundDuration || ''}
                    onChange={(e) => handleInputChange('woundDuration', e.target.value)}
                    placeholder="e.g., 6 weeks, 3 months"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Previously Used Therapies</label>
                  <textarea 
                    className="w-full p-2 border border-gray-300 rounded"
                    rows="3"
                    value={formData.previousTreatments || ''}
                    onChange={(e) => handleInputChange('previousTreatments', e.target.value)}
                    placeholder="List previous treatments attempted..."
                  />
                </div>
              </div>
            </div>

            <div className="bg-blue-50 p-4 rounded-lg">
              <h3 className="font-medium text-blue-900 mb-3">Procedure Information</h3>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Application CPT Codes</label>
                  <div className="bg-blue-100 border border-blue-200 rounded p-3 mb-3">
                    <p className="text-sm text-blue-800">
                      <strong>Auto-selected based on:</strong> {formData.woundLocation ? 
                        (formData.woundLocation.includes('trunk_arms_legs') ? 'Trunk/Arms/Legs' : 'Feet/Hands/Head') 
                        : 'Select wound location first'} | {woundArea} sq cm
                    </p>
                  </div>
                  
                  {(() => {
                    const area = parseFloat(woundArea) || 0;
                    const isExtremity = formData.woundLocation?.includes('hands_feet_head');
                    let suggestedCodes = [];
                    
                    if (area > 0) {
                      if (isExtremity) {
                        if (area <= 25) suggestedCodes = ['15275'];
                        else if (area <= 100) suggestedCodes = ['15275', '15276'];
                        else suggestedCodes = ['15277', '15278'];
                      } else {
                        if (area <= 25) suggestedCodes = ['15271'];
                        else if (area <= 100) suggestedCodes = ['15271', '15272'];
                        else suggestedCodes = ['15273', '15274'];
                      }
                    }
                    
                    const cptOptions = [
                      { value: '15271', label: '15271 - First 25 sq cm trunk/arms/legs', group: 'trunk' },
                      { value: '15272', label: '15272 - Each additional 25 sq cm trunk/arms/legs', group: 'trunk' },
                      { value: '15273', label: '15273 - First 100 sq cm trunk/arms/legs', group: 'trunk' },
                      { value: '15274', label: '15274 - Each additional 100 sq cm trunk/arms/legs', group: 'trunk' },
                      { value: '15275', label: '15275 - First 25 sq cm feet/hands/head', group: 'extremity' },
                      { value: '15276', label: '15276 - Each additional 25 sq cm feet/hands/head', group: 'extremity' },
                      { value: '15277', label: '15277 - First 100 sq cm feet/hands/head', group: 'extremity' },
                      { value: '15278', label: '15278 - Each additional 100 sq cm feet/hands/head', group: 'extremity' }
                    ];
                    
                    return (
                      <div className="space-y-2">
                        {cptOptions.map(option => (
                          <label key={option.value} className="flex items-center">
                            <input 
                              type="checkbox"
                              className="form-checkbox h-4 w-4 text-blue-600"
                              checked={formData.applicationCptCodes?.includes(option.value) || suggestedCodes.includes(option.value)}
                              onChange={(e) => {
                                const codes = formData.applicationCptCodes || [];
                                if (e.target.checked) {
                                  handleInputChange('applicationCptCodes', [...codes, option.value]);
                                } else {
                                  handleInputChange('applicationCptCodes', codes.filter(c => c !== option.value));
                                }
                              }}
                            />
                            <span className={`ml-2 text-sm ${suggestedCodes.includes(option.value) ? 'font-medium text-blue-700' : 'text-gray-700'}`}>
                              {option.label}
                              {suggestedCodes.includes(option.value) && <span className="ml-2 text-xs bg-blue-200 px-2 py-1 rounded">Recommended</span>}
                            </span>
                          </label>
                        ))}
                      </div>
                    );
                  })()}
                  
                  <p className="text-xs text-gray-500 mt-3 italic">
                    Note: This CPT code recommendation is based on the wound size and location provided. The final billing code selection is the responsibility of the provider.
                  </p>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Number of Prior Applications</label>
                  <input 
                    type="number"
                    className="w-full p-2 border border-gray-300 rounded"
                    value={formData.priorApplications || ''}
                    onChange={(e) => handleInputChange('priorApplications', e.target.value)}
                    min="0"
                    max="20"
                    placeholder="0"
                  />
                  <p className="text-xs text-gray-500 mt-1">Number of times this product has been previously applied</p>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Number of Anticipated Applications</label>
                  <input 
                    type="number"
                    className="w-full p-2 border border-gray-300 rounded"
                    value={formData.anticipatedApplications || ''}
                    onChange={(e) => handleInputChange('anticipatedApplications', e.target.value)}
                    min="1"
                    max="10"
                    placeholder="1"
                  />
                  <p className="text-xs text-gray-500 mt-1">Expected number of future applications needed</p>
                </div>
              </div>
            </div>

            <div className="bg-yellow-50 p-4 rounded-lg">
              <h3 className="font-medium text-yellow-900 mb-3">Facility & Billing Status</h3>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Place of Service</label>
                  <select 
                    className="w-full p-2 border border-gray-300 rounded"
                    value={formData.placeOfService}
                    onChange={(e) => handleInputChange('placeOfService', e.target.value)}
                  >
                    <option value="11">11 - Office</option>
                    <option value="12">12 - Home</option>
                    <option value="31">31 - Skilled Nursing Facility (SNF)</option>
                    <option value="32">32 - Nursing Home</option>
                  </select>
                </div>

                {/* SNF/Nursing Home Medicare Authorization */}
                {(formData.placeOfService === '31' || formData.placeOfService === '32') && (
                  <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div className="flex items-start">
                      <AlertCircle className="h-5 w-5 text-red-600 mt-0.5 flex-shrink-0" />
                      <div className="ml-3">
                        <h4 className="text-sm font-medium text-red-900">Medicare Part B Authorization Required</h4>
                        <p className="mt-1 text-sm text-red-700">
                          {formData.placeOfService === '31' ? 'Skilled Nursing Facility' : 'Nursing Home'} requires special Medicare authorization
                        </p>
                        
                        <div className="mt-3">
                          <label className="flex items-center">
                            <input 
                              type="checkbox"
                              className="form-checkbox h-4 w-4 text-red-600"
                              checked={formData.medicarePartBAuthorized || false}
                              onChange={(e) => handleInputChange('medicarePartBAuthorized', e.target.checked)}
                            />
                            <span className="ml-2 text-sm font-medium text-gray-700">
                              Medicare Part B is authorized for this {formData.placeOfService === '31' ? 'SNF' : 'Nursing Home'} stay
                            </span>
                          </label>
                        </div>

                        {formData.medicarePartBAuthorized && (
                          <div className="mt-3 space-y-3">
                            <div>
                              <label className="block text-sm font-medium text-gray-700 mb-1">Days in Facility</label>
                              <input 
                                type="number"
                                className="w-full p-2 border border-gray-300 rounded"
                                value={formData.snfDays || ''}
                                onChange={(e) => handleInputChange('snfDays', e.target.value)}
                                placeholder="Number of days"
                                min="0"
                                max="999"
                              />
                            </div>
                            
                            {formData.snfDays > 100 && (
                              <div className="bg-yellow-100 border-l-4 border-yellow-500 p-3">
                                <p className="text-sm text-yellow-700">
                                  <strong>Warning:</strong> Medicare coverage may be affected after 100 days in facility
                                </p>
                              </div>
                            )}
                          </div>
                        )}
                      </div>
                    </div>
                  </div>
                )}

                {/* Additional Billing Status Checkboxes */}
                <div className="space-y-2">
                  <label className="flex items-center">
                    <input 
                      type="checkbox"
                      className="form-checkbox h-4 w-4 text-blue-600"
                      checked={formData.hospiceStatus || false}
                      onChange={(e) => handleInputChange('hospiceStatus', e.target.checked)}
                    />
                    <span className="ml-2 text-sm text-gray-700">Patient is in Hospice</span>
                  </label>

                  <label className="flex items-center">
                    <input 
                      type="checkbox"
                      className="form-checkbox h-4 w-4 text-blue-600"
                      checked={formData.partAStatus || false}
                      onChange={(e) => handleInputChange('partAStatus', e.target.checked)}
                    />
                    <span className="ml-2 text-sm text-gray-700">Patient is under Medicare Part A stay</span>
                  </label>

                  <label className="flex items-center">
                    <input 
                      type="checkbox"
                      className="form-checkbox h-4 w-4 text-blue-600"
                      checked={formData.globalPeriodStatus || false}
                      onChange={(e) => handleInputChange('globalPeriodStatus', e.target.checked)}
                    />
                    <span className="ml-2 text-sm text-gray-700">Patient under post-op global period</span>
                  </label>

                  {formData.globalPeriodStatus && (
                    <div className="ml-6 mt-2 p-3 bg-gray-50 rounded space-y-2">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Previous Surgery CPT</label>
                        <input 
                          type="text"
                          className="w-full p-2 border border-gray-300 rounded"
                          value={formData.globalPeriodCPT || ''}
                          onChange={(e) => handleInputChange('globalPeriodCPT', e.target.value)}
                          placeholder="5-digit CPT code"
                          maxLength="5"
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Surgery Date</label>
                        <input 
                          type="date"
                          className="w-full p-2 border border-gray-300 rounded"
                          value={formData.globalPeriodSurgeryDate || ''}
                          onChange={(e) => handleInputChange('globalPeriodSurgeryDate', e.target.value)}
                        />
                      </div>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>
        );

      case 4: // Product Selection
        const availableProducts = getAvailableProducts();
        const selectedProduct = formData.selectedProduct ? 
          allProducts.find(p => p.qCode === formData.selectedProduct) : null;
        
        return (
          <div className="space-y-6">
            {/* Provider Onboarded Products Notice */}
            {formData.providerId && (
              <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div className="flex items-start">
                  <Info className="h-5 w-5 text-blue-600 mt-0.5" />
                  <div className="ml-3">
                    <h4 className="text-sm font-medium text-blue-900">Provider Onboarded Products</h4>
                    <p className="mt-1 text-sm text-blue-700">
                      Showing {availableProducts.length} products that {providers.find(p => p.id === formData.providerId)?.name} is authorized to order
                    </p>
                  </div>
                </div>
              </div>
            )}

            {/* Product Selection */}
            <div className="bg-white border border-gray-200 rounded-lg p-4">
              <h3 className="text-lg font-medium text-gray-900 mb-4">Select Product</h3>
              
              {!formData.providerId && (
                <div className="text-center py-8 text-gray-500">
                  Please select a provider first to see available products
                </div>
              )}

              {formData.providerId && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Product</label>
                  <select 
                    className="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    value={formData.selectedProduct || ''}
                    onChange={(e) => {
                      handleInputChange('selectedProduct', e.target.value);
                      // Clear existing order items when product changes
                      handleInputChange('orderItems', []);
                    }}
                  >
                    <option value="">Select product...</option>
                    {availableProducts.map(product => (
                      <option key={product.qCode} value={product.qCode}>
                        {product.name} ({product.qCode}) - {product.manufacturer}
                      </option>
                    ))}
                  </select>
                </div>
              )}
            </div>

            {/* Size Selection - Only shows after product is selected */}
            {selectedProduct && (
              <div className="space-y-4">
                <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
                  <div className="flex justify-between items-center mb-4">
                    <div>
                      <h3 className="text-lg font-medium text-gray-900">Size Selection</h3>
                      <p className="text-sm text-gray-600 mt-1">
                        Add multiple sizes of {selectedProduct.name} as needed
                      </p>
                    </div>
                    <button
                      onClick={() => {
                        const newItem = {
                          id: Date.now(),
                          productCode: formData.selectedProduct,
                          size: '',
                          quantity: 1,
                          unitPrice: 0,
                          totalPrice: 0
                        };
                        handleInputChange('orderItems', [...formData.orderItems, newItem]);
                      }}
                      className="flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                    >
                      <Plus className="h-4 w-4 mr-2" />
                      Add Size
                    </button>
                  </div>

                  {formData.orderItems.length === 0 && (
                    <div className="text-center py-8 text-gray-500 border-2 border-dashed border-gray-300 rounded-lg">
                      Click "Add Size" to add sizes for this product
                    </div>
                  )}

                  {formData.orderItems.map((item, index) => (
                    <div key={item.id} className="bg-white border border-gray-200 rounded-lg p-4 mb-3">
                      <div className="flex justify-between items-start mb-3">
                        <h4 className="font-medium text-gray-700">Size Option #{index + 1}</h4>
                        <button
                          onClick={() => removeOrderItem(item.id)}
                          className="text-red-600 hover:text-red-800"
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </div>

                      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">Size</label>
                          <select 
                            className="w-full p-2 border border-gray-300 rounded"
                            value={item.size}
                            onChange={(e) => updateOrderItem(item.id, 'size', e.target.value)}
                          >
                            <option value="">Select size...</option>
                            {selectedProduct.sizes.map(size => (
                              <option key={size} value={size}>{size}</option>
                            ))}
                          </select>
                        </div>

                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                          <input 
                            type="number"
                            className="w-full p-2 border border-gray-300 rounded"
                            value={item.quantity}
                            onChange={(e) => updateOrderItem(item.id, 'quantity', parseInt(e.target.value) || 1)}
                            min="1"
                            max="10"
                          />
                        </div>

                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">Line Total</label>
                          <div className="p-2 bg-gray-100 rounded font-medium">
                            ${item.totalPrice.toFixed(2)}
                          </div>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>

                {/* Order Total */}
                {formData.orderItems.length > 0 && (
                  <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div className="flex justify-between items-center">
                      <span className="text-lg font-medium text-gray-900">Order Total:</span>
                      <span className="text-2xl font-bold text-green-600">
                        ${calculateOrderTotal().toFixed(2)}
                      </span>
                    </div>
                  </div>
                )}
              </div>
            )}
          </div>
        );

      default:
        return null;
    }
  };

  return (
    <div className="max-w-5xl mx-auto p-6">
      {/* Header */}
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900 mb-2">MSC Enhanced Order Flow</h1>
        <p className="text-gray-600">Complete wound care product ordering in 90 seconds</p>
      </div>

      {/* Progress Bar */}
      <div className="mb-8">
        <div className="flex items-center justify-between mb-2">
          {sections.map((section, index) => {
            const Icon = section.icon;
            return (
              <div 
                key={index}
                className={`flex items-center ${index < sections.length - 1 ? 'flex-1' : ''}`}
              >
                <div className={`flex flex-col items-center ${index <= currentSection ? 'text-blue-600' : 'text-gray-400'}`}>
                  <div className={`rounded-full p-3 ${index <= currentSection ? 'bg-blue-100' : 'bg-gray-100'}`}>
                    <Icon className="h-6 w-6" />
                  </div>
                  <span className="text-xs mt-2 text-center max-w-[100px]">{section.title}</span>
                  <span className="text-xs text-gray-500 flex items-center mt-1">
                    <Clock className="h-3 w-3 mr-1" />
                    {section.estimatedTime}
                  </span>
                </div>
                {index < sections.length - 1 && (
                  <div className={`flex-1 h-0.5 mx-2 ${index < currentSection ? 'bg-blue-600' : 'bg-gray-300'}`} />
                )}
              </div>
            );
          })}
        </div>
      </div>

      {/* Form Content */}
      <div className="bg-white rounded-lg shadow-lg p-8 mb-6">
        <h2 className="text-2xl font-semibold mb-6 flex items-center">
          {React.createElement(sections[currentSection].icon, { className: "h-6 w-6 mr-3 text-blue-600" })}
          {sections[currentSection].title}
        </h2>
        {renderSection()}
      </div>

      {/* Navigation */}
      <div className="flex justify-between">
        <button
          onClick={handlePrevious}
          disabled={currentSection === 0}
          className={`px-6 py-3 rounded-lg font-medium ${
            currentSection === 0 
              ? 'bg-gray-200 text-gray-400 cursor-not-allowed' 
              : 'bg-gray-600 text-white hover:bg-gray-700'
          }`}
        >
          Previous
        </button>
        
        <button
          onClick={handleNext}
          className={`px-6 py-3 rounded-lg font-medium flex items-center ${
            currentSection === sections.length - 1
              ? 'bg-green-600 text-white hover:bg-green-700'
              : 'bg-blue-600 text-white hover:bg-blue-700'
          }`}
        >
          {currentSection === sections.length - 1 ? 'Submit Order' : 'Next'}
          <ChevronRight className="ml-2 h-5 w-5" />
        </button>
      </div>

      {/* Timer Display */}
      <div className="mt-6 text-center text-sm text-gray-500">
        Total estimated completion time: <span className="font-semibold">90 seconds</span>
      </div>
    </div>
  );
};

export default MSCOrderFlowForm;