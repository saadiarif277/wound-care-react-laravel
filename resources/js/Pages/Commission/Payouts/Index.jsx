import { useState } from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/Button';
import { Modal } from '@/Components/Modal';
import { Input } from '@/Components/Input';
import { useForm } from '@inertiajs/react';
import { format } from 'date-fns';

export default function Index({ auth, payouts }) {
    const [showGenerateModal, setShowGenerateModal] = useState(false);
    const [showProcessModal, setShowProcessModal] = useState(false);
    const [selectedPayout, setSelectedPayout] = useState(null);

    const generateForm = useForm({
        start_date: '',
        end_date: '',
    });

    const processForm = useForm({
        payment_reference: '',
    });

    const handleGenerateSubmit = (e) => {
        e.preventDefault();
        generateForm.post(route('commission-payouts.generate'), {
            onSuccess: () => {
                setShowGenerateModal(false);
                generateForm.reset();
            },
        });
    };

    const handleProcessSubmit = (e) => {
        e.preventDefault();
        processForm.post(route('commission-payouts.process', selectedPayout.id), {
            onSuccess: () => {
                setShowProcessModal(false);
                processForm.reset();
                setSelectedPayout(null);
            },
        });
    };

    const handleApprove = (payout) => {
        if (confirm('Are you sure you want to approve this payout?')) {
            axios.post(route('commission-payouts.approve', payout.id))
                .then(() => {
                    window.location.reload();
                });
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Commission Payouts</h2>}
        >
            <Head title="Commission Payouts" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 bg-white border-b border-gray-200">
                            <div className="flex justify-between mb-6">
                                <h3 className="text-lg font-medium">Commission Payouts</h3>
                                <Button onClick={() => setShowGenerateModal(true)}>
                                    Generate Payouts
                                </Button>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Rep
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Period
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Amount
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
                                        {payouts.data.map((payout) => (
                                            <tr key={payout.id}>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {payout.rep.name}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {format(new Date(payout.period_start), 'MMM d, yyyy')} - {format(new Date(payout.period_end), 'MMM d, yyyy')}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    ${payout.total_amount.toFixed(2)}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                                        payout.status === 'processed' ? 'bg-green-100 text-green-800' :
                                                        payout.status === 'approved' ? 'bg-blue-100 text-blue-800' :
                                                        'bg-yellow-100 text-yellow-800'
                                                    }`}>
                                                        {payout.status.charAt(0).toUpperCase() + payout.status.slice(1)}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    {payout.status === 'calculated' && (
                                                        <button
                                                            onClick={() => handleApprove(payout)}
                                                            className="text-blue-600 hover:text-blue-900 mr-4"
                                                        >
                                                            Approve
                                                        </button>
                                                    )}
                                                    {payout.status === 'approved' && (
                                                        <button
                                                            onClick={() => {
                                                                setSelectedPayout(payout);
                                                                setShowProcessModal(true);
                                                            }}
                                                            className="text-green-600 hover:text-green-900"
                                                        >
                                                            Process
                                                        </button>
                                                    )}
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

            <Modal show={showGenerateModal} onClose={() => {
                setShowGenerateModal(false);
                generateForm.reset();
            }}>
                <form onSubmit={handleGenerateSubmit} className="p-6">
                    <h2 className="text-lg font-medium mb-4">Generate Payouts</h2>

                    <div className="mb-4">
                        <Input
                            type="date"
                            label="Start Date"
                            value={generateForm.data.start_date}
                            onChange={e => generateForm.setData('start_date', e.target.value)}
                            error={generateForm.errors.start_date}
                        />
                    </div>

                    <div className="mb-4">
                        <Input
                            type="date"
                            label="End Date"
                            value={generateForm.data.end_date}
                            onChange={e => generateForm.setData('end_date', e.target.value)}
                            error={generateForm.errors.end_date}
                        />
                    </div>

                    <div className="flex justify-end">
                        <Button
                            type="button"
                            className="mr-2"
                            onClick={() => {
                                setShowGenerateModal(false);
                                generateForm.reset();
                            }}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={generateForm.processing}>
                            Generate
                        </Button>
                    </div>
                </form>
            </Modal>

            <Modal show={showProcessModal} onClose={() => {
                setShowProcessModal(false);
                processForm.reset();
                setSelectedPayout(null);
            }}>
                <form onSubmit={handleProcessSubmit} className="p-6">
                    <h2 className="text-lg font-medium mb-4">Process Payout</h2>

                    <div className="mb-4">
                        <Input
                            type="text"
                            label="Payment Reference"
                            value={processForm.data.payment_reference}
                            onChange={e => processForm.setData('payment_reference', e.target.value)}
                            error={processForm.errors.payment_reference}
                        />
                    </div>

                    <div className="flex justify-end">
                        <Button
                            type="button"
                            className="mr-2"
                            onClick={() => {
                                setShowProcessModal(false);
                                processForm.reset();
                                setSelectedPayout(null);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processForm.processing}>
                            Process
                        </Button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
