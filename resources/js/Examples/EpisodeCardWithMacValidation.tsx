import React from 'react';

/**
 * Visual Example: Enhanced Episode Card with MAC Validation
 * 
 * This shows how the Episode card looks with integrated MAC validation data.
 * The MAC validation panel provides immediate visibility into:
 * - Coverage risks
 * - Compliance status  
 * - Financial impact
 * - Actionable recommendations
 */

export default function EpisodeCardExample() {
  return (
    <div className="p-8 bg-gray-100">
      <h1 className="text-2xl font-bold mb-6">Episode Card with MAC Validation</h1>
      
      {/* Episode Card Container */}
      <div className="max-w-2xl mx-auto">
        <div className="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
          
          {/* Header Section */}
          <div className="p-5">
            <div className="flex items-start justify-between mb-4">
              <div className="flex items-start space-x-3">
                <div className="w-12 h-12 rounded-lg bg-gradient-to-br from-blue-600 to-purple-600 flex items-center justify-center text-white font-bold">
                  M
                </div>
                <div>
                  <h3 className="font-semibold text-lg">Patient #12345</h3>
                  <p className="text-sm text-gray-600">MediTech Solutions</p>
                </div>
              </div>
              <div className="flex items-center space-x-2 px-3 py-1.5 rounded-full bg-green-100 text-green-600">
                <span className="text-sm font-medium">Active</span>
              </div>
            </div>

            {/* Key Metrics */}
            <div className="grid grid-cols-3 gap-3 mb-4">
              <div className="p-3 rounded-lg bg-gray-50">
                <span className="text-xs text-gray-600">Orders</span>
                <p className="text-lg font-semibold">4</p>
              </div>
              <div className="p-3 rounded-lg bg-gray-50">
                <span className="text-xs text-gray-600">Value</span>
                <p className="text-lg font-semibold">$5,000</p>
              </div>
              <div className="p-3 rounded-lg bg-gray-50">
                <span className="text-xs text-gray-600">Days Active</span>
                <p className="text-lg font-semibold">7</p>
              </div>
            </div>

            {/* MAC Validation Indicator */}
            <div className="mt-3 p-2 rounded-lg bg-blue-50 flex items-center justify-between">
              <div className="flex items-center space-x-2">
                <svg className="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span className="text-xs font-medium text-gray-700">MAC Validation Status</span>
              </div>
              <span className="text-xs text-gray-600">Click to expand for details</span>
            </div>
          </div>

          {/* Expanded MAC Validation Panel */}
          <div className="border-t border-gray-200">
            <div className="p-4 bg-gradient-to-br from-blue-50 to-purple-50">
              
              {/* MAC Panel Header */}
              <div className="flex items-start justify-between mb-4">
                <div className="flex items-center space-x-2">
                  <svg className="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                  </svg>
                  <h4 className="font-semibold">MAC Validation Analysis</h4>
                </div>
                <div className="px-3 py-1 rounded-full text-sm font-semibold bg-yellow-100 text-yellow-700">
                  45% Risk
                </div>
              </div>

              {/* Coverage Status Grid */}
              <div className="grid grid-cols-2 gap-3 mb-4">
                <div className="p-3 rounded-lg bg-white">
                  <div className="flex items-center space-x-2 mb-1">
                    <svg className="w-4 h-4 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span className="text-xs font-medium text-gray-600">Coverage Status</span>
                  </div>
                  <p className="text-sm font-semibold">Conditional</p>
                </div>
                <div className="p-3 rounded-lg bg-white">
                  <div className="flex items-center space-x-2 mb-1">
                    <svg className="w-4 h-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                    <span className="text-xs font-medium text-gray-600">Approval Confidence</span>
                  </div>
                  <p className="text-sm font-semibold">55%</p>
                </div>
              </div>

              {/* MAC Contractor Info */}
              <div className="p-3 rounded-lg bg-gray-50 mb-3">
                <p className="text-xs text-gray-600">MAC Contractor</p>
                <p className="text-sm font-medium">Novitas Solutions (JL)</p>
              </div>

              {/* LCD Compliance Issues */}
              <div className="p-3 rounded-lg bg-yellow-50 mb-3">
                <p className="text-xs font-medium mb-2 text-yellow-700">LCD Compliance Issues</p>
                <p className="text-xs text-gray-700">• Prior auth not verified</p>
                <p className="text-xs text-gray-700">• Failed conservative treatment documentation needed</p>
              </div>

              {/* Risk Factors */}
              <div className="mb-3">
                <p className="text-xs font-medium mb-2 text-gray-600">Top Risk Factors</p>
                <div className="space-y-1">
                  <div className="p-2 rounded bg-white text-xs">
                    <div className="flex items-center justify-between">
                      <span>High-risk product category</span>
                      <span className="px-2 py-0.5 rounded bg-red-100 text-red-600">high</span>
                    </div>
                    <p className="mt-1 text-gray-600">💡 Ensure complete documentation</p>
                  </div>
                  <div className="p-2 rounded bg-white text-xs">
                    <div className="flex items-center justify-between">
                      <span>Episode value &gt; $5,000</span>
                      <span className="px-2 py-0.5 rounded bg-yellow-100 text-yellow-600">medium</span>
                    </div>
                  </div>
                </div>
              </div>

              {/* Financial Impact */}
              <div className="p-3 rounded-lg bg-white">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-xs text-gray-600">Potential Denial</p>
                    <p className="text-lg font-semibold text-red-500">$2,250</p>
                  </div>
                  <div className="text-right">
                    <p className="text-xs text-gray-600">Est. Reimbursement</p>
                    <p className="text-lg font-semibold text-green-500">$2,750</p>
                  </div>
                </div>
              </div>

              {/* Recommended Action */}
              <div className="mt-3 p-3 rounded-lg bg-blue-100">
                <p className="text-xs font-medium mb-1">Recommended Action</p>
                <p className="text-xs">Complete all required documentation: Failed conservative treatment documentation</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Benefits Section */}
      <div className="mt-8 max-w-2xl mx-auto">
        <h2 className="text-lg font-semibold mb-4">Key Benefits of MAC Validation Integration</h2>
        <div className="grid grid-cols-2 gap-4">
          <div className="p-4 bg-white rounded-lg shadow">
            <h3 className="font-medium text-blue-600 mb-2">Immediate Risk Visibility</h3>
            <p className="text-sm text-gray-600">See denial risks before submission with color-coded indicators</p>
          </div>
          <div className="p-4 bg-white rounded-lg shadow">
            <h3 className="font-medium text-green-600 mb-2">Financial Impact</h3>
            <p className="text-sm text-gray-600">Understand potential denial amounts and approval confidence</p>
          </div>
          <div className="p-4 bg-white rounded-lg shadow">
            <h3 className="font-medium text-purple-600 mb-2">Actionable Recommendations</h3>
            <p className="text-sm text-gray-600">Get specific steps to improve approval chances</p>
          </div>
          <div className="p-4 bg-white rounded-lg shadow">
            <h3 className="font-medium text-orange-600 mb-2">LCD Compliance</h3>
            <p className="text-sm text-gray-600">Track missing documentation and compliance issues</p>
          </div>
        </div>
      </div>
    </div>
  );
}