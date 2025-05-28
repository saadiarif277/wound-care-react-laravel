import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Card } from '@/Components/Card';
import { PageHeader } from '@/Components/PageHeader';
import { format } from 'date-fns';
import { PageProps } from '@/types';
import type { MainLayoutProps } from '@/types/layout';
import type { ButtonProps } from '@/types/button';

interface CommissionRecord {
    id: number;
    order_id: number;
    order_item_id: number;
    rep_id: number;
    rep: {
        id: number;
        name: string;
    };
    parent_rep_id: number | null;
    parent_rep: {
        id: number;
        name: string;
    } | null;
    amount: number;
    percentage_rate: number;
    type: 'direct-rep' | 'sub-rep-share' | 'parent-rep-share';
    status: 'pending' | 'approved' | 'included_in_payout' | 'paid';
    calculation_date: string;
    approved_by: number | null;
    approved_at: string | null;
    payout_id: number | null;
    notes: string | null;
}

interface Summary {
    total_commission: number;
    pending_commission: number;
    approved_commission: number;
    paid_commission: number;
}

interface Props extends PageProps {
    records: {
        data: CommissionRecord[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    summary: Summary;
}

interface Filters {
    status: string;
    rep_id: string;
    start_date: string;
    end_date: string;
}

export default function Index({ records, summary }: Props) {
    const [filters, setFilters] = useState<Filters>({
        status: '',
        rep_id: '',
        start_date: '',
        end_date: '',
    });

    const { data, setData, post, processing, errors } = useForm({
        notes: '',
    });

    const handleFilterChange = (key: keyof Filters, value: string) => {
        setFilters(prev => ({ ...prev, [key]: value }));
        // In a real implementation, you would trigger a new request here
    };

    const handleApprove = (record: CommissionRecord) => {
        if (confirm('Are you sure you want to approve this commission record?')) {
            post(route('commission-records.approve', record.id), {
                onSuccess: () => {
                    window.location.reload();
                },
            });
        }
    };

    const navItems = [
        {
            name: 'Commission Rules',
            href: route('commission-rules.index'),
            current: false,
        },
        {
            name: 'Commission Records',
            href: route('commission-records.index'),
            current: true,
        },
        {
            name: 'Commission Payouts',
            href: route('commission-payouts.index'),
            current: false,
        },
    ];

    return (
        <MainLayout title="Commission Records">
            <Head title="Commission Records" />

            <PageHeader
                title="Commission Records"
                description="View and manage commission records for all sales representatives"
                navItems={navItems}
            />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Summary Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <Card
                            title="Total Commission"
                            icon={
                                <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            }
                        >
                            <p className="mt-2 text-3xl font-semibold text-gray-900">
                                ${summary.total_commission.toFixed(2)}
                            </p>
                        </Card>
                        <Card
                            title="Pending Commission"
                            icon={
                                <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            }
                        >
                            <p className="mt-2 text-3xl font-semibold text-yellow-600">
                                ${summary.pending_commission.toFixed(2)}
                            </p>
                        </Card>
                        <Card
                            title="Approved Commission"
                            icon={
                                <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            }
                        >
                            <p className="mt-2 text-3xl font-semibold text-blue-600">
                                ${summary.approved_commission.toFixed(2)}
                            </p>
                        </Card>
                        <Card
                            title="Paid Commission"
                            icon={
                                <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            }
                        >
                            <p className="mt-2 text-3xl font-semibold text-green-600">
                                ${summary.paid_commission.toFixed(2)}
                            </p>
                        </Card>
                    </div>

                    {/* Filters */}
                    <Card className="mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <Select
                                label="Status"
                                value={filters.status}
                                onChange={e => handleFilterChange('status', e.target.value)}
                            >
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="included_in_payout">Included in Payout</option>
                                <option value="paid">Paid</option>
                            </Select>

                            <Input
                                type="text"
                                label="Rep ID"
                                value={filters.rep_id}
                                onChange={e => handleFilterChange('rep_id', e.target.value)}
                            />

                            <Input
                                type="date"
                                label="Start Date"
                                value={filters.start_date}
                                onChange={e => handleFilterChange('start_date', e.target.value)}
                            />

                            <Input
                                type="date"
                                label="End Date"
                                value={filters.end_date}
                                onChange={e => handleFilterChange('end_date', e.target.value)}
                            />
                        </div>
                    </Card>

                    {/* Records Table */}
                    <Card>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Order
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Rep
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Type
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Amount
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Date
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {records.data.map((record) => (
                                        <tr key={record.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                #{record.order_id}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="text-sm font-medium text-gray-900">{record.rep.name}</div>
                                                {record.parent_rep && (
                                                    <div className="text-sm text-gray-500">
                                                        Parent: {record.parent_rep.name}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                    {record.type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                ${record.amount.toFixed(2)}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                                    record.status === 'paid' ? 'bg-green-100 text-green-800' :
                                                    record.status === 'approved' ? 'bg-blue-100 text-blue-800' :
                                                    record.status === 'included_in_payout' ? 'bg-purple-100 text-purple-800' :
                                                    'bg-yellow-100 text-yellow-800'
                                                }`}>
                                                    {record.status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {format(new Date(record.calculation_date), 'MMM d, yyyy')}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                {record.status === 'pending' && (
                                                    <Button
                                                        variant="primary"
                                                        onClick={() => handleApprove(record)}
                                                        className="text-sm"
                                                    >
                                                        Approve
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                </div>
            </div>
        </MainLayout>
    );
}
