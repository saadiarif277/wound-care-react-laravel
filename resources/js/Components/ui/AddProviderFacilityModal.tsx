import React from 'react';
import { router, useForm } from '@inertiajs/react';
import { Modal } from '@/Components/Modal';
import SelectInput from '@/Components/Form/SelectInput';
import LoadingButton from '@/Components/Button/LoadingButton';
import { X } from 'lucide-react';

interface AddProviderFacilityModalProps {
  isOpen: boolean;
  onClose: () => void;
  providerId: number;
  facilities: Array<{
    id: number;
    name: string;
    address: string;
  }>;
}

export default function AddProviderFacilityModal({ isOpen, onClose, providerId, facilities }: AddProviderFacilityModalProps) {
  const { data, setData, post, processing, errors, reset } = useForm({
    facility_id: '',
    is_primary: false,
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    post(`/api/providers/${providerId}/facilities`, {
      onSuccess: () => {
        reset();
        onClose();
        router.reload();
      },
    });
  };

  const handleClose = () => {
    reset();
    onClose();
  };

  return (
    <Modal show={isOpen} onClose={handleClose} maxWidth="md">
      <div className="p-6">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-semibold">Associate Facility with Provider</h2>
          <button
            onClick={handleClose}
            className="text-gray-400 hover:text-gray-600"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <SelectInput
            label="Select Facility"
            value={data.facility_id}
            onChange={(e) => setData('facility_id', e.target.value)}
            error={errors.facility_id}
            required
          >
            <option value="">Choose a facility...</option>
            {facilities.map((facility) => (
              <option key={facility.id} value={facility.id}>
                {facility.name} - {facility.address}
              </option>
            ))}
          </SelectInput>

          <div className="flex items-center">
            <input
              type="checkbox"
              id="is_primary"
              checked={data.is_primary}
              onChange={(e) => setData('is_primary', e.target.checked)}
              className="mr-2 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
            />
            <label htmlFor="is_primary" className="text-sm text-gray-700">
              Set as primary facility for this provider
            </label>
          </div>

          {facilities.length === 0 && (
            <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
              <p className="text-sm text-yellow-800">
                No available facilities to associate. All facilities may already be associated with this provider.
              </p>
            </div>
          )}

          <div className="flex justify-end gap-3 pt-4">
            <button
              type="button"
              onClick={handleClose}
              className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
            >
              Cancel
            </button>
            <LoadingButton
              type="submit"
              loading={processing}
              className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
              disabled={!data.facility_id}
            >
              Associate Facility
            </LoadingButton>
          </div>
        </form>
      </div>
    </Modal>
  );
}