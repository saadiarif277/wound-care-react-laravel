import React, { useState } from 'react';
import { apiGet, handleApiResponse } from '@/lib/api';

export default function TestAuthentication() {
    const [testResult, setTestResult] = useState<string>('');
    const [loading, setLoading] = useState(false);

    const testApiCall = async () => {
        setLoading(true);
        setTestResult('Testing API authentication...');
        
        try {
            // Test the templates endpoint
            const response = await apiGet('/api/v1/docuseal/templates');
            const data = await handleApiResponse(response);
            
            setTestResult(`✅ Authentication successful! Found ${data.templates?.length || 0} templates.`);
        } catch (error: any) {
            if (error.message.includes('401')) {
                setTestResult('❌ Authentication failed: Unauthorized (401). Please check if you are logged in.');
            } else if (error.message.includes('403')) {
                setTestResult('❌ Authentication failed: Forbidden (403). You do not have the "manage-orders" permission.');
            } else if (error.message.includes('DOCUSEAL_API_KEY')) {
                setTestResult('❌ Server configuration error: DocuSeal API key is not configured.');
            } else {
                setTestResult(`❌ Error: ${error.message}`);
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="p-4 bg-white rounded-lg shadow">
            <h3 className="text-lg font-semibold mb-4">DocuSeal Authentication Test</h3>
            
            <button
                onClick={testApiCall}
                disabled={loading}
                className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
            >
                {loading ? 'Testing...' : 'Test API Authentication'}
            </button>
            
            {testResult && (
                <div className="mt-4 p-4 bg-gray-100 rounded">
                    <pre className="whitespace-pre-wrap">{testResult}</pre>
                </div>
            )}
            
            <div className="mt-4 text-sm text-gray-600">
                <p>This test will verify:</p>
                <ul className="list-disc list-inside mt-2">
                    <li>You are logged in (authentication)</li>
                    <li>You have the "manage-orders" permission (authorization)</li>
                    <li>The DocuSeal API key is configured on the server</li>
                    <li>The API endpoint is accessible</li>
                </ul>
            </div>
        </div>
    );
}