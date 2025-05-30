import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Progress } from '@/Components/ui/progress';
import {
    Building2,
    Users,
    TrendingUp,
    AlertCircle,
    CheckCircle2,
    Clock,
    Search,
    Filter,
    Plus,
    Eye,
    Edit,
    Mail,
    MoreHorizontal,
    Download
} from 'lucide-react';

interface Organization {
    id: string;
    name: string;
    type: string;
    status: 'pending' | 'active' | 'inactive' | 'suspended';
    created_at: string;
    onboarding_progress: number;
    onboarding_status: 'not_started' | 'in_progress' | 'completed' | 'needs_attention';
    total_users: number;
    total_orders: number;
    monthly_revenue: number;
    primary_contact: {
        name: string;
        email: string;
        phone: string;
    };
    sales_rep: {
        name: string;
        email: string;
    };
    compliance_status: 'compliant' | 'pending_documents' | 'expired' | 'non_compliant';
    last_activity: string;
}

interface DashboardStats {
    total_organizations: number;
    active_organizations: number;
    pending_onboarding: number;
    total_revenue: number;
    growth_rate: number;
    compliance_alerts: number;
}

interface CustomerManagementDashboardProps {
    organizations: Organization[];
    stats: DashboardStats;
    filters: {
        status: string;
        type: string;
        onboarding_status: string;
        sales_rep: string;
    };
}

export default function CustomerManagementDashboard({
    organizations,
    stats,
    filters
}: CustomerManagementDashboardProps) {
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedOrganizations, setSelectedOrganizations] = useState<string[]>([]);
    const [showFilters, setShowFilters] = useState(false);

    const { data, setData, get } = useForm(filters);

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'active': return 'bg-green-100 text-green-800';
            case 'pending': return 'bg-yellow-100 text-yellow-800';
            case 'inactive': return 'bg-gray-100 text-gray-800';
            case 'suspended': return 'bg-red-100 text-red-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    const getOnboardingStatusColor = (status: string) => {
        switch (status) {
            case 'completed': return 'bg-green-100 text-green-800';
            case 'in_progress': return 'bg-blue-100 text-blue-800';
            case 'needs_attention': return 'bg-red-100 text-red-800';
            case 'not_started': return 'bg-gray-100 text-gray-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    const getComplianceStatusColor = (status: string) => {
        switch (status) {
            case 'compliant': return 'bg-green-100 text-green-800';
            case 'pending_documents': return 'bg-yellow-100 text-yellow-800';
            case 'expired': return 'bg-orange-100 text-orange-800';
            case 'non_compliant': return 'bg-red-100 text-red-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    const filteredOrganizations = organizations.filter(org => {
        const matchesSearch = org.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                            org.primary_contact.email.toLowerCase().includes(searchTerm.toLowerCase());

        const matchesStatus = !data.status || org.status === data.status;
        const matchesType = !data.type || org.type === data.type;
        const matchesOnboarding = !data.onboarding_status || org.onboarding_status === data.onboarding_status;

        return matchesSearch && matchesStatus && matchesType && matchesOnboarding;
    });

    const handleBulkAction = (action: string) => {
        if (selectedOrganizations.length === 0) return;

        // Handle bulk actions here
        console.log(`Performing ${action} on organizations:`, selectedOrganizations);
    };

    const handleFilter = () => {
        get('/admin/organizations', {
            preserveState: true,
            replace: true
        });
    };

    return (
        <MainLayout>
            <Head title="Customer Management" />

            <div className="py-8">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="flex justify-between items-center mb-8">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">Customer Management</h1>
                            <p className="text-gray-600 mt-1">Manage organizations and track onboarding progress</p>
                        </div>
                        <div className="flex gap-3">
                            <Button variant="secondary">
                                <Download className="h-4 w-4 mr-2" />
                                Export
                            </Button>
                            <Link href="/admin/organizations/create">
                                <Button>
                                    <Plus className="h-4 w-4 mr-2" />
                                    Add Organization
                                </Button>
                            </Link>
                        </div>
                    </div>

                    {/* Stats Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <Card>
                            <CardContent className="p-6">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-gray-600">Total Organizations</p>
                                        <p className="text-3xl font-bold text-gray-900">{stats.total_organizations}</p>
                                    </div>
                                    <Building2 className="h-8 w-8 text-blue-600" />
                                </div>
                                <div className="mt-4 flex items-center text-sm">
                                    <span className="text-green-600 font-medium">
                                        {stats.active_organizations} active
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="p-6">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-gray-600">Pending Onboarding</p>
                                        <p className="text-3xl font-bold text-gray-900">{stats.pending_onboarding}</p>
                                    </div>
                                    <Clock className="h-8 w-8 text-yellow-600" />
                                </div>
                                <div className="mt-4 flex items-center text-sm">
                                    <span className="text-yellow-600 font-medium">
                                        Need attention
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="p-6">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-gray-600">Monthly Revenue</p>
                                        <p className="text-3xl font-bold text-gray-900">
                                            ${stats.total_revenue.toLocaleString()}
                                        </p>
                                    </div>
                                    <TrendingUp className="h-8 w-8 text-green-600" />
                                </div>
                                <div className="mt-4 flex items-center text-sm">
                                    <span className="text-green-600 font-medium">
                                        +{stats.growth_rate}% from last month
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="p-6">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-gray-600">Compliance Alerts</p>
                                        <p className="text-3xl font-bold text-gray-900">{stats.compliance_alerts}</p>
                                    </div>
                                    <AlertCircle className="h-8 w-8 text-red-600" />
                                </div>
                                <div className="mt-4 flex items-center text-sm">
                                    <span className="text-red-600 font-medium">
                                        Require immediate attention
                                    </span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Filters and Search */}
                    <Card className="mb-6">
                        <CardContent className="p-6">
                            <div className="flex flex-col md:flex-row gap-4">
                                <div className="flex-1">
                                    <div className="relative">
                                        <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                                        <input
                                            type="text"
                                            placeholder="Search organizations..."
                                            value={searchTerm}
                                            onChange={(e) => setSearchTerm(e.target.value)}
                                            className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        />
                                    </div>
                                </div>

                                <Button
                                    variant="secondary"
                                    onClick={() => setShowFilters(!showFilters)}
                                >
                                    <Filter className="h-4 w-4 mr-2" />
                                    Filters
                                </Button>
                            </div>

                            {showFilters && (
                                <div className="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4 pt-4 border-t">
                                    <select
                                        value={data.status}
                                        onChange={(e) => setData('status', e.target.value)}
                                        className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    >
                                        <option value="">All Statuses</option>
                                        <option value="active">Active</option>
                                        <option value="pending">Pending</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="suspended">Suspended</option>
                                    </select>

                                    <select
                                        value={data.type}
                                        onChange={(e) => setData('type', e.target.value)}
                                        className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    >
                                        <option value="">All Types</option>
                                        <option value="hospital">Hospital</option>
                                        <option value="clinic_group">Clinic Group</option>
                                        <option value="wound_center">Wound Center</option>
                                        <option value="physician_practice">Physician Practice</option>
                                    </select>

                                    <select
                                        value={data.onboarding_status}
                                        onChange={(e) => setData('onboarding_status', e.target.value)}
                                        className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    >
                                        <option value="">All Onboarding</option>
                                        <option value="completed">Completed</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="needs_attention">Needs Attention</option>
                                        <option value="not_started">Not Started</option>
                                    </select>

                                    <Button onClick={handleFilter}>
                                        Apply Filters
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Bulk Actions */}
                    {selectedOrganizations.length > 0 && (
                        <Card className="mb-6">
                            <CardContent className="p-4">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-gray-600">
                                        {selectedOrganizations.length} organization(s) selected
                                    </span>
                                    <div className="flex gap-2">
                                        <Button
                                            variant="secondary"
                                            onClick={() => handleBulkAction('send_reminders')}
                                        >
                                            <Mail className="h-4 w-4 mr-1" />
                                            Send Reminders
                                        </Button>
                                        <Button
                                            variant="secondary"
                                            onClick={() => handleBulkAction('export')}
                                        >
                                            <Download className="h-4 w-4 mr-1" />
                                            Export
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Organizations Table */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Organizations ({filteredOrganizations.length})</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="text-left py-3 px-4">
                                                <input
                                                    type="checkbox"
                                                    onChange={(e) => {
                                                        if (e.target.checked) {
                                                            setSelectedOrganizations(filteredOrganizations.map(org => org.id));
                                                        } else {
                                                            setSelectedOrganizations([]);
                                                        }
                                                    }}
                                                />
                                            </th>
                                            <th className="text-left py-3 px-4">Organization</th>
                                            <th className="text-left py-3 px-4">Status</th>
                                            <th className="text-left py-3 px-4">Onboarding Progress</th>
                                            <th className="text-left py-3 px-4">Compliance</th>
                                            <th className="text-left py-3 px-4">Users</th>
                                            <th className="text-left py-3 px-4">Revenue</th>
                                            <th className="text-left py-3 px-4">Sales Rep</th>
                                            <th className="text-left py-3 px-4">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {filteredOrganizations.map((org) => (
                                            <tr key={org.id} className="border-b hover:bg-gray-50">
                                                <td className="py-3 px-4">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedOrganizations.includes(org.id)}
                                                        onChange={(e) => {
                                                            if (e.target.checked) {
                                                                setSelectedOrganizations([...selectedOrganizations, org.id]);
                                                            } else {
                                                                setSelectedOrganizations(selectedOrganizations.filter(id => id !== org.id));
                                                            }
                                                        }}
                                                    />
                                                </td>
                                                <td className="py-3 px-4">
                                                    <div>
                                                        <div className="font-medium text-gray-900">{org.name}</div>
                                                        <div className="text-sm text-gray-500">{org.type}</div>
                                                        <div className="text-sm text-gray-500">{org.primary_contact.email}</div>
                                                    </div>
                                                </td>
                                                <td className="py-3 px-4">
                                                    <Badge className={getStatusColor(org.status)}>
                                                        {org.status}
                                                    </Badge>
                                                </td>
                                                <td className="py-3 px-4">
                                                    <div className="space-y-1">
                                                        <div className="flex items-center justify-between">
                                                            <Badge className={getOnboardingStatusColor(org.onboarding_status)}>
                                                                {org.onboarding_status.replace('_', ' ')}
                                                            </Badge>
                                                            <span className="text-sm text-gray-500">
                                                                {org.onboarding_progress}%
                                                            </span>
                                                        </div>
                                                        <Progress value={org.onboarding_progress} className="h-1" />
                                                    </div>
                                                </td>
                                                <td className="py-3 px-4">
                                                    <Badge className={getComplianceStatusColor(org.compliance_status)}>
                                                        {org.compliance_status.replace('_', ' ')}
                                                    </Badge>
                                                </td>
                                                <td className="py-3 px-4">
                                                    <div className="flex items-center">
                                                        <Users className="h-4 w-4 text-gray-400 mr-1" />
                                                        {org.total_users}
                                                    </div>
                                                </td>
                                                <td className="py-3 px-4">
                                                    <div className="text-sm">
                                                        <div className="font-medium">${org.monthly_revenue.toLocaleString()}</div>
                                                        <div className="text-gray-500">{org.total_orders} orders</div>
                                                    </div>
                                                </td>
                                                <td className="py-3 px-4">
                                                    <div className="text-sm">
                                                        <div className="font-medium">{org.sales_rep.name}</div>
                                                        <div className="text-gray-500">{org.sales_rep.email}</div>
                                                    </div>
                                                </td>
                                                <td className="py-3 px-4">
                                                    <div className="flex items-center gap-2">
                                                        <Link href={`/admin/organizations/${org.id}`}>
                                                            <Button variant="ghost" size="sm">
                                                                <Eye className="h-4 w-4" />
                                                            </Button>
                                                        </Link>
                                                        <Link href={`/admin/organizations/${org.id}/edit`}>
                                                            <Button variant="ghost" size="sm">
                                                                <Edit className="h-4 w-4" />
                                                            </Button>
                                                        </Link>
                                                        <Button variant="secondary" className="h-8 w-8">
                                                            <MoreHorizontal className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {filteredOrganizations.length === 0 && (
                                <div className="text-center py-12">
                                    <Building2 className="mx-auto h-12 w-12 text-gray-400 mb-4" />
                                    <h3 className="text-lg font-medium text-gray-900 mb-2">No organizations found</h3>
                                    <p className="text-gray-600 mb-4">
                                        {searchTerm || Object.values(data).some(v => v)
                                            ? "Try adjusting your search or filters"
                                            : "Get started by adding your first organization"}
                                    </p>
                                    {!searchTerm && !Object.values(data).some(v => v) && (
                                        <Link href="/admin/organizations/create">
                                            <Button>
                                                <Plus className="h-4 w-4 mr-2" />
                                                Add Organization
                                            </Button>
                                        </Link>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </MainLayout>
    );
}
