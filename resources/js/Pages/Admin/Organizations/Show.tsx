import React from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import Button from '@/Components/ui/Button';
import { ArrowLeft, Edit, Building, Mail, Phone, MapPin, User, DollarSign } from 'lucide-react';

interface Organization {
  id: string;
  name: string;
  type: string;
  status: string;
  contact_email: string;
  phone?: string;
  address?: string;
  city?: string;
  state?: string;
  zip_code?: string;
  billing_address?: string;
  billing_city?: string;
  billing_state?: string;
  billing_zip?: string;
  ap_contact_name?: string;
  ap_contact_phone?: string;
  ap_contact_email?: string;
  created_at_formatted: string;
  updated_at_formatted: string;
}

interface Props {
  organization: Organization;
  stats: any; // Simplified for this example
}

const DetailItem = ({ icon, label, value }: { icon: React.ElementType, label: string, value?: string | null }) => (
  <div>
    <dt className="text-sm font-medium text-gray-500 flex items-center">
      {React.createElement(icon, { className: "w-4 h-4 mr-2" })}
      {label}
    </dt>
    <dd className="mt-1 text-sm text-gray-900">{value || 'N/A'}</dd>
  </div>
);

export default function OrganizationShow({ organization, stats }: Props) {
  return (
    <MainLayout>
      <Head title={organization.name} />

      <div className="py-6">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="mb-6 flex justify-between items-center">
            <Link
              href={route('admin.organizations.index')}
              className="inline-flex items-center text-sm text-gray-600 hover:text-gray-900"
            >
              <ArrowLeft className="h-4 w-4 mr-2" />
              Back to Organizations
            </Link>
            <Link href={route('admin.organizations.edit', organization.id)}>
              <Button>
                <Edit className="w-4 h-4 mr-2" />
                Edit Organization
              </Button>
            </Link>
          </div>

          <Card>
            <CardHeader>
              <CardTitle className="text-2xl">{organization.name}</CardTitle>
            </CardHeader>
            <CardContent>
              {/* Primary Details */}
              <div className="mb-8">
                <h3 className="text-lg font-medium text-gray-900 mb-4">Primary Details</h3>
                <dl className="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-6">
                  <DetailItem icon={Building} label="Organization Name" value={organization.name} />
                  <DetailItem icon={Mail} label="Contact Email" value={organization.contact_email} />
                  <DetailItem icon={Phone} label="Phone Number" value={organization.phone} />
                </dl>
              </div>

              {/* Mailing Address */}
              <div className="mb-8">
                <h3 className="text-lg font-medium text-gray-900 mb-4">Mailing Address</h3>
                <dl className="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-6">
                    <DetailItem icon={MapPin} label="Address" value={`${organization.address}, ${organization.city}, ${organization.state} ${organization.zip_code}`} />
                </dl>
              </div>

              {/* Billing Information */}
              <div className="mb-8">
                <h3 className="text-lg font-medium text-gray-900 mb-4">Billing Information</h3>
                <dl className="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-6">
                   <DetailItem icon={MapPin} label="Billing Address" value={organization.billing_address ? `${organization.billing_address}, ${organization.billing_city}, ${organization.billing_state} ${organization.billing_zip}` : null} />
                </dl>
              </div>

              {/* Accounts Payable Contact */}
              <div>
                <h3 className="text-lg font-medium text-gray-900 mb-4">Accounts Payable Contact</h3>
                <dl className="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-6">
                  <DetailItem icon={User} label="AP Contact Name" value={organization.ap_contact_name} />
                  <DetailItem icon={Mail} label="AP Contact Email" value={organization.ap_contact_email} />
                  <DetailItem icon={Phone} label="AP Contact Phone" value={organization.ap_contact_phone} />
                </dl>
              </div>

            </CardContent>
          </Card>
        </div>
      </div>
    </MainLayout>
  );
}
