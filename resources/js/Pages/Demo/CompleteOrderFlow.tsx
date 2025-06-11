import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { IvrGenerator } from '@/Components/DocuSeal/IvrGenerator';
import DocuSealFormComponent from '@/Components/DocuSeal/DocuSealForm';
import { 
  ShoppingCart, 
  FileText, 
  Send, 
  CheckCircle,
  ChevronRight,
  User,
  Package,
  Building2,
  Calendar,
  AlertCircle,
  Clock,
  Heart
} from 'lucide-react';

interface OrderStep {
  id: number;
  title: string;
  status: 'completed' | 'active' | 'pending';
}

const CompleteOrderFlow: React.FC = () => {
  const [currentStep, setCurrentStep] = useState(1);
  const [showDocuSealEmbed, setShowDocuSealEmbed] = useState(false);
  
  // Real order data with ACZ Distribution products
  const mockOrder = {
    id: 'demo-001',
    order_number: 'ORD-2025-001',
    patient_display_id: 'JO**#7842',
    provider: {
      name: 'Dr. Jane Smith',
      npi: '1234567890',
      email: 'dr.smith@woundcare.com',
      facility: 'Advanced Wound Care Center'
    },
    product: {
      name: 'ACELL Cytal Wound Matrix',
      sku: 'CWM-2X3',
      quantity: 2,
      size: '2cm x 3cm',
      manufacturer: 'ACZ Distribution'
    },
    wound_details: {
      type: 'Diabetic Foot Ulcer',
      location: 'Right foot, plantar surface',
      size: '2.5cm x 3.0cm',
      duration: '6 weeks'
    },
    insurance: {
      primary: 'Medicare',
      id: 'MED123456789'
    },
    docuseal: {
      folder_id: '75423',
      template_id: '852440'
    }
  };

  const steps: OrderStep[] = [
    { id: 1, title: 'Product Request Created', status: 'completed' },
    { id: 2, title: 'Admin Review', status: currentStep >= 2 ? 'completed' : currentStep === 1 ? 'active' : 'pending' },
    { id: 3, title: 'IVR Generation', status: currentStep >= 3 ? 'completed' : currentStep === 2 ? 'active' : 'pending' },
    { id: 4, title: 'Manufacturer Approval', status: currentStep >= 4 ? 'completed' : currentStep === 3 ? 'active' : 'pending' },
    { id: 5, title: 'Order Complete', status: currentStep === 5 ? 'completed' : currentStep === 4 ? 'active' : 'pending' }
  ];

  const handleStepComplete = () => {
    if (currentStep < 5) {
      setCurrentStep(currentStep + 1);
    }
  };

  const handleDocuSealComplete = (data: any) => {
    console.log('DocuSeal form completed:', data);
    setShowDocuSealEmbed(false);
    handleStepComplete();
  };

  return (
    <MainLayout>
      <Head title="Complete Order Flow Demo" />
      
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900 mb-2">
            End-to-End Order Process with DocuSeal Integration
          </h1>
          <p className="text-lg text-gray-600">
            Experience the complete wound care product ordering workflow
          </p>
        </div>

        {/* Progress Steps */}
        <div className="mb-8">
          <nav aria-label="Progress">
            <ol className="flex items-center">
              {steps.map((step, index) => (
                <li key={step.id} className={index !== steps.length - 1 ? 'flex-1' : ''}>
                  <div className="flex items-center">
                    <div className={`
                      flex items-center justify-center w-10 h-10 rounded-full
                      ${step.status === 'completed' ? 'bg-green-600' : 
                        step.status === 'active' ? 'bg-blue-600' : 
                        'bg-gray-300'}
                    `}>
                      {step.status === 'completed' ? (
                        <CheckCircle className="w-6 h-6 text-white" />
                      ) : (
                        <span className="text-white font-semibold">{step.id}</span>
                      )}
                    </div>
                    {index !== steps.length - 1 && (
                      <div className={`flex-1 h-0.5 mx-4 ${
                        steps[index + 1].status !== 'pending' ? 'bg-green-600' : 'bg-gray-300'
                      }`} />
                    )}
                  </div>
                  <p className={`mt-2 text-sm font-medium ${
                    step.status === 'active' ? 'text-blue-600' : 
                    step.status === 'completed' ? 'text-green-600' : 
                    'text-gray-500'
                  }`}>
                    {step.title}
                  </p>
                </li>
              ))}
            </ol>
          </nav>
        </div>

        {/* Main Content Area */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Order Details Sidebar */}
          <div className="lg:col-span-1">
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sticky top-6">
              <h2 className="text-lg font-semibold text-gray-900 mb-4">Order Details</h2>
              
              {/* Provider Info */}
              <div className="mb-4 pb-4 border-b border-gray-200">
                <div className="flex items-start space-x-3">
                  <User className="w-5 h-5 text-gray-400 mt-0.5" />
                  <div>
                    <p className="text-sm font-medium text-gray-900">{mockOrder.provider.name}</p>
                    <p className="text-xs text-gray-500">NPI: {mockOrder.provider.npi}</p>
                    <p className="text-xs text-gray-500">{mockOrder.provider.facility}</p>
                  </div>
                </div>
              </div>

              {/* Patient Info */}
              <div className="mb-4 pb-4 border-b border-gray-200">
                <div className="flex items-start space-x-3">
                  <Heart className="w-5 h-5 text-gray-400 mt-0.5" />
                  <div>
                    <p className="text-sm font-medium text-gray-900">Patient ID: {mockOrder.patient_display_id}</p>
                    <p className="text-xs text-gray-500">{mockOrder.insurance.primary}</p>
                    <p className="text-xs text-gray-500">ID: {mockOrder.insurance.id}</p>
                  </div>
                </div>
              </div>

              {/* Product Info */}
              <div className="mb-4 pb-4 border-b border-gray-200">
                <div className="flex items-start space-x-3">
                  <Package className="w-5 h-5 text-gray-400 mt-0.5" />
                  <div>
                    <p className="text-sm font-medium text-gray-900">{mockOrder.product.name}</p>
                    <p className="text-xs text-gray-500">SKU: {mockOrder.product.sku}</p>
                    <p className="text-xs text-gray-500">Qty: {mockOrder.product.quantity} x {mockOrder.product.size}</p>
                  </div>
                </div>
              </div>

              {/* Wound Details */}
              <div>
                <div className="flex items-start space-x-3">
                  <AlertCircle className="w-5 h-5 text-gray-400 mt-0.5" />
                  <div>
                    <p className="text-sm font-medium text-gray-900">Clinical Details</p>
                    <p className="text-xs text-gray-500">{mockOrder.wound_details.type}</p>
                    <p className="text-xs text-gray-500">{mockOrder.wound_details.location}</p>
                    <p className="text-xs text-gray-500">Size: {mockOrder.wound_details.size}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Main Action Area */}
          <div className="lg:col-span-2">
            {/* Step 1: Product Request Created */}
            {currentStep === 1 && (
              <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 className="text-lg font-semibold text-gray-900 mb-4">
                  Product Request Submitted
                </h3>
                <div className="mb-6">
                  <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div className="flex items-center space-x-3">
                      <CheckCircle className="w-8 h-8 text-green-600" />
                      <div>
                        <p className="text-sm font-medium text-green-900">
                          Product request successfully created
                        </p>
                        <p className="text-sm text-green-700">
                          Order #{mockOrder.order_number} is ready for admin review
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
                <button
                  onClick={handleStepComplete}
                  className="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 font-medium"
                >
                  Proceed to Admin Review
                  <ChevronRight className="w-4 h-4 inline-block ml-2" />
                </button>
              </div>
            )}

            {/* Step 2: Admin Review */}
            {currentStep === 2 && (
              <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 className="text-lg font-semibold text-gray-900 mb-4">
                  Admin Review
                </h3>
                <div className="mb-6 space-y-4">
                  <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p className="text-sm text-blue-900">
                      Reviewing order details and preparing for IVR generation...
                    </p>
                  </div>
                  
                  <div className="space-y-3">
                    <div className="flex items-center space-x-3">
                      <CheckCircle className="w-5 h-5 text-green-600" />
                      <span className="text-sm text-gray-700">Patient eligibility verified</span>
                    </div>
                    <div className="flex items-center space-x-3">
                      <CheckCircle className="w-5 h-5 text-green-600" />
                      <span className="text-sm text-gray-700">Product availability confirmed</span>
                    </div>
                    <div className="flex items-center space-x-3">
                      <CheckCircle className="w-5 h-5 text-green-600" />
                      <span className="text-sm text-gray-700">Clinical documentation complete</span>
                    </div>
                  </div>
                </div>
                <button
                  onClick={handleStepComplete}
                  className="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 font-medium"
                >
                  Proceed to IVR Generation
                  <ChevronRight className="w-4 h-4 inline-block ml-2" />
                </button>
              </div>
            )}

            {/* Step 3: IVR Generation */}
            {currentStep === 3 && (
              <div className="space-y-6">
                <IvrGenerator
                  orderId={mockOrder.id}
                  orderNumber={mockOrder.order_number}
                  manufacturerName={mockOrder.product.manufacturer}
                  onComplete={handleStepComplete}
                />
                
                {/* DocuSeal Embed Option */}
                <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                  <h3 className="text-lg font-semibold text-gray-900 mb-4">
                    Alternative: Embedded DocuSeal Form
                  </h3>
                  <p className="text-sm text-gray-600 mb-4">
                    You can also use the embedded DocuSeal form for a more integrated experience:
                  </p>
                  <button
                    onClick={() => setShowDocuSealEmbed(true)}
                    className="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 font-medium"
                  >
                    <FileText className="w-4 h-4 inline-block mr-2" />
                    Open DocuSeal Form
                  </button>
                </div>

                {showDocuSealEmbed && (
                  <div className="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center p-4 z-50">
                    <div className="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-auto">
                      <div className="p-4 border-b border-gray-200 flex items-center justify-between">
                        <h3 className="text-lg font-semibold text-gray-900">
                          DocuSeal IVR Form
                        </h3>
                        <button
                          onClick={() => setShowDocuSealEmbed(false)}
                          className="text-gray-400 hover:text-gray-600"
                        >
                          ×
                        </button>
                      </div>
                      <div className="p-4">
                        <DocuSealFormComponent
                          src={`https://docuseal.com/templates/${mockOrder.docuseal.template_id}`}
                          onComplete={handleDocuSealComplete}
                          className="min-h-[600px]"
                        />
                      </div>
                    </div>
                  </div>
                )}
              </div>
            )}

            {/* Step 4: Manufacturer Approval */}
            {currentStep === 4 && (
              <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 className="text-lg font-semibold text-gray-900 mb-4">
                  Awaiting Manufacturer Approval
                </h3>
                <div className="mb-6">
                  <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div className="flex items-start space-x-3">
                      <Clock className="w-8 h-8 text-yellow-600 mt-0.5" />
                      <div>
                        <p className="text-sm font-medium text-yellow-900">
                          IVR sent to {mockOrder.product.manufacturer}
                        </p>
                        <p className="text-sm text-yellow-700">
                          Typical approval time: 24-48 hours
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
                
                <div className="text-center py-8">
                  <p className="text-sm text-gray-600 mb-4">
                    Simulating manufacturer approval process...
                  </p>
                  <button
                    onClick={handleStepComplete}
                    className="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700 font-medium"
                  >
                    Simulate Approval
                  </button>
                </div>
              </div>
            )}

            {/* Step 5: Order Complete */}
            {currentStep === 5 && (
              <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 className="text-lg font-semibold text-gray-900 mb-4">
                  Order Complete!
                </h3>
                <div className="mb-6">
                  <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div className="flex items-center space-x-3">
                      <CheckCircle className="w-12 h-12 text-green-600" />
                      <div>
                        <p className="text-lg font-medium text-green-900">
                          Order Approved and Submitted
                        </p>
                        <p className="text-sm text-green-700">
                          {mockOrder.product.manufacturer} has approved the order
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
                
                <div className="space-y-4">
                  <div className="border-t border-gray-200 pt-4">
                    <h4 className="text-sm font-medium text-gray-900 mb-2">Next Steps:</h4>
                    <ul className="space-y-2 text-sm text-gray-600">
                      <li className="flex items-start">
                        <span className="text-green-600 mr-2">✓</span>
                        Product will be shipped directly to the facility
                      </li>
                      <li className="flex items-start">
                        <span className="text-green-600 mr-2">✓</span>
                        Tracking information will be sent via email
                      </li>
                      <li className="flex items-start">
                        <span className="text-green-600 mr-2">✓</span>
                        Invoice will be generated for billing
                      </li>
                    </ul>
                  </div>
                  
                  <button
                    onClick={() => setCurrentStep(1)}
                    className="w-full bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 font-medium"
                  >
                    Start New Demo
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Key Features */}
        <div className="mt-12 grid grid-cols-1 md:grid-cols-3 gap-6">
          <div className="bg-blue-50 rounded-lg p-6 text-center">
            <Clock className="w-12 h-12 text-blue-600 mx-auto mb-3" />
            <h3 className="text-lg font-semibold text-gray-900 mb-2">90-Second Workflow</h3>
            <p className="text-sm text-gray-600">
              Complete product requests in under 90 seconds
            </p>
          </div>
          
          <div className="bg-green-50 rounded-lg p-6 text-center">
            <FileText className="w-12 h-12 text-green-600 mx-auto mb-3" />
            <h3 className="text-lg font-semibold text-gray-900 mb-2">90% Auto-Population</h3>
            <p className="text-sm text-gray-600">
              IVR forms filled automatically from system data
            </p>
          </div>
          
          <div className="bg-purple-50 rounded-lg p-6 text-center">
            <Send className="w-12 h-12 text-purple-600 mx-auto mb-3" />
            <h3 className="text-lg font-semibold text-gray-900 mb-2">Automated Delivery</h3>
            <p className="text-sm text-gray-600">
              Direct email to manufacturers for fast processing
            </p>
          </div>
        </div>
      </div>
    </MainLayout>
  );
};

export default CompleteOrderFlow;