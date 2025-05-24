import { usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import FilterBar from '@/Components/FilterBar/FilterBar';
import Pagination from '@/Components/Pagination/Pagination';
import { Organization, PaginatedData } from '@/types';
import Table from '@/Components/Table/Table';
import { Trash2 } from 'lucide-react';

function Index() {
  const { organizations } = usePage<{
    organizations: PaginatedData<Organization>;
  }>().props;

  const {
    data,
    meta: { links }
  } = organizations;

  return (
    <div>
      <h1 className="mb-8 text-3xl font-bold">Organizations</h1>
      <div className="flex items-center justify-between mb-6">
        <FilterBar />
        <div className="text-sm text-gray-600">
          Read-only reference list
        </div>
      </div>
      <Table
        columns={[
          {
            label: 'Name',
            name: 'name',
            renderCell: row => (
              <>
                {row.name}
                {row.deleted_at && (
                  <Trash2 size={16} className="ml-2 text-gray-400" />
                )}
              </>
            )
          },
          { label: 'City', name: 'city' },
          { label: 'Phone', name: 'phone', colSpan: 2 }
        ]}
        rows={data}
      />
      <Pagination links={links} />
    </div>
  );
}

/**
 * Persistent Layout (Inertia.js)
 *
 * [Learn more](https://inertiajs.com/pages#persistent-layouts)
 */
Index.layout = (page: React.ReactNode) => (
  <MainLayout title="Organizations" children={page} />
);

export default Index;
