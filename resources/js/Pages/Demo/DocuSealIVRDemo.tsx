import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { DocuSealViewer } from '@/Components/DocuSeal/DocuSealViewer';
import { 
  FileText, 
  CheckCircle, 
  AlertCircle,
  User,
  Building2,
  Package,
  Heart,
  Calendar,
  MapPin,
  Phone,
  Mail,
  CreditCard
} from 'lucide-react';

const DocuSealIVRDemo: React.FC = () => {
  const [showForm, setShowForm] = useState(false);
  const [formCompleted, setFormCompleted] = useState(false);
  
  // ACZ Distribution real IDs
  const ACZ_FOLDER_ID = '75423';
  const ACZ_TEMPLATE_ID = '852440';
  
  // Pre-filled data for ACZ Distribution IVR form
  // Using exact field names from ACZ IVR template
  const preFilledData = {
    // Sales & Representative Information
    'Sales Rep Name': 'Demo Sales Representative',
    'Additional Emails': 'notifications@mscwoundcare.com',
    
    // Physician Information
    'Physician Name': 'Dr. Jane Smith',
    'Physician Specialty': 'Wound Care',
    'NPI': '1234567890',
    'Tax ID': '12-3456789',
    'PTAN': 'ABC123',
    'Medicaid #': 'MED789456',
    'Phone #': '(305) 555-0100',
    'Fax #': '(305) 555-0101',
    
    // Facility Information
    'Facility Name': 'Demo Medical Center',
    'Facility Address': '123 Medical Way',
    'City': 'Miami',
    'State': 'FL',
    'ZIP': '33101',
    'Contact Name': 'John Smith',
    'Contact Phone': '(305) 555-0102',
    
    // Patient Information (Minimal PHI)
    'Patient Name': 'JO**#7842', // Display ID only
    'Patient DOB': '01/15/1950',
    'Patient Address': '456 Patient St',
    'Patient City': 'Miami',
    'Patient State': 'FL',
    'Patient ZIP': '33102',
    'Patient Phone': '(305) 555-0200',
    
    // Insurance Information
    'Primary Insurance': 'Medicare',
    'Primary Policy Number': 'MED123456789',
    'Primary Payer Phone': '1-800-MEDICARE',
    'Secondary Insurance': '',
    'Secondary Policy Number': '',
    'Secondary Payer Phone': '',
    
    // Clinical Information
    'Place of Service': 'Physician Office (POS 11)',
    'Is Patient in Hospice': 'No',
    'Is Patient in Part A': 'No',
    'Global Period Status': 'No',
    'Location of Wound': 'Right foot, plantar surface',
    'ICD-10 Codes': 'L97.419, E11.621',
    'Total Wound Size': '6 sq cm',
    'Product': 'ACELL Cytal Wound Matrix (Q4256)',
    
    // Authorization
    'Prior Auth Permission': 'Yes',
  };

  const handleFormComplete = (data: any) => {
    console.log('Form completed:', data);
    setFormCompleted(true);
  };

  const dataCategories = [
    {
      title: 'Provider & Facility Info',
      icon: Building2,
      color: 'blue',
      percentage: '30%',
      fields: ['Physician Name & NPI', 'Tax ID & PTAN', 'Facility Name & Address', 'Contact Information'],
      description: 'Sourced from Provider Profile & Facility Records'
    },
    {
      title: 'Insurance Information',
      icon: CreditCard,
      color: 'green',
      percentage: '25%',
      fields: ['Primary/Secondary Insurance', 'Policy Numbers', 'Payer Phone Numbers'],
      description: 'From Product Request & Eligibility Data'
    },
    {
      title: 'Clinical Data (PHI)',
      icon: Heart,
      color: 'red',
      percentage: '10%',
      fields: ['ICD-10 Diagnosis Codes', 'Wound Location & Size', 'Place of Service'],
      description: 'Minimal PHI from Azure FHIR when needed'
    },
    {
      title: 'Product Details',
      icon: Package,
      color: 'purple',
      percentage: '20%',
      fields: ['Product Name & Code', 'HCPCS Code', 'Size & Quantity'],
      description: 'From Product Catalog & Request'
    },
    {
      title: 'Sales Rep Info',
      icon: User,
      color: 'orange',
      percentage: '15%',
      fields: ['Sales Rep Name', 'Contact Email', 'Territory Assignment'],
      description: 'From Organization & User Data'
    }
  ];

  return (
    <MainLayout>
      <Head title="ACZ Distribution IVR Demo" />
      
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900 mb-4">
            ACZ Distribution IVR Form Demo
          </h1>
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div className="flex items-start">
              <AlertCircle className="w-5 h-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" />
              <div>
                <p className="text-sm text-blue-800 font-medium">Live Integration</p>
                <p className="text-sm text-blue-700 mt-1">
                  This demo uses the actual ACZ Distribution DocuSeal template (ID: {ACZ_TEMPLATE_ID}) 
                  and folder (ID: {ACZ_FOLDER_ID}) with 90% pre-filled data.
                </p>
              </div>
            </div>
          </div>
        </div>

        {/* Data Pre-fill Overview */}
        <div className="mb-8">
          <h2 className="text-xl font-semibold text-gray-900 mb-4">
            Data Auto-Population Overview
          </h2>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {dataCategories.map((category) => {
              const Icon = category.icon;
              const colorClasses = category.color === 'blue' 
                ? { bg: 'bg-blue-100', text: 'text-blue-600', border: 'border-blue-200' }
                : category.color === 'green'
                ? { bg: 'bg-green-100', text: 'text-green-600', border: 'border-green-200' }
                : category.color === 'red'
                ? { bg: 'bg-red-100', text: 'text-red-600', border: 'border-red-200' }
                : category.color === 'purple'
                ? { bg: 'bg-purple-100', text: 'text-purple-600', border: 'border-purple-200' }
                : { bg: 'bg-orange-100', text: 'text-orange-600', border: 'border-orange-200' };

              return (
                <div key={category.title} className={`${colorClasses.bg} ${colorClasses.border} border rounded-lg p-4`}>
                  <div className="flex items-center justify-between mb-2">
                    <Icon className={`w-5 h-5 ${colorClasses.text}`} />
                    <span className={`text-lg font-semibold ${colorClasses.text}`}>
                      {category.percentage}
                    </span>
                  </div>
                  <h3 className="font-medium text-gray-900">{category.title}</h3>
                  <p className="text-xs text-gray-500 mt-1">{category.description}</p>
                  <ul className="mt-2 space-y-1">
                    {category.fields.map((field, idx) => (
                      <li key={idx} className="text-xs text-gray-600 flex items-center">
                        <CheckCircle className="w-3 h-3 text-green-500 mr-1" />
                        {field}
                      </li>
                    ))}
                  </ul>
                </div>
              );
            })}
          </div>
        </div>

        {/* Form Section */}
        <div className="bg-white rounded-lg shadow-lg">
          <div className="p-6 border-b border-gray-200">
            <div className="flex items-center justify-between">
              <div>
                <h2 className="text-xl font-semibold text-gray-900">
                  {formCompleted ? 'Form Completed!' : 'ACZ Distribution IVR Form'}
                </h2>
                <p className="text-sm text-gray-600 mt-1">
                  {formCompleted 
                    ? 'Thank you for completing the form. The IVR has been generated.'
                    : 'Click below to launch the pre-filled IVR form for ACELL products.'}
                </p>
              </div>
              {!showForm && !formCompleted && (
                <button
                  onClick={() => setShowForm(true)}
                  className="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition-colors"
                >
                  Launch IVR Form
                </button>
              )}
              {formCompleted && (
                <CheckCircle className="w-8 h-8 text-green-500" />
              )}
            </div>
          </div>

          {showForm && !formCompleted && (
            <div className="p-6">
              <DocuSealViewer 
                templateId={ACZ_TEMPLATE_ID}
                folderId={ACZ_FOLDER_ID}
                email="limitless@mscwoundcare.com"
                fields={preFilledData}
                name="ACZ Distribution IVR Form"
                onComplete={handleFormComplete}
                onError={(error) => console.error('DocuSeal error:', error)}
                className="min-h-[800px]"
                isDemo={true}
              />
            </div>
          )}

          {formCompleted && (
            <div className="p-6">
              <div className="bg-green-50 border border-green-200 rounded-lg p-6">
                <div className="flex items-start">
                  <CheckCircle className="w-6 h-6 text-green-600 mt-0.5 mr-3 flex-shrink-0" />
                  <div>
                    <h3 className="text-lg font-medium text-green-900">
                      IVR Successfully Generated
                    </h3>
                    <p className="text-sm text-green-800 mt-2">
                      The Insurance Verification Request has been generated and is ready to be sent to ACZ Distribution.
                    </p>
                    <div className="mt-4 space-y-2">
                      <div className="flex items-center text-sm text-green-700">
                        <FileText className="w-4 h-4 mr-2" />
                        Document ID: IVR-2024-{Math.floor(Math.random() * 10000)}
                      </div>
                      <div className="flex items-center text-sm text-green-700">
                        <Calendar className="w-4 h-4 mr-2" />
                        Generated: {new Date().toLocaleString()}
                      </div>
                      <div className="flex items-center text-sm text-green-700">
                        <Mail className="w-4 h-4 mr-2" />
                        Ready for manufacturer delivery
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Technical Details */}
        <div className="mt-8 bg-gray-50 rounded-lg p-6">
          <h3 className="text-lg font-medium text-gray-900 mb-4">Technical Implementation</h3>
          <div className="space-y-3 text-sm text-gray-600">
            <div className="flex items-start">
              <span className="font-medium mr-2">Template ID:</span>
              <code className="bg-white px-2 py-1 rounded text-xs">{ACZ_TEMPLATE_ID}</code>
            </div>
            <div className="flex items-start">
              <span className="font-medium mr-2">Folder ID:</span>
              <code className="bg-white px-2 py-1 rounded text-xs">{ACZ_FOLDER_ID}</code>
            </div>
            <div className="flex items-start">
              <span className="font-medium mr-2">Integration:</span>
              <span>DocuSeal React Component with JWT Authentication</span>
            </div>
            <div className="flex items-start">
              <span className="font-medium mr-2">Data Source:</span>
              <span>90% from Portal Database, 10% from Azure FHIR</span>
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
};

export default DocuSealIVRDemo;