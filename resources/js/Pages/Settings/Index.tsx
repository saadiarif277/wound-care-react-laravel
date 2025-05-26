import React from 'react';
import { Head } from '@inertiajs/react';
import Layout from '@/Layouts/Layout';

export default function Index() {
    return (
        <Layout>
            <Head title="Settings" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <h1 className="text-2xl font-semibold mb-6">Settings</h1>
                            <p className="text-gray-600">
                                Configure application settings and preferences.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </Layout>
    );
}
