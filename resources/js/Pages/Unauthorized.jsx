import React from 'react';
import { Link } from '@inertiajs/react';

const Unauthorized = () => {
    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-100">
            <div className="max-w-md w-full bg-white rounded-lg shadow-lg p-8">
                <div className="text-center">
                    <h1 className="text-4xl font-bold text-red-600 mb-4">Access Denied</h1>
                    <p className="text-gray-600 mb-6">
                        You don't have permission to access this page. Please contact your administrator if you believe this is an error.
                    </p>
                    <Link
                        href="/"
                        className="inline-block bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition-colors"
                    >
                        Return to Dashboard
                    </Link>
                </div>
            </div>
        </div>
    );
};

export default Unauthorized;
