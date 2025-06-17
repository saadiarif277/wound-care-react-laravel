import React, { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';

export default function AuthStatus() {
    const { auth, props } = usePage().props as any;
    const [csrfToken, setCsrfToken] = useState<string | null>(null);
    const [cookies, setCookies] = useState<string>('');

    useEffect(() => {
        // Get CSRF token from meta tag
        const metaTag = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
        setCsrfToken(metaTag?.content || null);
        
        // Get current cookies
        setCookies(document.cookie);
    }, []);

    return (
        <div className="fixed bottom-4 right-4 bg-white shadow-lg rounded-lg p-4 max-w-md z-50">
            <h3 className="font-bold text-lg mb-2">Auth Debug Info</h3>
            
            <div className="space-y-2 text-sm">
                <div>
                    <strong>User:</strong> {auth?.user ? `${auth.user.name} (${auth.user.email})` : 'Not logged in'}
                </div>
                
                <div>
                    <strong>User ID:</strong> {auth?.user?.id || 'N/A'}
                </div>
                
                <div>
                    <strong>Roles:</strong> {auth?.user?.roles?.map((r: any) => r.name).join(', ') || 'None'}
                </div>
                
                <div>
                    <strong>CSRF Token:</strong> {csrfToken ? 'Present' : 'Missing'}
                </div>
                
                <div>
                    <strong>Session Cookie:</strong> {cookies.includes('laravel_session') ? 'Present' : 'Missing'}
                </div>
                
                <div>
                    <strong>XSRF Token:</strong> {cookies.includes('XSRF-TOKEN') ? 'Present' : 'Missing'}
                </div>
                
                <details className="mt-2">
                    <summary className="cursor-pointer text-blue-600">Full Auth Object</summary>
                    <pre className="mt-2 text-xs bg-gray-100 p-2 rounded overflow-auto max-h-40">
                        {JSON.stringify(auth, null, 2)}
                    </pre>
                </details>
                
                <details className="mt-2">
                    <summary className="cursor-pointer text-blue-600">All Props</summary>
                    <pre className="mt-2 text-xs bg-gray-100 p-2 rounded overflow-auto max-h-40">
                        {JSON.stringify(props, null, 2)}
                    </pre>
                </details>
            </div>
        </div>
    );
}