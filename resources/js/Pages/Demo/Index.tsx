import React from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { 
  Package, 
  FileText, 
  Send, 
  CheckCircle,
  ArrowRight,
  Clock,
  DollarSign,
  Shield,
  Zap
} from 'lucide-react';

const DemoIndex: React.FC = () => {
  const demos = [
    {
      title: 'Complete Order Flow',
      description: 'Experience the full end-to-end workflow from product request to manufacturer approval',
      path: '/demo/complete-order-flow',
      features: [
        'Real ACZ Distribution products',
        'Live DocuSeal IVR generation',
        'Step-by-step process walkthrough',
        'Actual template ID integration'
      ],
      icon: Package,
      color: 'blue'
    },
    {
      title: 'DocuSeal IVR Form',
      description: 'Interactive ACZ Distribution IVR form with real template and 90% pre-filled data',
      path: '/demo/docuseal-ivr',
      features: [
        'Real Template ID: 852440',
        'Real Folder ID: 75423',
        '90% pre-populated fields',
        'Live DocuSeal integration'
      ],
      icon: FileText,
      color: 'green'
    },
    {
      title: 'Order Showcase',
      description: 'Quick overview of the ordering process with interactive demonstrations',
      path: '/demo/order-showcase',
      features: [
        'Interactive timeline',
        'Process visualization',
        'Key feature highlights',
        'Demo controls'
      ],
      icon: FileText,
      color: 'purple'
    }
  ];

  const keyFeatures = [
    {
      icon: Clock,
      title: '90-Second Workflow',
      description: 'Complete product requests in under 90 seconds'
    },
    {
      icon: FileText,
      title: '90% Auto-Population',
      description: 'IVR forms automatically filled from system data'
    },
    {
      icon: Shield,
      title: 'Minimal PHI Access',
      description: 'Only 10% PHI required from FHIR systems'
    },
    {
      icon: Send,
      title: 'Direct Manufacturer Delivery',
      description: 'Automated email to manufacturers for processing'
    },
    {
      icon: DollarSign,
      title: 'Commission Tracking',
      description: 'Real-time commission calculations and reporting'
    },
    {
      icon: Zap,
      title: 'DocuSeal Integration',
      description: 'Seamless document generation and management'
    }
  ];

  return (
    <MainLayout>
      <Head title="Demo Center" />
      
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="text-center mb-12">
          <h1 className="text-4xl font-bold text-gray-900 mb-4">
            MSC Wound Portal Demo Center
          </h1>
          <p className="text-xl text-gray-600 max-w-3xl mx-auto">
            Explore our innovative wound care ordering system with DocuSeal integration
          </p>
        </div>

        {/* Demo Options */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
          {demos.map((demo) => {
            const Icon = demo.icon;
            const colorClasses = demo.color === 'blue' 
              ? {
                  bg: 'bg-blue-100',
                  text: 'text-blue-600',
                  button: 'bg-blue-600 hover:bg-blue-700'
                }
              : demo.color === 'green'
              ? {
                  bg: 'bg-green-100',
                  text: 'text-green-600',
                  button: 'bg-green-600 hover:bg-green-700'
                }
              : {
                  bg: 'bg-purple-100',
                  text: 'text-purple-600',
                  button: 'bg-purple-600 hover:bg-purple-700'
                };
            
            return (
              <div
                key={demo.path}
                className="bg-white rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300"
              >
                <div className="p-6">
                  <div className={`w-12 h-12 ${colorClasses.bg} rounded-lg flex items-center justify-center mb-4`}>
                    <Icon className={`w-6 h-6 ${colorClasses.text}`} />
                  </div>
                  <h2 className="text-2xl font-semibold text-gray-900 mb-2">
                    {demo.title}
                  </h2>
                  <p className="text-gray-600 mb-4">
                    {demo.description}
                  </p>
                  <ul className="space-y-2 mb-6">
                    {demo.features.map((feature, index) => (
                      <li key={index} className="flex items-start">
                        <CheckCircle className="w-5 h-5 text-green-500 mr-2 flex-shrink-0 mt-0.5" />
                        <span className="text-sm text-gray-700">{feature}</span>
                      </li>
                    ))}
                  </ul>
                  <Link
                    href={demo.path}
                    className={`inline-flex items-center justify-center w-full px-6 py-3 ${colorClasses.button} text-white font-medium rounded-md transition-colors`}
                  >
                    Launch Demo
                    <ArrowRight className="w-4 h-4 ml-2" />
                  </Link>
                </div>
              </div>
            );
          })}
        </div>

        {/* Key Features */}
        <div className="bg-gray-50 rounded-lg p-8">
          <h2 className="text-2xl font-semibold text-gray-900 mb-6 text-center">
            Key Features of Our System
          </h2>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {keyFeatures.map((feature, index) => {
              const Icon = feature.icon;
              return (
                <div key={index} className="flex items-start space-x-3">
                  <div className="flex-shrink-0">
                    <div className="w-10 h-10 bg-white rounded-lg shadow-sm flex items-center justify-center">
                      <Icon className="w-5 h-5 text-blue-600" />
                    </div>
                  </div>
                  <div>
                    <h3 className="text-base font-medium text-gray-900">
                      {feature.title}
                    </h3>
                    <p className="text-sm text-gray-600 mt-1">
                      {feature.description}
                    </p>
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        {/* ACZ Distribution Highlight */}
        <div className="mt-12 bg-white rounded-lg shadow-lg p-8">
          <div className="text-center">
            <h2 className="text-2xl font-semibold text-gray-900 mb-4">
              Featured Integration: ACZ Distribution
            </h2>
            <p className="text-gray-600 mb-6 max-w-2xl mx-auto">
              Our system is fully integrated with ACZ Distribution's IVR process, 
              utilizing their specific DocuSeal folder (ID: 75423) and template (ID: 852440) 
              for seamless order processing.
            </p>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6 text-center">
              <div>
                <div className="text-3xl font-bold text-blue-600 mb-2">ACELL</div>
                <p className="text-sm text-gray-600">Cytal Wound Matrix Products</p>
              </div>
              <div>
                <div className="text-3xl font-bold text-green-600 mb-2">24-48hrs</div>
                <p className="text-sm text-gray-600">Typical Approval Time</p>
              </div>
              <div>
                <div className="text-3xl font-bold text-purple-600 mb-2">100%</div>
                <p className="text-sm text-gray-600">Digital Workflow</p>
              </div>
            </div>
          </div>
        </div>

        {/* Call to Action */}
        <div className="mt-12 text-center">
          <p className="text-lg text-gray-600 mb-4">
            Ready to streamline your wound care ordering process?
          </p>
          <Link
            href="/demo/complete-order-flow"
            className="inline-flex items-center px-8 py-4 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors"
          >
            Start Complete Demo
            <ArrowRight className="w-5 h-5 ml-2" />
          </Link>
        </div>
      </div>
    </MainLayout>
  );
};

export default DemoIndex;