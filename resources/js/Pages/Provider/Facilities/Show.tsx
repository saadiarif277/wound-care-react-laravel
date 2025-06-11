import React from 'react';
import { router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { ArrowLeft, MapPin, Users, Building, Mail, Phone } from 'lucide-react';

interface Provider {
  id: number;
  name: string;
  email: string;
  role: string;
}

interface Facility {
  id: number;
  name: string;
  address?: string;
  organization: {
    id: number;
    name: string;
  };
  providers: Provider[];
  created_at: string;
  updated_at: string;
}

interface Props {
  facility: Facility;
}

const ProviderFacilityShow: React.FC<Props> = ({ facility }) => {
  return (
    <MainLayout title={facility.name}>
      <div className="py-6">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Back button */}
          <div className="mb-6">
            <button
              onClick={() => router.visit('/facilities')}
              className="inline-flex items-center text-sm text-gray-600 hover:text-gray-900"
            >
              <ArrowLeft className="h-4 w-4 mr-2" />
              Back to Facilities
            </button>
          </div>

          {/* Facility Details */}
          <div className="bg-white shadow rounded-lg">
            <div className="px-6 py-5 border-b border-gray-200">
              <div className="flex items-center">
                <Building className="h-6 w-6 text-gray-400 mr-3" />
                <h1 className="text-2xl font-semibold text-gray-900">{facility.name}</h1>
              </div>
            </div>

            <div className="px-6 py-5">
              <dl className="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                {/* Organization */}
                <div>
                  <dt className="text-sm font-medium text-gray-500">Organization</dt>
                  <dd className="mt-1 text-sm text-gray-900">{facility.organization.name}</dd>
                </div>

                {/* Address */}
                {facility.address && (
                  <div>
                    <dt className="text-sm font-medium text-gray-500 flex items-center">
                      <MapPin className="h-4 w-4 mr-1" />
                      Address
                    </dt>
                    <dd className="mt-1 text-sm text-gray-900">{facility.address}</dd>
                  </div>
                )}

                {/* Provider Count */}
                <div>
                  <dt className="text-sm font-medium text-gray-500 flex items-center">
                    <Users className="h-4 w-4 mr-1" />
                    Total Providers
                  </dt>
                  <dd className="mt-1 text-sm text-gray-900">{facility.providers.length}</dd>
                </div>

                {/* Created Date */}
                <div>
                  <dt className="text-sm font-medium text-gray-500">Member Since</dt>
                  <dd className="mt-1 text-sm text-gray-900">
                    {new Date(facility.created_at).toLocaleDateString()}
                  </dd>
                </div>
              </dl>
            </div>
          </div>

          {/* Providers Section */}
          <div className="mt-6 bg-white shadow rounded-lg">
            <div className="px-6 py-5 border-b border-gray-200">
              <h2 className="text-lg font-medium text-gray-900">Providers at this Facility</h2>
            </div>

            <div className="px-6 py-5">
              {facility.providers.length > 0 ? (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                  {facility.providers.map((provider) => (
                    <div
                      key={provider.id}
                      className="p-4 border border-gray-200 rounded-lg hover:shadow-md transition-shadow"
                    >
                      <div className="flex items-center">
                        <div className="flex-shrink-0">
                          <div className="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                            <Users className="h-5 w-5 text-blue-600" />
                          </div>
                        </div>
                        <div className="ml-3">
                          <p className="text-sm font-medium text-gray-900">{provider.name}</p>
                          <div className="flex items-center mt-1 text-xs text-gray-500">
                            <Mail className="h-3 w-3 mr-1" />
                            {provider.email}
                          </div>
                          <p className="mt-1 text-xs text-gray-500">Role: {provider.role}</p>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-8">
                  <Users className="mx-auto h-12 w-12 text-gray-400" />
                  <p className="mt-2 text-sm text-gray-500">No other providers at this facility</p>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
};

export default ProviderFacilityShow; 