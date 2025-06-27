import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Plus, Search, Edit, Trash2, MapPin } from 'lucide-react';
import { usePage } from '@inertiajs/react';

interface Facility {
  id: number;
  name: string;
  address?: string;
  organization_id: number;
  organization_name: string;
  created_at: string;
  updated_at: string;
}

interface Props {
  facilities: Facility[];
  organizations: Array<{
    id: number;
    name: string;
  }>;
}

const FacilitiesIndex: React.FC<Props> = ({ facilities, organizations }) => {
  const { props } = usePage<any>();
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedOrganization, setSelectedOrganization] = useState<number | ''>('');

  const filteredFacilities = facilities.filter(facility => {
    const matchesSearch = facility.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                         facility.address?.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesOrganization = !selectedOrganization || facility.organization_id === selectedOrganization;
    return matchesSearch && matchesOrganization;
  });

  const handleDelete = async (id: number) => {
    if (window.confirm('Are you sure you want to delete this facility?')) {
      router.delete(`/admin/facilities/${id}`, {
        onSuccess: () => {
          // The page will refresh automatically due to Inertia
        },
        onError (errors) {
          console.error('Error deleting facility:', errors);
          alert('Failed to delete facility. Please try again.');
        }
      });
    }
  };

  return (
    <MainLayout title="Facility Management">
      <div className="py-6">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between items-center mb-6">
            <h1 className="text-2xl font-semibold text-gray-900">Facility Management</h1>
            <button
              onClick={() => router.visit('/admin/facilities/create')}
              className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              <Plus className="h-5 w-5 mr-2" />
              Add Facility
            </button>
          </div>

          {/* Filters */}
          <div className="bg-white p-4 rounded-lg shadow mb-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <Search className="h-5 w-5 text-gray-400" />
                </div>
                <input
                  type="text"
                  className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                  placeholder="Search facilities..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                />
              </div>
              <select
                className="block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md"
                value={selectedOrganization}
                onChange={(e) => setSelectedOrganization(e.target.value ? Number(e.target.value) : '')}
              >
                <option value="">All Organizations</option>
                {organizations.map(org => (
                  <option key={org.id} value={org.id}>{org.name}</option>
                ))}
              </select>
            </div>
          </div>

          {/* Facilities Table */}
          <div className="bg-white shadow rounded-lg overflow-hidden">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Facility Name
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Organization
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Address
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Last Updated
                  </th>
                  <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {filteredFacilities.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="px-6 py-4 text-center text-sm text-gray-500">
                      No facilities found.
                    </td>
                  </tr>
                 ) : (filteredFacilities.map((facility) => (
                   <tr key={facility.id}>
                     <td className="px-6 py-4 whitespace-nowrap">
                       <div className="flex items-center">
                         <MapPin className="h-5 w-5 text-gray-400 mr-2" />
                         <div className="text-sm font-medium text-gray-900">{facility.name}</div>
                       </div>
                     </td>
                     <td className="px-6 py-4 whitespace-nowrap">
                       <div className="text-sm text-gray-900">{facility.organization_name}</div>
                     </td>
                     <td className="px-6 py-4 whitespace-nowrap">
                       <div className="text-sm text-gray-500">{facility.address || 'No address provided'}</div>
                     </td>
                     <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                       {new Date(facility.updated_at).toLocaleDateString()}
                     </td>
                     <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                       <button
                         onClick={() => router.visit(`/admin/facilities/${facility.id}/edit`)}
                         className="text-blue-600 hover:text-blue-900 mr-4"
                       >
                         <Edit className="h-5 w-5" />
                       </button>
                       <button
                         onClick={() => handleDelete(facility.id)}
                         className="text-red-600 hover:text-red-900"
                       >
                         <Trash2 className="h-5 w-5" />
                       </button>
                     </td>
                   </tr>
                 )))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </MainLayout>
  );
};

export default FacilitiesIndex;
