import { useState, useEffect } from 'react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
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

export function IvrFieldPreview({ formData, manufacturer, className }: IvrFieldPreviewProps) {
    const [fields, setFields] = useState<any[]>([]);
    const [coverage, setCoverage] = useState<FieldCoverage | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    // Theme context with fallback
    let theme: 'dark' | 'light' = 'dark';
    let t = themes.dark;

    try {
        const themeContext = useTheme();
        theme = themeContext.theme;
        t = themes[theme];
    } catch (e) {
        // Fallback to dark theme if outside ThemeProvider
    }

    useEffect(() => {
        const fetchFieldsAndCoverage = async () => {
            try {
                setLoading(true);
                setError(null);

                // Get manufacturer fields
                const fieldsResponse = await fetch(`/api/v1/ivr/manufacturers/${manufacturer}/fields`);
                if (!fieldsResponse.ok) {
                    throw new Error('Failed to fetch manufacturer fields');
                }
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

                if (!coverageResponse.ok) {
                    throw new Error('Failed to calculate coverage');
                }

                const coverageData = await coverageResponse.json();
                setCoverage(coverageData.coverage);
            } catch (error) {
                console.error('Error fetching IVR data:', error);
                setError(error instanceof Error ? error.message : 'Failed to load IVR preview');
            } finally {
                setLoading(false);
            }
        };

        if (manufacturer) {
            fetchFieldsAndCoverage();
        }
    }, [manufacturer, formData]);

    if (loading) {
        return (
            <GlassCard variant="primary" className={cn("p-6", className)}>
                <div className="animate-pulse">
                    <div className="h-6 bg-gray-700/50 rounded w-1/3 mb-4"></div>
                    <div className="space-y-2">
                        <div className="h-4 bg-gray-700/30 rounded w-full"></div>
                        <div className="h-4 bg-gray-700/30 rounded w-3/4"></div>
                        <div className="h-4 bg-gray-700/30 rounded w-5/6"></div>
                    </div>
                </div>
            </GlassCard>
        );
    }

    if (error) {
        return (
            <GlassCard variant="danger" className={cn("p-6", className)}>
                <p className="text-red-400">Error loading IVR preview: {error}</p>
            </GlassCard>
        );
    }

    const getCoverageColor = (level: string) => {
        switch (level) {
            case 'excellent': return 'text-green-400';
            case 'good': return 'text-blue-400';
            case 'fair': return 'text-yellow-400';
            case 'poor': return 'text-red-400';
            default: return t.text.muted;
        }
    };

    const getCoverageBarColor = (percentage: number) => {
        if (percentage >= 90) return 'bg-green-500';
        if (percentage >= 75) return 'bg-blue-500';
        if (percentage >= 50) return 'bg-yellow-500';
        return 'bg-red-500';
    };

    return (
        <GlassCard variant="primary" className={cn("p-6", className)}>
            <Heading level={3} className="mb-6">IVR Field Coverage Preview</Heading>

            {coverage && (
                <>
                    {/* Coverage Summary */}
                    <div className="mb-6">
                        <div className="flex items-center gap-4 mb-3">
                            <span className="text-4xl font-bold">{coverage.percentage}%</span>
                            <span className={cn(
                                "text-sm font-medium uppercase tracking-wider",
                                getCoverageColor(coverage.coverage_level)
                            )}>
                                {coverage.coverage_level} Coverage
                            </span>
                        </div>

                        <p className={cn("text-sm mb-3", t.text.muted)}>
                            {coverage.filled_fields} of {coverage.total_fields} fields will be auto-filled
                        </p>

                        {/* Progress bar */}
                        <div className={cn(
                            "rounded-full h-3 overflow-hidden",
                            theme === 'dark' ? 'bg-gray-800' : 'bg-gray-200'
                        )}>
                            <div
                                className={cn(
                                    "h-full rounded-full transition-all duration-500",
                                    getCoverageBarColor(coverage.percentage)
                                )}
                                style={{ width: `${coverage.percentage}%` }}
                            />
                        </div>
                    </div>

                    {/* Field Status Lists */}
                    <div className="space-y-6">
                        {coverage.extracted_fields.length > 0 && (
                            <div>
                                <h4 className={cn(
                                    "font-medium mb-3 flex items-center gap-2",
                                    "text-green-400"
                                )}>
                                    <span className="text-lg">✓</span>
                                    Auto-filled Fields ({coverage.extracted_fields.length})
                                </h4>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    {coverage.extracted_fields.map((field, idx) => (
                                        <div
                                            key={idx}
                                            className={cn(
                                                "px-3 py-2 rounded-lg text-sm",
                                                theme === 'dark'
                                                    ? 'bg-green-900/20 text-green-300'
                                                    : 'bg-green-50 text-green-700'
                                            )}
                                        >
                                            • {field}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {coverage.missing_fields.length > 0 && (
                            <div>
                                <h4 className={cn(
                                    "font-medium mb-3 flex items-center gap-2",
                                    "text-amber-400"
                                )}>
                                    <span className="text-lg">⚠</span>
                                    Manual Entry Required ({coverage.missing_fields.length})
                                </h4>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    {coverage.missing_fields.map((field, idx) => (
                                        <div
                                            key={idx}
                                            className={cn(
                                                "px-3 py-2 rounded-lg text-sm",
                                                theme === 'dark'
                                                    ? 'bg-amber-900/20 text-amber-300'
                                                    : 'bg-amber-50 text-amber-700'
                                            )}
                                        >
                                            • {field}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Coverage Insights */}
                    <div className={cn(
                        "mt-6 p-4 rounded-lg",
                        theme === 'dark' ? 'bg-gray-800/50' : 'bg-gray-100'
                    )}>
                        <p className={cn("text-sm", t.text.muted)}>
                            {coverage.percentage >= 90 && "Excellent coverage! Most fields will be auto-populated from your episode data."}
                            {coverage.percentage >= 75 && coverage.percentage < 90 && "Good coverage. Some manual entry will be required for manufacturer-specific fields."}
                            {coverage.percentage >= 50 && coverage.percentage < 75 && "Fair coverage. Several fields will require manual entry to complete the IVR."}
                            {coverage.percentage < 50 && "Low coverage. Most fields will need manual entry. Consider adding more clinical data to improve auto-population."}
                        </p>
                    </div>
                </>
            )}
        </GlassCard>
    );
}
