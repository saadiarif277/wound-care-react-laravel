import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import FilterBar from '@/Components/FilterBar/FilterBar';
import Pagination from '@/Components/Pagination/Pagination';

interface ProductRequest {
  id: number;
  request_number: string;
  patient_display: string;
  patient_fhir_id: string;
  order_status: string;
  step: number;
  step_description: string;
  facility_name: string;
  created_at: string;
  total_products: number;
  total_amount: number;
}

interface Props {
  requests: {
    data: ProductRequest[];
    links: any[];
  };
  filters: {
    search?: string;
    status?: string;
  };
}

const ProductRequestIndex: React.FC<Props> = ({ requests, filters }) => {
  const { auth } = usePage<any>().props;

  const getStatusColor = (status: string): string => {
    const colors = {
      draft: 'bg-gray-100 text-gray-800',
      submitted: 'bg-blue-100 text-blue-800',
      processing: 'bg-yellow-100 text-yellow-800',
      approved: 'bg-green-100 text-green-800',
      rejected: 'bg-red-100 text-red-800',
      shipped: 'bg-purple-100 text-purple-800',
      delivered: 'bg-green-100 text-green-800',
      cancelled: 'bg-red-100 text-red-800',
    };
    return colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800';
  };

  const getStepColor = (step: number): string => {
    if (step === 6) return 'text-green-600'; // Completed
    if (step >= 4) return 'text-blue-600'; // In progress
    return 'text-gray-600'; // Early stages
  };

  const formatStatus = (status: string): string => {
    return status.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
  };

  return (
    <MainLayout title="Product Requests">
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">Product Requests</h1>
          <p className="mt-1 text-sm text-gray-600">
            Manage your MSC-MVP product requests with intelligent workflow and sequential patient IDs
          </p>
        </div>
        <Link
          href="/product-requests/create"
          className="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150"
        >
          + New Product Request
        </Link>
      </div>

      <div className="bg-white rounded-lg shadow overflow-hidden">
        <div className="p-6 border-b border-gray-200">
          <FilterBar />
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Request
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Patient ID
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Facility
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status / Step
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Products
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Total
                </th>
                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Created
                </th>
                <th scope="col" className="relative px-6 py-3">
                  <span className="sr-only">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {requests.data.length === 0 ? (
                <tr>
                  <td colSpan={8} className="px-6 py-12 text-center">
                    <div className="text-gray-500">
                      <p className="text-lg font-medium">No product requests found</p>
                      <p className="mt-1">Get started by creating your first product request with the MSC-MVP workflow.</p>
                      <Link
                        href="/product-requests/create"
                        className="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700"
                      >
                        + New Product Request
                      </Link>
                    </div>
                  </td>
                </tr>
              ) : (
                requests.data.map((request) => (
                  <tr key={request.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div>
                        <div className="text-sm font-medium text-gray-900">
                          {request.request_number}
                        </div>
                        <div className="text-sm text-gray-500">
                          MSC-MVP Request
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div>
                        <div className="text-sm font-medium text-gray-900">
                          {request.patient_display}
                        </div>
                        <div className="text-xs text-gray-500">
                          Sequential Display ID
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm text-gray-900">
                        {request.facility_name}
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="space-y-1">
                        <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusColor(request.order_status)}`}>
                          {formatStatus(request.order_status)}
                        </span>
                        <div className={`text-xs ${getStepColor(request.step)}`}>
                          Step {request.step}/6: {request.step_description}
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {request.total_products} product{request.total_products !== 1 ? 's' : ''}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      ${request.total_amount.toFixed(2)}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {request.created_at}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                      <Link
                        href={`/product-requests/${request.id}`}
                        className="text-blue-600 hover:text-blue-900 inline-flex items-center"
                      >
                        👁 View
                      </Link>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {requests.data.length > 0 && (
          <div className="px-6 py-4 border-t border-gray-200">
            <Pagination links={requests.links} />
          </div>
        )}
      </div>

      {/* Sequential ID Benefits Info */}
      <div className="mt-6 bg-blue-50 border-l-4 border-blue-400 p-4">
        <div className="flex">
          <div className="ml-3">
            <h3 className="text-sm font-medium text-blue-800">Sequential Display ID Benefits</h3>
            <div className="mt-2 text-sm text-blue-700">
              <ul className="list-disc list-inside space-y-1">
                <li><strong>Better Privacy:</strong> No age information that could enable patient identification</li>
                <li><strong>Faster Performance:</strong> Order lists load without Azure HDS API calls</li>
                <li><strong>Easy Search:</strong> Find patients by initials ("JoSm") or full ID ("JoSm001")</li>
                <li><strong>Clear Differentiation:</strong> Sequential numbers prevent confusion between patients</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
};

export default ProductRequestIndex;

