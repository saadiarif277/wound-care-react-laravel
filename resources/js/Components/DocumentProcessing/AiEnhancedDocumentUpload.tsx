import React, { useState, useCallback } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Loader2, Upload, Brain, CheckCircle, AlertCircle } from 'lucide-react';
import axios from 'axios'; // Added axios import

interface DocumentData {
    [key: string]: any;
}

interface ProcessingResult {
    success: boolean;
    original_ocr?: DocumentData;
    ai_enhanced?: DocumentData;
    confidence_scores?: Record<string, number>;
    quality_grade?: string;
    suggestions?: string[];
    processing_notes?: string[];
    medical_validation?: {
        total_terms: number;
        valid_terms: number;
        overall_confidence: number;
        suggestions?: string[];
    };
    is_ai_enhanced?: boolean;
    document_type?: string;
    filename?: string;
    message?: string;
}

interface ServiceHealth {
    accessible: boolean;
    status: string;
    total_terms?: number;
    domains?: string[];
    error?: string;
}

const AI_ENHANCED_DOCUMENT_TYPES = [
    { value: 'insurance_card', label: 'Insurance Card' },
    { value: 'clinical_note', label: 'Clinical Note' },
    { value: 'wound_photo', label: 'Wound Photo' },
    { value: 'prescription', label: 'Prescription' },
    { value: 'other', label: 'Other Document' }
];

export default function AiEnhancedDocumentUpload() {
    const [file, setFile] = useState<File | null>(null);
    const [documentType, setDocumentType] = useState<string>('insurance_card');
    const [processing, setProcessing] = useState<boolean>(false);
    const [result, setResult] = useState<ProcessingResult | null>(null);
    const [serviceHealth, setServiceHealth] = useState<ServiceHealth | null>(null);
    const [activeTab, setActiveTab] = useState<string>('upload');
    const [targetFields, setTargetFields] = useState<string>('');
    const [formContext, setFormContext] = useState<string>('general');
    const [progress, setProgress] = useState<number>(0); // Added progress state

    // Check AI service health on component mount
    React.useEffect(() => {
        checkServiceHealth();
    }, []);

    const checkServiceHealth = useCallback(async () => {
        try {
            const response = await fetch('/api/document/ai-service-status');
            const data = await response.json();
            
            if (data.success) {
                setServiceHealth({
                    accessible: data.ai_service_health.accessible,
                    status: data.ai_service_health.status,
                    total_terms: data.terminology_stats.total_terms,
                    domains: data.terminology_stats.domains,
                });
            }
        } catch (error) {
            console.error('Failed to check service health:', error);
            setServiceHealth({
                accessible: false,
                status: 'error',
                error: 'Failed to check service status'
            });
        }
    }, []);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const selectedFile = e.target.files?.[0];
        if (selectedFile) {
            setFile(selectedFile);
            setResult(null);
        }
    };

    const processDocument = async () => {
        if (!file) return;

        setProcessing(true);
        setResult(null);

        try {
            const formData = new FormData();
            formData.append('document', file);
            formData.append('type', documentType);
            formData.append('form_context', formContext);

            // Add target fields if specified
            if (targetFields.trim()) {
                const fieldsArray = targetFields.split(',').map(field => field.trim()).filter(Boolean);
                fieldsArray.forEach((field, index) => {
                    formData.append(`target_fields[${index}]`, field);
                });
            }

            const response = await axios.post('/api/document/process-with-ai', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
                onUploadProgress: (progressEvent) => {
                    const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                    setProgress(percentCompleted);
                }
            });

            const data = response.data;
            setResult(data);
            
            if (data.success) {
                setActiveTab('results');
            }
        } catch (error) {
            console.error('Document processing failed:', error);
            setResult({
                success: false,
                message: 'An error occurred while processing the document.'
            });
        } finally {
            setProcessing(false);
        }
    };

    const getGradeColor = (grade: string) => {
        switch (grade) {
            case 'A': return 'bg-green-100 text-green-800';
            case 'B': return 'bg-green-100 text-green-700';
            case 'C': return 'bg-yellow-100 text-yellow-800';
            case 'D': return 'bg-orange-100 text-orange-800';
            case 'F': return 'bg-red-100 text-red-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    const renderDataComparison = () => {
        if (!result || !result.success) return null;

        const ocrData = result.original_ocr || {};
        const aiData = result.ai_enhanced || {};

        return (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Upload className="w-4 h-4" />
                            Original OCR Data
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            {Object.entries(ocrData).map(([key, value]) => (
                                <div key={key} className="flex justify-between">
                                    <span className="font-medium text-sm">{key}:</span>
                                    <span className="text-sm text-gray-600">{String(value)}</span>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Brain className="w-4 h-4" />
                            AI-Enhanced Data
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            {Object.entries(aiData).map(([key, value]) => {
                                const confidence = result.confidence_scores?.[key];
                                return (
                                    <div key={key} className="flex justify-between items-center">
                                        <span className="font-medium text-sm">{key}:</span>
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm text-gray-600">{String(value)}</span>
                                            {confidence && (
                                                <Badge variant="secondary" className="text-xs">
                                                    {(confidence * 100).toFixed(0)}%
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>
            </div>
        );
    };

    const renderMedicalValidation = () => {
        if (!result?.medical_validation) return null;

        const validation = result.medical_validation;
        const accuracy = validation.total_terms > 0 ? (validation.valid_terms / validation.total_terms) * 100 : 0;

        return (
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <CheckCircle className="w-4 h-4" />
                        Medical Terminology Validation
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="space-y-3">
                        <div className="flex justify-between items-center">
                            <span>Terms Validated:</span>
                            <span>{validation.valid_terms} / {validation.total_terms}</span>
                        </div>
                        <div className="flex justify-between items-center">
                            <span>Accuracy:</span>
                            <Badge className={accuracy >= 80 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}>
                                {accuracy.toFixed(1)}%
                            </Badge>
                        </div>
                        <div className="flex justify-between items-center">
                            <span>Overall Confidence:</span>
                            <Badge variant="outline">
                                {(validation.overall_confidence * 100).toFixed(1)}%
                            </Badge>
                        </div>
                        {validation.suggestions && validation.suggestions.length > 0 && (
                            <div>
                                <p className="font-medium text-sm mb-2">Suggestions:</p>
                                <ul className="text-sm text-gray-600 space-y-1">
                                    {validation.suggestions.map((suggestion, index) => (
                                        <li key={index} className="flex items-start gap-2">
                                            <span className="text-blue-500">•</span>
                                            {suggestion}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>
        );
    };

    return (
        <div className="max-w-4xl mx-auto p-6">
            <div className="mb-6">
                <h1 className="text-2xl font-bold mb-2">AI-Enhanced Document Processing</h1>
                <p className="text-gray-600">
                    Upload documents and let AI intelligently extract and structure data with medical terminology validation.
                </p>
            </div>

            {/* Service Health Status */}
            {serviceHealth && (
                <Alert className={`mb-4 ${serviceHealth.accessible ? 'border-green-200' : 'border-red-200'}`}>
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                        <div className="flex items-center justify-between">
                            <span>
                                AI Service Status: <Badge className={serviceHealth.accessible ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}>
                                    {serviceHealth.status}
                                </Badge>
                            </span>
                            {serviceHealth.total_terms && (
                                <span className="text-sm text-gray-600">
                                    {serviceHealth.total_terms.toLocaleString()} medical terms available
                                </span>
                            )}
                        </div>
                    </AlertDescription>
                </Alert>
            )}

            <Tabs value={activeTab} onValueChange={setActiveTab}>
                <TabsList className="grid w-full grid-cols-2">
                    <TabsTrigger value="upload">Upload & Process</TabsTrigger>
                    <TabsTrigger value="results" disabled={!result}>Results</TabsTrigger>
                </TabsList>

                <TabsContent value="upload" className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Document Upload</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <Label htmlFor="file">Select Document</Label>
                                <Input
                                    id="file"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.pdf"
                                    onChange={handleFileChange}
                                    className="mt-1"
                                />
                            </div>

                            <div>
                                <Label htmlFor="document-type">Document Type</Label>
                                <select
                                    id="document-type"
                                    value={documentType}
                                    onChange={(e) => setDocumentType(e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    {AI_ENHANCED_DOCUMENT_TYPES.map(type => (
                                        <option key={type.value} value={type.value}>
                                            {type.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <Label htmlFor="form-context">Form Context</Label>
                                <Input
                                    id="form-context"
                                    value={formContext}
                                    onChange={(e) => setFormContext(e.target.value)}
                                    placeholder="e.g., wound_care, insurance, clinical"
                                    className="mt-1"
                                />
                            </div>

                            <div>
                                <Label htmlFor="target-fields">Target Fields (Optional)</Label>
                                <Input
                                    id="target-fields"
                                    value={targetFields}
                                    onChange={(e) => setTargetFields(e.target.value)}
                                    placeholder="e.g., patient_name, member_id, wound_location"
                                    className="mt-1"
                                />
                                <p className="text-sm text-gray-500 mt-1">
                                    Comma-separated field names to focus extraction on
                                </p>
                            </div>

                            <Button 
                                onClick={processDocument} 
                                disabled={!file || processing}
                                className="w-full"
                            >
                                {processing ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Processing with AI...
                                    </>
                                ) : (
                                    <>
                                        <Brain className="mr-2 h-4 w-4" />
                                        Process with AI
                                    </>
                                )}
                            </Button>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="results" className="space-y-4">
                    {result && (
                        <>
                            {/* Processing Summary */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center justify-between">
                                        <span>Processing Summary</span>
                                        {result.quality_grade && (
                                            <Badge className={getGradeColor(result.quality_grade)}>
                                                Grade: {result.quality_grade}
                                            </Badge>
                                        )}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        <div className="flex justify-between">
                                            <span>Status:</span>
                                            <Badge className={result.success ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}>
                                                {result.success ? 'Success' : 'Failed'}
                                            </Badge>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>AI Enhanced:</span>
                                            <Badge className={result.is_ai_enhanced ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'}>
                                                {result.is_ai_enhanced ? 'Yes' : 'No'}
                                            </Badge>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>Document Type:</span>
                                            <span className="capitalize">{result.document_type}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>Filename:</span>
                                            <span className="text-sm">{result.filename}</span>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Data Comparison */}
                            {renderDataComparison()}

                            {/* Medical Validation */}
                            {renderMedicalValidation()}

                            {/* Suggestions */}
                            {result.suggestions && result.suggestions.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>AI Suggestions</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <ul className="space-y-2">
                                            {result.suggestions.map((suggestion, index) => (
                                                <li key={index} className="flex items-start gap-2">
                                                    <span className="text-blue-500">•</span>
                                                    <span className="text-sm">{suggestion}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    </CardContent>
                                </Card>
                            )}

                            {/* Processing Notes */}
                            {result.processing_notes && result.processing_notes.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Processing Notes</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <ul className="space-y-2">
                                            {result.processing_notes.map((note, index) => (
                                                <li key={index} className="text-sm text-gray-600">
                                                    {note}
                                                </li>
                                            ))}
                                        </ul>
                                    </CardContent>
                                </Card>
                            )}
                        </>
                    )}
                </TabsContent>
            </Tabs>
        </div>
    );
} 