import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Search, MapPin, Users } from 'lucide-react';
import { usePage } from '@inertiajs/react';

interface Facility {
  id: number;
  name: string;
  address?: string;
  organization_name: string;
  provider_count: number;
  created_at: string;
  updated_at: string;
}

interface Props {
  facilities: Facility[];
}

const ProviderFacilitiesIndex: React.FC<Props> = ({ facilities }) => {
  const { props } = usePage<any>();
  const [searchTerm, setSearchTerm] = useState('');

  const filteredFacilities = facilities.filter(facility =>
    facility.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    facility.address?.toLowerCase().includes(searchTerm.toLowerCase()) ||
    facility.organization_name.toLowerCase().includes(searchTerm.toLowerCase())
  );

  return (
    <MainLayout title="My Facilities">
      <div className="py-6">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="mb-6">
            <h1 className="text-2xl font-semibold text-gray-900">My Facilities</h1>
            <p className="mt-1 text-sm text-gray-500">
              View and manage facilities you have access to
            </p>
          </div>

          {/* Search Filter */}
          <div className="bg-white p-4 rounded-lg shadow mb-6">
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
          </div>

          {/* Facilities Grid */}
          {filteredFacilities.length > 0 ? (
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {filteredFacilities.map((facility) => (
                <div
                  key={facility.id}
                  className="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow duration-200"
                >
                  <div className="p-6">
                    <div className="flex items-center">
                      <MapPin className="h-5 w-5 text-gray-400 mr-2" />
                      <h3 className="text-lg font-medium text-gray-900 truncate">
                        {facility.name}
                      </h3>
                    </div>

                    <div className="mt-2">
                      <p className="text-sm text-gray-500">
                        {facility.address || 'No address provided'}
                      </p>
                      <p className="text-sm text-gray-500 mt-1">
                        Organization: {facility.organization_name}
                      </p>
                    </div>

                    <div className="mt-4 flex items-center text-sm text-gray-500">
                      <Users className="h-4 w-4 mr-1" />
                      <span>{facility.provider_count} Providers</span>
                    </div>

                    <div className="mt-4 flex justify-end">
                      <button
                        onClick={() => {
                          console.log('Navigating to facility:', facility.id);
                          console.log('Full URL:', `/facilities/${facility.id}`);
                          router.visit(`/facilities/${facility.id}`);
                        }}
                        className="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                      >
                        View Details
                      </button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="text-center py-12">
              <MapPin className="mx-auto h-12 w-12 text-gray-400" />
              <h3 className="mt-2 text-sm font-medium text-gray-900">No facilities found</h3>
              <p className="mt-1 text-sm text-gray-500">
                {searchTerm ? 'Try adjusting your search terms' : 'You don\'t have access to any facilities yet'}
              </p>
            </div>
          )}
        </div>
      </div>
    </MainLayout>
  );
};

export default ProviderFacilitiesIndex;
