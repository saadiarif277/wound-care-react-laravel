import React from 'react';
import { FileText, Shield, CheckCircle } from 'lucide-react';
import { DocusealForm } from '@docuseal/react';

interface BAAStepProps {
    data: any;
    setData: (field: string, value: any) => void;
    onComplete: () => void;
}

export default function BAAStep({ data, setData, onComplete }: BAAStepProps) {
    return (
        <div className="space-y-8">
            <div>
                <h2 className="text-2xl font-bold text-gray-900 flex items-center mb-2">
                    <Shield className="h-6 w-6 mr-3 text-purple-600" />
                    Business Associate Agreement
                </h2>
                <p className="text-gray-600">Review and sign the Business Associate Agreement to ensure HIPAA compliance</p>
            </div>

            <div className="bg-blue-50 border border-blue-200 rounded-lg p-6">
                <div className="flex items-start">
                    <FileText className="h-5 w-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" />
                    <div>
                        <h3 className="text-sm font-medium text-blue-900 mb-2">Why is this required?</h3>
                        <p className="text-sm text-blue-800">
                            The Business Associate Agreement (BAA) is a HIPAA-required contract that ensures 
                            all parties handling protected health information (PHI) maintain appropriate 
                            safeguards and comply with privacy regulations.
                        </p>
                    </div>
                </div>
            </div>

            <div className="border-2 border-gray-200 rounded-lg overflow-hidden">
                <div className="bg-gray-50 px-4 py-3 border-b border-gray-200">
                    <p className="text-sm text-gray-700 font-medium">
                        Please review and sign the agreement below. The next step will unlock automatically upon completion.
                    </p>
                </div>
                
                <div className="bg-white p-4">
                    <DocusealForm
                        src="https://docuseal.com/d/8odd3N6nPnLdMq"
                        onComplete={() => {
                            onComplete();
                        }}
                    />
                </div>
            </div>

            {data.baa_signed && (
                <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div className="flex items-center">
                        <CheckCircle className="h-5 w-5 text-green-600 mr-3" />
                        <div>
                            <p className="text-sm font-medium text-green-900">Agreement Signed Successfully</p>
                            <p className="text-sm text-green-800">
                                Signed on: {new Date(data.baa_signed_at).toLocaleString()}
                            </p>
                        </div>
                    </div>
                </div>
            )}

            <div className="text-sm text-gray-600 space-y-2">
                <p className="font-medium">Key points covered in the BAA:</p>
                <ul className="list-disc list-inside space-y-1 ml-2">
                    <li>Permitted uses and disclosures of PHI</li>
                    <li>Safeguards for protecting PHI</li>
                    <li>Breach notification requirements</li>
                    <li>Compliance with HIPAA regulations</li>
                    <li>Terms for agreement termination</li>
                </ul>
            </div>
        </div>
    );
} 