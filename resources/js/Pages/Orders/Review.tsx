import { Head } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import OrderReviewSummary from '@/Pages/QuickRequest/Components/OrderReviewSummary';
import { router } from '@inertiajs/react';
import { PageProps } from '@/types';

interface OrderReviewProps extends PageProps {
    order: {
        id: string;
        status: string;
    };
}

export default function OrderReview({ auth, order }: OrderReviewProps) {
    const handleEdit = (section: string) => {
        // Navigate to the appropriate edit page based on section
        const sectionRouteMap: Record<string, string> = {
            'patient-insurance': `/quick-request/${order.id}/edit?step=patient-insurance`,
            'provider': `/quick-request/${order.id}/edit?step=provider`,
            'clinical-billing': `/quick-request/${order.id}/edit?step=clinical-billing`,
            'product-selection': `/quick-request/${order.id}/edit?step=product-selection`,
            'shipping': `/quick-request/${order.id}/edit?step=shipping`,
            'ivr-form': `/quick-request/${order.id}/edit?step=ivr-form`,
            'order-form': `/quick-request/${order.id}/edit?step=order-form`
        };

        const route = sectionRouteMap[section];
        if (route) {
            router.visit(route);
        }
    };

    const handleSubmit = async (): Promise<void> => {
        router.post(`/api/v1/orders/${order.id}/submit`, {
            confirmation: true
        }, {
            onSuccess: () => {
                // Redirect to order center or success page
                router.visit('/admin/order-center');
            },
            onError: (errors) => {
                // Handle errors if needed
                // Optionally show a message or log
            }
        });
    };

    const isPreSubmission = ['draft', 'ready_for_review'].includes(order.status);

    return (
        <MainLayout title="Order Review">
            <Head title="Order Review" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <OrderReviewSummary
                        orderId={order.id}
                        isPreSubmission={isPreSubmission}
                        onEdit={handleEdit}
                        onSubmit={handleSubmit}
                    />
                </div>
            </div>
        </MainLayout>
    );
}
