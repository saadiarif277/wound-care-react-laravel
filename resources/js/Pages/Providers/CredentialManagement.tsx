import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import {
    Shield,
    Upload,
    CheckCircle2,
    AlertTriangle,
    Calendar,
    FileText,
    Edit,
    Trash2,
    Plus,
    Download,
    Eye,
    Clock
} from 'lucide-react';

interface Credential {
    id: string;
    type: 'npi' | 'license' | 'certification' | 'insurance' | 'dea';
    name: string;
    number: string;
    issuing_state?: string;
    issuing_organization: string;
    issue_date: string;
    expiration_date: string;
    status: 'active' | 'expired' | 'pending_verification' | 'rejected';
    verification_status: 'verified' | 'pending' | 'failed' | 'not_required';
    document_url?: string;
    notes?: string;
    last_verified: string;
}

interface CredentialFormData {
    type: string;
    name: string;
    number: string;
    issuing_state: string;
    issuing_organization: string;
    issue_date: string;
    expiration_date: string;
    document: File | null;
    notes: string;
}

interface CredentialManagementProps {
    credentials: Credential[];
    user: {
        id: string;
        name: string;
        email: string;
        verification_status: string;
    };
}

export default function CredentialManagement({ credentials, user }: CredentialManagementProps) {
    const [showAddForm, setShowAddForm] = useState(false);
    const [editingCredential, setEditingCredential] = useState<Credential | null>(null);
    const [selectedCredentials, setSelectedCredentials] = useState<string[]>([]);

    const { data, setData, post, put, processing, errors, reset } = useForm<CredentialFormData>({
        type: '',
        name: '',
        number: '',
        issuing_state: '',
        issuing_organization: '',
        issue_date: '',
        expiration_date: '',
        document: null,
        notes: ''
    });

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'active': case 'verified': return 'bg-green-100 text-green-800 border-green-200';
            case 'pending_verification': case 'pending': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
            case 'expired': return 'bg-orange-100 text-orange-800 border-orange-200';
            case 'rejected': case 'failed': return 'bg-red-100 text-red-800 border-red-200';
            default: return 'bg-gray-100 text-gray-800 border-gray-200';
        }
    };

    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'active': case 'verified': return <CheckCircle2 className="h-4 w-4" />;
            case 'pending_verification': case 'pending': return <Clock className="h-4 w-4" />;
            case 'expired': case 'rejected': case 'failed': return <AlertTriangle className="h-4 w-4" />;
            default: return <FileText className="h-4 w-4" />;
        }
    };

    const isExpiringSoon = (expirationDate: string) => {
        const expDate = new Date(expirationDate);
        const today = new Date();
        const daysUntilExpiry = Math.ceil((expDate.getTime() - today.getTime()) / (1000 * 3600 * 24));
        return daysUntilExpiry <= 30 && daysUntilExpiry > 0;
    };

    const handleSubmit = () => {
        if (editingCredential) {
            put(`/api/v1/provider/credentials/${editingCredential.id}`, {
                onSuccess: () => {
                    setEditingCredential(null);
                    reset();
                }
            });
        } else {
            post('/api/v1/provider/credentials', {
                onSuccess: () => {
                    setShowAddForm(false);
                    reset();
                }
            });
        }
    };

    const handleEdit = (credential: Credential) => {
        setEditingCredential(credential);
        setData({
            type: credential.type,
            name: credential.name,
            number: credential.number,
            issuing_state: credential.issuing_state || '',
            issuing_organization: credential.issuing_organization,
            issue_date: credential.issue_date,
            expiration_date: credential.expiration_date,
            document: null,
            notes: credential.notes || ''
        });
        setShowAddForm(true);
    };

    const credentialTypes = [
        { value: 'npi', label: 'NPI Number' },
        { value: 'license', label: 'Medical License' },
        { value: 'certification', label: 'Board Certification' },
        { value: 'insurance', label: 'Malpractice Insurance' },
        { value: 'dea', label: 'DEA Registration' }
    ];

    const expiringCredentials = credentials.filter(cred =>
        isExpiringSoon(cred.expiration_date) && cred.status === 'active'
    );

    const pendingCredentials = credentials.filter(cred =>
        cred.verification_status === 'pending'
    );

    return (
        <MainLayout>
            <Head title="Credential Management" />

            <div className="py-8">
                <div className="max-w-6xl mx-auto sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="flex justify-between items-center mb-8">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">Credential Management</h1>
                            <p className="text-gray-600 mt-1">
                                Manage your professional credentials and certifications
                            </p>
                        </div>
                        <Button onClick={() => setShowAddForm(true)}>
                            <Plus className="h-4 w-4 mr-2" />
                            Add Credential
                        </Button>
                    </div>

                    {/* Alert Cards */}
                    {(expiringCredentials.length > 0 || pendingCredentials.length > 0) && (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            {expiringCredentials.length > 0 && (
                                <Card className="border-orange-200 bg-orange-50">
                                    <CardContent className="p-6">
                                        <div className="flex items-center gap-3">
                                            <AlertTriangle className="h-8 w-8 text-orange-600" />
                                            <div>
                                                <h3 className="text-lg font-semibold text-orange-900">
                                                    Expiring Soon
                                                </h3>
                                                <p className="text-orange-700">
                                                    {expiringCredentials.length} credential(s) expire within 30 days
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                            {pendingCredentials.length > 0 && (
                                <Card className="border-yellow-200 bg-yellow-50">
                                    <CardContent className="p-6">
                                        <div className="flex items-center gap-3">
                                            <Clock className="h-8 w-8 text-yellow-600" />
                                            <div>
                                                <h3 className="text-lg font-semibold text-yellow-900">
                                                    Pending Verification
                                                </h3>
                                                <p className="text-yellow-700">
                                                    {pendingCredentials.length} credential(s) awaiting verification
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    )}

                    {/* Add/Edit Form */}
                    {showAddForm && (
                        <Card className="mb-8">
                            <CardHeader>
                                <CardTitle>
                                    {editingCredential ? 'Edit Credential' : 'Add New Credential'}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Credential Type *
                                        </label>
                                        <select
                                            value={data.type}
                                            onChange={(e) => setData('type', e.target.value)}
                                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        >
                                            <option value="">Select type</option>
                                            {credentialTypes.map(type => (
                                                <option key={type.value} value={type.value}>
                                                    {type.label}
                                                </option>
                                            ))}
                                        </select>
                                        {errors.type && <p className="mt-1 text-sm text-red-600">{errors.type}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Credential Name *
                                        </label>
                                        <input
                                            type="text"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            placeholder="e.g., Board Certification in Internal Medicine"
                                        />
                                        {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Number/ID *
                                        </label>
                                        <input
                                            type="text"
                                            value={data.number}
                                            onChange={(e) => setData('number', e.target.value)}
                                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            placeholder="Credential number or ID"
                                        />
                                        {errors.number && <p className="mt-1 text-sm text-red-600">{errors.number}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Issuing Organization *
                                        </label>
                                        <input
                                            type="text"
                                            value={data.issuing_organization}
                                            onChange={(e) => setData('issuing_organization', e.target.value)}
                                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            placeholder="e.g., State Medical Board"
                                        />
                                        {errors.issuing_organization && <p className="mt-1 text-sm text-red-600">{errors.issuing_organization}</p>}
                                    </div>

                                    {data.type === 'license' && (
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                                Issuing State
                                            </label>
                                            <select
                                                value={data.issuing_state}
                                                onChange={(e) => setData('issuing_state', e.target.value)}
                                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            >
                                                <option value="">Select state</option>
                                                <option value="AL">Alabama</option>
                                                <option value="CA">California</option>
                                                <option value="FL">Florida</option>
                                                <option value="TX">Texas</option>
                                                {/* Add more states */}
                                            </select>
                                        </div>
                                    )}

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Issue Date *
                                        </label>
                                        <input
                                            type="date"
                                            value={data.issue_date}
                                            onChange={(e) => setData('issue_date', e.target.value)}
                                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        />
                                        {errors.issue_date && <p className="mt-1 text-sm text-red-600">{errors.issue_date}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Expiration Date *
                                        </label>
                                        <input
                                            type="date"
                                            value={data.expiration_date}
                                            onChange={(e) => setData('expiration_date', e.target.value)}
                                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        />
                                        {errors.expiration_date && <p className="mt-1 text-sm text-red-600">{errors.expiration_date}</p>}
                                    </div>

                                    <div className="md:col-span-2">
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Upload Document
                                        </label>
                                        <div className="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                                            <Upload className="mx-auto h-8 w-8 text-gray-400 mb-2" />
                                            <input
                                                type="file"
                                                accept=".pdf,.doc,.docx,.jpg,.png"
                                                onChange={(e) => setData('document', e.target.files?.[0] || null)}
                                                className="hidden"
                                                id="credential-upload"
                                            />
                                            <label
                                                htmlFor="credential-upload"
                                                className="cursor-pointer text-sm text-blue-600 hover:text-blue-500"
                                            >
                                                Click to upload or drag and drop
                                            </label>
                                            <p className="text-xs text-gray-500 mt-1">PDF, DOC, JPG, PNG up to 10MB</p>
                                        </div>
                                    </div>

                                    <div className="md:col-span-2">
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Notes
                                        </label>
                                        <textarea
                                            value={data.notes}
                                            onChange={(e) => setData('notes', e.target.value)}
                                            rows={3}
                                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            placeholder="Additional notes or comments"
                                        />
                                    </div>
                                </div>

                                <div className="mt-6 flex justify-end gap-3">
                                    <Button
                                        variant="secondary"
                                        onClick={() => {
                                            setShowAddForm(false);
                                            setEditingCredential(null);
                                            reset();
                                        }}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        onClick={handleSubmit}
                                        disabled={processing}
                                    >
                                        {processing ? 'Saving...' : editingCredential ? 'Update' : 'Add'} Credential
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Credentials List */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Your Credentials ({credentials.length})</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {credentials.length === 0 ? (
                                <div className="text-center py-12">
                                    <Shield className="mx-auto h-12 w-12 text-gray-400 mb-4" />
                                    <h3 className="text-lg font-medium text-gray-900 mb-2">No credentials added</h3>
                                    <p className="text-gray-600 mb-4">Add your professional credentials to get started</p>
                                    <Button onClick={() => setShowAddForm(true)}>
                                        <Plus className="h-4 w-4 mr-2" />
                                        Add First Credential
                                    </Button>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {credentials.map((credential) => (
                                        <div
                                            key={credential.id}
                                            className="border border-gray-200 rounded-lg p-6 hover:bg-gray-50"
                                        >
                                            <div className="flex items-start justify-between">
                                                <div className="flex-1">
                                                    <div className="flex items-center gap-3 mb-2">
                                                        <h3 className="text-lg font-medium text-gray-900">
                                                            {credential.name}
                                                        </h3>
                                                        <Badge className={getStatusColor(credential.status)}>
                                                            {getStatusIcon(credential.status)}
                                                            <span className="ml-1">{credential.status.replace('_', ' ')}</span>
                                                        </Badge>
                                                        <Badge className={getStatusColor(credential.verification_status)}>
                                                            {credential.verification_status}
                                                        </Badge>
                                                        {isExpiringSoon(credential.expiration_date) && (
                                                            <Badge variant="destructive">
                                                                <AlertTriangle className="h-3 w-3 mr-1" />
                                                                Expiring Soon
                                                            </Badge>
                                                        )}
                                                    </div>

                                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-600">
                                                        <div>
                                                            <span className="font-medium">Number:</span> {credential.number}
                                                        </div>
                                                        <div>
                                                            <span className="font-medium">Issued by:</span> {credential.issuing_organization}
                                                            {credential.issuing_state && ` (${credential.issuing_state})`}
                                                        </div>
                                                        <div className="flex items-center gap-1">
                                                            <Calendar className="h-4 w-4" />
                                                            <span className="font-medium">Expires:</span>
                                                            <span className={isExpiringSoon(credential.expiration_date) ? 'text-orange-600 font-medium' : ''}>
                                                                {new Date(credential.expiration_date).toLocaleDateString()}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    {credential.notes && (
                                                        <p className="mt-3 text-sm text-gray-600">
                                                            <span className="font-medium">Notes:</span> {credential.notes}
                                                        </p>
                                                    )}
                                                </div>

                                                <div className="flex items-center gap-2 ml-4">
                                                    {credential.document_url && (
                                                        <Button variant="secondary">
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                    <Button
                                                        variant="secondary"
                                                        onClick={() => handleEdit(credential)}
                                                    >
                                                        <Edit className="h-4 w-4" />
                                                    </Button>
                                                    <Button variant="secondary">
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </MainLayout>
    );
}
