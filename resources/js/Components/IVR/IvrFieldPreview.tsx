import { useState, useEffect } from 'react';

interface IvrFieldPreviewProps {
    formData: any;
    manufacturer: string;
}

interface FieldCoverage {
    total_fields: number;
    filled_fields: number;
    missing_fields: string[];
    extracted_fields: string[];
    percentage: number;
    coverage_level: 'excellent' | 'good' | 'fair' | 'poor';
}

export function IvrFieldPreview({ formData, manufacturer }: IvrFieldPreviewProps) {
    const [fields, setFields] = useState<any[]>([]);
    const [coverage, setCoverage] = useState<FieldCoverage | null>(null);
    const [loading, setLoading] = useState(true);
    
    useEffect(() => {
        const fetchFieldsAndCoverage = async () => {
            try {
                // Get manufacturer fields
                const fieldsResponse = await fetch(`/api/v1/ivr/manufacturers/${manufacturer}/fields`);
                const fieldsData = await fieldsResponse.json();
                setFields(fieldsData.fields || []);
                
                // Calculate coverage
                const coverageResponse = await fetch('/api/v1/ivr/calculate-coverage', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        manufacturer_key: manufacturer,
                        form_data: formData,
                        patient_data: formData.patient_fhir_data || {}
                    })
                });
                const coverageData = await coverageResponse.json();
                setCoverage(coverageData.coverage);
            } catch (error) {
                console.error('Error fetching IVR data:', error);
            } finally {
                setLoading(false);
            }
        };
        
        fetchFieldsAndCoverage();
    }, [manufacturer, formData]);
    
    if (loading) {
        return <div className="text-center py-4">Loading IVR field preview...</div>;
    }
    
    const getCoverageColor = (level: string) => {
        switch (level) {
            case 'excellent': return 'text-green-600';
            case 'good': return 'text-blue-600';
            case 'fair': return 'text-yellow-600';
            case 'poor': return 'text-red-600';
            default: return 'text-gray-600';
        }
    };
    
    return (
        <div className="bg-white rounded-lg shadow p-6">
            <h3 className="text-lg font-semibold mb-4">IVR Field Coverage Preview</h3>
            
            {coverage && (
                <div className="mb-6">
                    <div className="flex items-center gap-4 mb-2">
                        <span className="text-3xl font-bold">{coverage.percentage}%</span>
                        <span className={`text-sm font-medium ${getCoverageColor(coverage.coverage_level)}`}>
                            {coverage.coverage_level.charAt(0).toUpperCase() + coverage.coverage_level.slice(1)} Coverage
                        </span>
                    </div>
                    <div className="text-sm text-gray-600">
                        {coverage.filled_fields} of {coverage.total_fields} fields will be auto-filled
                    </div>
                    
                    {/* Progress bar */}
                    <div className="mt-2 bg-gray-200 rounded-full h-2">
                        <div 
                            className="bg-blue-600 h-2 rounded-full transition-all duration-300"
                            style={{ width: `${coverage.percentage}%` }}
                        />
                    </div>
                </div>
            )}
            
            {/* Field status list */}
            <div className="space-y-4">
                <div>
                    <h4 className="font-medium text-green-600 mb-2">
                        ✓ Auto-filled Fields ({coverage?.extracted_fields?.length || 0})
                    </h4>
                    {coverage && coverage.extracted_fields && coverage.extracted_fields.length > 0 ? (
                        <ul className="text-sm space-y-1">
                            {coverage.extracted_fields.map((field, idx) => (
                                <li key={idx} className="text-gray-600">• {field}</li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-sm text-gray-500 italic">No fields will be auto-filled</p>
                    )}
                </div>
                
                {coverage && coverage.missing_fields && coverage.missing_fields.length > 0 && (
                    <div>
                        <h4 className="font-medium text-amber-600 mb-2">
                            ⚠ Manual Entry Required ({coverage.missing_fields.length})
                        </h4>
                        <ul className="text-sm space-y-1">
                            {coverage.missing_fields.map((field, idx) => (
                                <li key={idx} className="text-gray-600">• {field}</li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </div>
    );
}
