import React from 'react';
import { useForm, Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { ArrowLeft } from 'lucide-react';

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
}

interface Props {
  organization: Organization;
}

export default function OrganizationEdit({ organization }: Props) {
  const { data, setData, put, processing, errors } = useForm({
    name: organization.name || '',
    type: organization.type || 'healthcare',
    status: organization.status || 'active',
    contact_email: organization.contact_email || '',
    phone: organization.phone || '',
    address: organization.address || '',
    city: organization.city || '',
    state: organization.state || '',
    zip_code: organization.zip_code || '',
    billing_address: organization.billing_address || '',
    billing_city: organization.billing_city || '',
    billing_state: organization.billing_state || '',
    billing_zip: organization.billing_zip || '',
    ap_contact_name: organization.ap_contact_name || '',
    ap_contact_phone: organization.ap_contact_phone || '',
    ap_contact_email: organization.ap_contact_email || '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put(route('admin.organizations.update', organization.id));
  };

  return (
    <MainLayout>
      <Head title={`Edit ${organization.name}`} />

      <div className="py-6">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="mb-6">
            <Link
              href={route('admin.organizations.index')}
              className="inline-flex items-center text-sm text-gray-600 hover:text-gray-900"
            >
              <ArrowLeft className="h-4 w-4 mr-2" />
              Back to Organizations
            </Link>
          </div>

          <form onSubmit={handleSubmit}>
            <Card>
              <CardHeader>
                <CardTitle>Edit Organization</CardTitle>
                <CardDescription>Update the details for {organization.name}.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-8">
                {/* Primary Information */}
                <div className="space-y-4">
                  <h3 className="text-lg font-medium text-gray-900">Primary Information</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                      <Label htmlFor="name">Organization Name</Label>
                      <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                      {errors.name && <p className="text-red-500 text-xs mt-1">{errors.name}</p>}
                    </div>
                    <div>
                      <Label htmlFor="contact_email">Contact Email</Label>
                      <Input id="contact_email" type="email" value={data.contact_email} onChange={(e) => setData('contact_email', e.target.value)} />
                      {errors.contact_email && <p className="text-red-500 text-xs mt-1">{errors.contact_email}</p>}
                    </div>
                    <div>
                      <Label htmlFor="phone">Phone Number</Label>
                      <Input id="phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                      {errors.phone && <p className="text-red-500 text-xs mt-1">{errors.phone}</p>}
                    </div>
                  </div>
                </div>

                {/* Address Information */}
                <div className="space-y-4">
                  <h3 className="text-lg font-medium text-gray-900">Mailing Address</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="md:col-span-2">
                      <Label htmlFor="address">Street Address</Label>
                      <Input id="address" value={data.address} onChange={(e) => setData('address', e.target.value)} />
                      {errors.address && <p className="text-red-500 text-xs mt-1">{errors.address}</p>}
                    </div>
                    <div>
                      <Label htmlFor="city">City</Label>
                      <Input id="city" value={data.city} onChange={(e) => setData('city', e.target.value)} />
                      {errors.city && <p className="text-red-500 text-xs mt-1">{errors.city}</p>}
                    </div>
                    <div>
                      <Label htmlFor="state">State</Label>
                      <Input id="state" value={data.state} onChange={(e) => setData('state', e.target.value)} />
                      {errors.state && <p className="text-red-500 text-xs mt-1">{errors.state}</p>}
                    </div>
                    <div>
                      <Label htmlFor="zip_code">ZIP Code</Label>
                      <Input id="zip_code" value={data.zip_code} onChange={(e) => setData('zip_code', e.target.value)} />
                      {errors.zip_code && <p className="text-red-500 text-xs mt-1">{errors.zip_code}</p>}
                    </div>
                  </div>
                </div>

                {/* Billing Address */}
                <div className="space-y-4">
                  <h3 className="text-lg font-medium text-gray-900">Billing Address</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="md:col-span-2">
                      <Label htmlFor="billing_address">Street Address</Label>
                      <Input id="billing_address" value={data.billing_address} onChange={(e) => setData('billing_address', e.target.value)} />
                      {errors.billing_address && <p className="text-red-500 text-xs mt-1">{errors.billing_address}</p>}
                    </div>
                    <div>
                      <Label htmlFor="billing_city">City</Label>
                      <Input id="billing_city" value={data.billing_city} onChange={(e) => setData('billing_city', e.target.value)} />
                      {errors.billing_city && <p className="text-red-500 text-xs mt-1">{errors.billing_city}</p>}
                    </div>
                    <div>
                      <Label htmlFor="billing_state">State</Label>
                      <Input id="billing_state" value={data.billing_state} onChange={(e) => setData('billing_state', e.target.value)} />
                      {errors.billing_state && <p className="text-red-500 text-xs mt-1">{errors.billing_state}</p>}
                    </div>
                    <div>
                      <Label htmlFor="billing_zip">ZIP Code</Label>
                      <Input id="billing_zip" value={data.billing_zip} onChange={(e) => setData('billing_zip', e.target.value)} />
                      {errors.billing_zip && <p className="text-red-500 text-xs mt-1">{errors.billing_zip}</p>}
                    </div>
                  </div>
                </div>

                {/* Accounts Payable Contact */}
                <div className="space-y-4">
                  <h3 className="text-lg font-medium text-gray-900">Accounts Payable Contact</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                      <Label htmlFor="ap_contact_name">AP Contact Name</Label>
                      <Input id="ap_contact_name" value={data.ap_contact_name} onChange={(e) => setData('ap_contact_name', e.target.value)} />
                      {errors.ap_contact_name && <p className="text-red-500 text-xs mt-1">{errors.ap_contact_name}</p>}
                    </div>
                    <div>
                      <Label htmlFor="ap_contact_email">AP Contact Email</Label>
                      <Input id="ap_contact_email" type="email" value={data.ap_contact_email} onChange={(e) => setData('ap_contact_email', e.target.value)} />
                      {errors.ap_contact_email && <p className="text-red-500 text-xs mt-1">{errors.ap_contact_email}</p>}
                    </div>
                    <div>
                      <Label htmlFor="ap_contact_phone">AP Contact Phone</Label>
                      <Input id="ap_contact_phone" value={data.ap_contact_phone} onChange={(e) => setData('ap_contact_phone', e.target.value)} />
                      {errors.ap_contact_phone && <p className="text-red-500 text-xs mt-1">{errors.ap_contact_phone}</p>}
                    </div>
                  </div>
                </div>

              </CardContent>
              <CardFooter className="flex justify-end">
                <Button type="submit" disabled={processing}>
                  {processing ? 'Saving...' : 'Save Changes'}
                </Button>
              </CardFooter>
            </Card>
          </form>
        </div>
      </div>
    </MainLayout>
  );
}
