import React, { useState } from 'react';
import { testCSRFToken, refreshCSRFToken, getCurrentCSRFToken } from '@/lib/csrf';

export default function CSRFTestButton() {
    const [testing, setTesting] = useState(false);
    const [result, setResult] = useState<string | null>(null);

    const runCSRFTest = async () => {
        setTesting(true);
        setResult(null);

        try {
            const currentToken = getCurrentCSRFToken();
            console.log('Current CSRF token:', currentToken?.substring(0, 10) + '...');

            const isValid = await testCSRFToken();

            if (isValid) {
                setResult('✅ CSRF token is valid');
            } else {
                setResult('❌ CSRF token is invalid, attempting refresh...');

                const newToken = await refreshCSRFToken();
                if (newToken) {
                    const retestValid = await testCSRFToken();
                    if (retestValid) {
                        setResult('✅ CSRF token refreshed and now valid');
                    } else {
                        setResult('❌ CSRF token still invalid after refresh');
                    }
                } else {
                    setResult('❌ Failed to refresh CSRF token');
                }
            }
        } catch (error) {
            console.error('CSRF test error:', error);
            setResult('❌ Error testing CSRF token: ' + (error as Error).message);
        } finally {
            setTesting(false);
        }
    };

    return (
        <div className="p-4 border border-gray-300 rounded-lg bg-gray-50 dark:bg-gray-800 dark:border-gray-600">
            <h3 className="text-lg font-semibold mb-2 text-gray-900 dark:text-white">
                CSRF Token Test
            </h3>
            <button
                onClick={runCSRFTest}
                disabled={testing}
                className="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
            >
                {testing ? 'Testing...' : 'Test CSRF Token'}
            </button>
            {result && (
                <div className="mt-2 p-2 rounded bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white">
                    {result}
                </div>
            )}
        </div>
    );
}
