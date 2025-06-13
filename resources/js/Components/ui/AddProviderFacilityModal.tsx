import React from 'react';
import { router, useForm } from '@inertiajs/react';
import { Modal } from '@/Components/Modal';
import SelectInput from '@/Components/Form/SelectInput';
import LoadingButton from '@/Components/Button/LoadingButton';
import { X } from 'lucide-react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

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
  // Theme setup with fallback
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // If not in ThemeProvider, use dark theme
  }

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
      <div className={cn(t.modal.body)}>
        <div className="flex items-center justify-between mb-4">
          <h2 className={cn("text-lg font-semibold", t.text.primary)}>Associate Facility with Provider</h2>
          <button
            onClick={handleClose}
            className={cn(
              "transition-colors rounded-lg p-1",
              theme === 'dark'
                ? 'text-white/60 hover:text-white/90 hover:bg-white/10'
                : 'text-gray-400 hover:text-gray-600 hover:bg-gray-100'
            )}
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
              className={cn("mr-2 h-4 w-4 rounded",
                theme === 'dark'
                  ? 'text-blue-400 focus:ring-blue-400/30 border-white/20'
                  : 'text-blue-600 focus:ring-blue-500 border-gray-300'
              )}
            />
            <label htmlFor="is_primary" className={cn("text-sm", t.text.secondary)}>
              Set as primary facility for this provider
            </label>
          </div>

          {facilities.length === 0 && (
            <div className={cn(
              "border rounded-lg p-4",
              theme === 'dark'
                ? 'bg-yellow-900/20 border-yellow-700/30 text-yellow-200'
                : 'bg-yellow-50 border-yellow-200 text-yellow-800'
            )}>
              <p className="text-sm">
                No available facilities to associate. All facilities may already be associated with this provider.
              </p>
            </div>
          )}

          <div className={cn("flex justify-end gap-3 pt-4", t.modal.footer)}>
            <button
              type="button"
              onClick={handleClose}
              className={cn(t.button.secondary.base, t.button.secondary.hover)}
            >
              Cancel
            </button>
            <LoadingButton
              type="submit"
              loading={processing}
              className={cn(t.button.primary.base, t.button.primary.hover)}
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
