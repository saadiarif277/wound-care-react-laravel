import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { 
  Package, 
  FileText, 
  Send, 
  CheckCircle, 
  ArrowRight,
  Play,
  User,
  Building2,
  Heart,
  ShoppingCart,
  Clipboard,
  FileSignature,
  Download,
  Mail,
  Clock,
  AlertCircle
} from 'lucide-react';
import DocuSealFormComponent from '@/Components/DocuSeal/DocuSealForm';

interface ShowcaseStep {
  id: string;
  title: string;
  description: string;
  icon: React.ElementType;
  status: 'completed' | 'current' | 'upcoming';
  demoAction?: () => void;
}

const OrderShowcase: React.FC = () => {
  const [currentStep, setCurrentStep] = useState(0);
  const [showDocuSealForm, setShowDocuSealForm] = useState(false);
  const [generatedIvrUrl, setGeneratedIvrUrl] = useState<string | null>(null);
  const [orderData, setOrderData] = useState({
    id: 'demo-order-001',
    order_number: 'ORD-2025-001',
    patient_display_id: 'JO**#7842',
    provider: {
      name: 'Dr. Jane Smith',
      npi: '1234567890',
      facility: 'Advanced Wound Care Center'
    },
    product: {
      name: 'ACell MicroMatrix',
      sku: 'ACM-100',
      quantity: 2,
      size: '100mg'
    },
    manufacturer: 'ACell Inc.',
    status: 'pending_ivr'
  });

  const steps: ShowcaseStep[] = [
    {
      id: 'create',
      title: 'Create Product Request',
      description: 'Provider initiates a product request for wound care supplies',
      icon: ShoppingCart,
      status: 'completed',
      demoAction: () => {
        alert('Product Request Created!\n\nOrder Number: ' + orderData.order_number);
        setCurrentStep(1);
      }
    },
    {
      id: 'review',
      title: 'Admin Review',
      description: 'Admin reviews the order and prepares for IVR generation',
      icon: Clipboard,
      status: currentStep >= 1 ? 'completed' : 'upcoming',
      demoAction: () => {
        alert('Order reviewed and ready for IVR generation');
        setCurrentStep(2);
      }
    },
    {
      id: 'ivr',
      title: 'Generate IVR Document',
      description: 'Generate ACZ IVR form using DocuSeal integration',
      icon: FileSignature,
      status: currentStep >= 2 ? 'current' : 'upcoming',
      demoAction: () => {
        setShowDocuSealForm(true);
        // Simulate IVR generation
        setTimeout(() => {
          setGeneratedIvrUrl('/demo-ivr-document.pdf');
          setCurrentStep(3);
        }, 2000);
      }
    },
    {
      id: 'send',
      title: 'Send to Manufacturer',
      description: 'Email IVR document to manufacturer for processing',
      icon: Mail,
      status: currentStep >= 3 ? 'completed' : 'upcoming',
      demoAction: () => {
        alert('IVR document sent to ' + orderData.manufacturer);
        setCurrentStep(4);
      }
    },
    {
      id: 'confirm',
      title: 'Manufacturer Confirmation',
      description: 'Receive confirmation from manufacturer',
      icon: CheckCircle,
      status: currentStep >= 4 ? 'completed' : 'upcoming',
      demoAction: () => {
        alert('Order confirmed by manufacturer!');
        setOrderData({...orderData, status: 'manufacturer_approved'});
      }
    }
  ];

  const handleDocuSealComplete = (data: any) => {
    console.log('DocuSeal form completed:', data);
    setShowDocuSealForm(false);
    setGeneratedIvrUrl('/demo-ivr-document.pdf');
    setCurrentStep(3);
  };

  return (
    <MainLayout>
      <Head title="Order Process Showcase" />
      
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="text-center mb-12">
          <h1 className="text-3xl font-bold text-gray-900 mb-4">
            End-to-End Order Process with DocuSeal
          </h1>
          <p className="text-lg text-gray-600 max-w-3xl mx-auto">
            Experience the complete workflow from product request creation to manufacturer approval, 
            featuring our integrated DocuSeal IVR generation system.
          </p>
        </div>

        {/* Demo Order Info */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">Demo Order Information</h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div className="flex items-start space-x-3">
              <User className="w-5 h-5 text-gray-400 mt-0.5" />
              <div>
                <p className="text-sm font-medium text-gray-500">Provider</p>
                <p className="text-sm text-gray-900">{orderData.provider.name}</p>
                <p className="text-xs text-gray-500">NPI: {orderData.provider.npi}</p>
              </div>
            </div>
            <div className="flex items-start space-x-3">
              <Package className="w-5 h-5 text-gray-400 mt-0.5" />
              <div>
                <p className="text-sm font-medium text-gray-500">Product</p>
                <p className="text-sm text-gray-900">{orderData.product.name}</p>
                <p className="text-xs text-gray-500">Qty: {orderData.product.quantity} x {orderData.product.size}</p>
              </div>
            </div>
            <div className="flex items-start space-x-3">
              <Building2 className="w-5 h-5 text-gray-400 mt-0.5" />
              <div>
                <p className="text-sm font-medium text-gray-500">Manufacturer</p>
                <p className="text-sm text-gray-900">{orderData.manufacturer}</p>
                <p className="text-xs text-gray-500">Status: {orderData.status}</p>
              </div>
            </div>
          </div>
        </div>

        {/* Process Steps */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <h2 className="text-lg font-semibold text-gray-900 mb-6">Order Process Workflow</h2>
          
          <div className="space-y-6">
            {steps.map((step, index) => {
              const Icon = step.icon;
              const isActive = currentStep === index;
              const isCompleted = currentStep > index;
              
              return (
                <div key={step.id} className="relative">
                  {index < steps.length - 1 && (
                    <div className={`absolute left-5 top-10 bottom-0 w-0.5 ${
                      isCompleted ? 'bg-green-500' : 'bg-gray-200'
                    }`} />
                  )}
                  
                  <div className="flex items-start space-x-4">
                    <div className={`
                      flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center
                      ${isCompleted ? 'bg-green-500 text-white' : 
                        isActive ? 'bg-blue-500 text-white animate-pulse' : 
                        'bg-gray-200 text-gray-400'}
                    `}>
                      {isCompleted ? (
                        <CheckCircle className="w-5 h-5" />
                      ) : (
                        <Icon className="w-5 h-5" />
                      )}
                    </div>
                    
                    <div className="flex-1">
                      <h3 className={`text-base font-medium ${
                        isActive ? 'text-gray-900' : 
                        isCompleted ? 'text-gray-700' : 
                        'text-gray-400'
                      }`}>
                        {step.title}
                      </h3>
                      <p className={`text-sm mt-1 ${
                        isActive ? 'text-gray-600' : 
                        isCompleted ? 'text-gray-500' : 
                        'text-gray-400'
                      }`}>
                        {step.description}
                      </p>
                      
                      {isActive && step.demoAction && (
                        <button
                          onClick={step.demoAction}
                          className="mt-3 inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors"
                        >
                          <Play className="w-4 h-4 mr-2" />
                          {step.id === 'ivr' ? 'Generate IVR Document' : 'Execute Step'}
                        </button>
                      )}
                      
                      {/* Special content for IVR step */}
                      {step.id === 'ivr' && isActive && showDocuSealForm && (
                        <div className="mt-4 p-4 bg-gray-50 rounded-lg">
                          <div className="mb-4">
                            <h4 className="text-sm font-medium text-gray-700 mb-2">
                              DocuSeal IVR Form Generation
                            </h4>
                            <p className="text-xs text-gray-500">
                              This form is auto-populated with 90% of the data from our system. 
                              Only minimal PHI is accessed from FHIR.
                            </p>
                          </div>
                          
                          {/* Simulated DocuSeal Form */}
                          <div className="bg-white border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                            <FileSignature className="w-12 h-12 text-gray-400 mx-auto mb-3" />
                            <p className="text-sm text-gray-600 mb-4">
                              DocuSeal IVR Form for {orderData.manufacturer}
                            </p>
                            <div className="animate-pulse space-y-3">
                              <div className="h-4 bg-gray-200 rounded w-3/4 mx-auto"></div>
                              <div className="h-4 bg-gray-200 rounded w-1/2 mx-auto"></div>
                              <div className="h-4 bg-gray-200 rounded w-2/3 mx-auto"></div>
                            </div>
                            <button
                              onClick={() => handleDocuSealComplete({})}
                              className="mt-6 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700"
                            >
                              Complete IVR Generation
                            </button>
                          </div>
                        </div>
                      )}
                      
                      {/* Show generated IVR */}
                      {step.id === 'ivr' && generatedIvrUrl && (
                        <div className="mt-4 p-4 bg-green-50 rounded-lg">
                          <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-3">
                              <FileText className="w-5 h-5 text-green-600" />
                              <div>
                                <p className="text-sm font-medium text-green-900">IVR Document Generated</p>
                                <p className="text-xs text-green-600">Ready to send to manufacturer</p>
                              </div>
                            </div>
                            <button className="text-green-600 hover:text-green-700">
                              <Download className="w-5 h-5" />
                            </button>
                          </div>
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        {/* Key Features */}
        <div className="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
          <div className="bg-blue-50 rounded-lg p-6">
            <Clock className="w-8 h-8 text-blue-600 mb-3" />
            <h3 className="text-base font-semibold text-gray-900 mb-2">90-Second Workflow</h3>
            <p className="text-sm text-gray-600">
              Complete the entire order process in under 90 seconds with our streamlined interface.
            </p>
          </div>
          
          <div className="bg-green-50 rounded-lg p-6">
            <FileSignature className="w-8 h-8 text-green-600 mb-3" />
            <h3 className="text-base font-semibold text-gray-900 mb-2">90% Auto-Population</h3>
            <p className="text-sm text-gray-600">
              IVR forms are automatically filled with data from our system, minimizing PHI access.
            </p>
          </div>
          
          <div className="bg-purple-50 rounded-lg p-6">
            <Mail className="w-8 h-8 text-purple-600 mb-3" />
            <h3 className="text-base font-semibold text-gray-900 mb-2">Automated Delivery</h3>
            <p className="text-sm text-gray-600">
              IVR documents are automatically emailed to manufacturers for quick processing.
            </p>
          </div>
        </div>

        {/* Demo Controls */}
        <div className="mt-8 bg-gray-50 rounded-lg p-6 text-center">
          <h3 className="text-base font-semibold text-gray-900 mb-4">Demo Controls</h3>
          <div className="space-x-4">
            <button
              onClick={() => {
                setCurrentStep(0);
                setShowDocuSealForm(false);
                setGeneratedIvrUrl(null);
                setOrderData({...orderData, status: 'pending_ivr'});
              }}
              className="px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-md hover:bg-gray-700"
            >
              Reset Demo
            </button>
            <button
              onClick={() => {
                if (currentStep < steps.length - 1) {
                  setCurrentStep(currentStep + 1);
                }
              }}
              className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700"
            >
              Skip to Next Step
            </button>
          </div>
        </div>
      </div>
    </MainLayout>
  );
};

export default OrderShowcase;