import React, { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Badge } from '@/Components/ui/badge';

interface Order {
    id: string;
    order_number: string;
    provider_name: string;
    patient_identifier: string;
    status: string;
    simplified_status: string;
    status_color: string;
    request_date: string;
    manufacturer: string;
    action_required: boolean;
    total_value: number;
}

interface Props {
    orders: {
        data: Order[];
        links: any;
        meta: any;
    };
    activeFilter: string;
}

export default function Dashboard({ orders, activeFilter }: Props) {
    const [filter, setFilter] = useState(activeFilter);

    const handleFilterChange = (newFilter: string) => {
        setFilter(newFilter);
        router.get('/admin/orders', { filter: newFilter }, { preserveState: true });
    };

    const getStatusBadgeColor = (color: string) => {
        const colorMap: Record<string, string> = {
            gray: 'bg-gray-100 text-gray-800',
            blue: 'bg-blue-100 text-blue-800',
            purple: 'bg-purple-100 text-purple-800',
            green: 'bg-green-100 text-green-800',
            orange: 'bg-orange-100 text-orange-800',
            red: 'bg-red-100 text-red-800',
        };
        return colorMap[color] || 'bg-gray-100 text-gray-800';
    };

    return (
        <MainLayout title="Order Center">
            <div className="py-6">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="mb-6">
                        <h1 className="text-2xl font-semibold text-gray-900">Order Management Center</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Manage provider-submitted product requests and track order lifecycles
                        </p>
                    </div>

                    {/* Filter Tabs */}
                    <div className="border-b border-gray-200 mb-6">
                        <nav className="-mb-px flex space-x-8" aria-label="Tabs">
                            <button
                                onClick={() => handleFilterChange('action_required')}
                                className={`${
                                    filter === 'action_required'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                } whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm`}
                            >
                                Orders Requiring Action
                                {orders.data.filter(o => o.action_required).length > 0 && (
                                    <span className="ml-2 bg-red-100 text-red-600 py-0.5 px-2 rounded-full text-xs">
                                        {orders.data.filter(o => o.action_required).length}
                                    </span>
                                )}
                            </button>
                            <button
                                onClick={() => handleFilterChange('all')}
                                className={`${
                                    filter === 'all'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                } whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm`}
                            >
                                All Orders
                            </button>
                        </nav>
                    </div>

                    {/* Orders Table */}
                    <div className="bg-white shadow overflow-hidden sm:rounded-lg">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Order ID
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Provider
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Patient
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Request Date
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Manufacturer
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Action Required
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {orders.data.map((order) => (
                                    <tr
                                        key={order.id}
                                        className="hover:bg-gray-50 cursor-pointer"
                                        onClick={() => router.visit(`/admin/orders/${order.id}`)}
                                    >
                                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {order.order_number}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {order.provider_name}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {order.patient_identifier}
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="max-w-[120px]">
                                                <Badge className={getStatusBadgeColor(order.status_color)}>
                                                    {order.simplified_status}
                                                </Badge>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {order.request_date}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {order.manufacturer}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {order.action_required && (
                                                <span className="text-red-600 font-medium flex items-center">
                                                    <span className="w-2 h-2 bg-red-600 rounded-full mr-2"></span>
                                                    Yes
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        {orders.data.length === 0 && (
                            <div className="text-center py-12">
                                <p className="text-gray-500">No orders found</p>
                            </div>
                        )}
                    </div>

                    {/* Pagination */}
                    {orders.links && orders.links.length > 0 && (
                        <div className="mt-6">
                            {orders.meta && (
                                <div className="mb-4 text-sm text-gray-700">
                                    Showing {orders.meta.from || 0} to {orders.meta.to || 0} of {orders.meta.total || 0} results
                                </div>
                            )}
                            <div className="flex space-x-2 justify-center">
                                {orders.links.map((link: any, index: number) => (
                                    <Link
                                        key={index}
                                        href={link.url || '#'}
                                        className={`px-3 py-2 text-sm ${
                                            link.active
                                                ? 'bg-indigo-600 text-white'
                                                : 'bg-white text-gray-700 hover:bg-gray-50'
                                        } border rounded`}
                                        preserveState
                                        preserveScroll
                                    >
                                        <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                    </Link>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </MainLayout>
    );
}
