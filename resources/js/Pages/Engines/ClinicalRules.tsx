import React from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';

export default function ClinicalRules() {
    return (
        <MainLayout>
            <Head title="Clinical Rules Engine" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <h1 className="text-2xl font-semibold mb-6">Clinical Rules Engine</h1>
                            <p className="text-gray-600">
                                Configure and manage clinical decision rules for wound care treatment recommendations.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
