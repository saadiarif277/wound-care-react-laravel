import { useState, ChangeEvent, FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Button } from '@/Components/Button';
import { Modal } from '@/Components/Modal';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { format } from 'date-fns';
import { PageProps } from '@/types';

interface CommissionRule {
    id: number;
    target_type: 'product' | 'manufacturer' | 'category';
    target_id: number;
    percentage_rate: number;
    valid_from: string;
    valid_to: string | null;
    is_active: boolean;
    description: string | null;
    created_at: string;
    updated_at: string;
}

interface Props extends PageProps {
    rules: {
        data: CommissionRule[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

interface FormData {
    target_type: string;
    target_id: string;
    percentage_rate: string;
    valid_from: string;
    valid_to: string;
    is_active: boolean;
    description: string;
}

export default function Index({ auth, rules }: Props) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editingRule, setEditingRule] = useState<CommissionRule | null>(null);

    const { data, setData, post, put, processing, errors, reset } = useForm<FormData>({
        target_type: '',
        target_id: '',
        percentage_rate: '',
        valid_from: '',
        valid_to: '',
        is_active: true,
        description: '',
    });

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (editingRule) {
            put(route('commission-rules.update', editingRule.id), {
                onSuccess: () => {
                    setShowCreateModal(false);
                    reset();
                    setEditingRule(null);
                },
            });
        } else {
            post(route('commission-rules.store'), {
                onSuccess: () => {
                    setShowCreateModal(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (rule: CommissionRule) => {
        setEditingRule(rule);
        setData({
            target_type: rule.target_type,
            target_id: rule.target_id.toString(),
            percentage_rate: rule.percentage_rate.toString(),
            valid_from: format(new Date(rule.valid_from), 'yyyy-MM-dd'),
            valid_to: rule.valid_to ? format(new Date(rule.valid_to), 'yyyy-MM-dd') : '',
            is_active: rule.is_active,
            description: rule.description || '',
        });
        setShowCreateModal(true);
    };

    return (
        <MainLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Commission Rules</h2>}
        >
            <Head title="Commission Rules" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 bg-white border-b border-gray-200">
                            <div className="flex justify-between mb-6">
                                <h3 className="text-lg font-medium">Commission Rules</h3>
                                <Button onClick={() => setShowCreateModal(true)}>
                                    Create New Rule
                                </Button>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Target Type
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Target ID
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Rate
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Valid From
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Valid To
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Status
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {rules.data.map((rule) => (
                                            <tr key={rule.id}>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {rule.target_type}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {rule.target_id}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {rule.percentage_rate}%
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {format(new Date(rule.valid_from), 'MMM d, yyyy')}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {rule.valid_to ? format(new Date(rule.valid_to), 'MMM d, yyyy') : 'N/A'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                                        rule.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                                    }`}>
                                                        {rule.is_active ? 'Active' : 'Inactive'}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <button
                                                        onClick={() => handleEdit(rule)}
                                                        className="text-indigo-600 hover:text-indigo-900"
                                                    >
                                                        Edit
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <Modal show={showCreateModal} onClose={() => {
                setShowCreateModal(false);
                reset();
                setEditingRule(null);
            }}>
                <form onSubmit={handleSubmit} className="p-6">
                    <h2 className="text-lg font-medium mb-4">
                        {editingRule ? 'Edit Commission Rule' : 'Create Commission Rule'}
                    </h2>

                    <div className="mb-4">
                        <Select
                            label="Target Type"
                            value={data.target_type}
                            onChange={(e: ChangeEvent<HTMLSelectElement>) => setData('target_type', e.target.value)}
                            error={errors.target_type}
                        >
                            <option value="">Select Type</option>
                            <option value="product">Product</option>
                            <option value="manufacturer">Manufacturer</option>
                            <option value="category">Category</option>
                        </Select>
                    </div>

                    <div className="mb-4">
                        <Input
                            type="number"
                            label="Target ID"
                            value={data.target_id}
                            onChange={(e: ChangeEvent<HTMLInputElement>) => setData('target_id', e.target.value)}
                            error={errors.target_id}
                        />
                    </div>

                    <div className="mb-4">
                        <Input
                            type="number"
                            label="Percentage Rate"
                            value={data.percentage_rate}
                            onChange={(e: ChangeEvent<HTMLInputElement>) => setData('percentage_rate', e.target.value)}
                            error={errors.percentage_rate}
                            step="0.01"
                            min="0"
                            max="100"
                        />
                    </div>

                    <div className="mb-4">
                        <Input
                            type="date"
                            label="Valid From"
                            value={data.valid_from}
                            onChange={(e: ChangeEvent<HTMLInputElement>) => setData('valid_from', e.target.value)}
                            error={errors.valid_from}
                        />
                    </div>

                    <div className="mb-4">
                        <Input
                            type="date"
                            label="Valid To (Optional)"
                            value={data.valid_to}
                            onChange={(e: ChangeEvent<HTMLInputElement>) => setData('valid_to', e.target.value)}
                            error={errors.valid_to}
                        />
                    </div>

                    <div className="mb-4">
                        <label className="flex items-center">
                            <input
                                type="checkbox"
                                checked={data.is_active}
                                onChange={e => setData('is_active', e.target.checked)}
                                className="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                            />
                            <span className="ml-2">Active</span>
                        </label>
                    </div>

                    <div className="mb-4">
                        <Input
                            type="text"
                            label="Description"
                            value={data.description}
                            onChange={(e: ChangeEvent<HTMLInputElement>) => setData('description', e.target.value)}
                            error={errors.description}
                        />
                    </div>

                    <div className="flex justify-end">
                        <Button
                            type="button"
                            className="mr-2"
                            onClick={() => {
                                setShowCreateModal(false);
                                reset();
                                setEditingRule(null);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {editingRule ? 'Update' : 'Create'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </MainLayout>
    );
}
