import React from 'react';
import { Link } from '@inertiajs/react';
import { AlertTriangle, Clock, ChevronRight } from 'lucide-react';

interface ExpiringIVR {
  id: number;
  patient_fhir_id: string;
  patient_name: string;
  patient_display_id: string;
  manufacturer_name: string;
  expiration_date: string;
  days_until_expiration: number;
}

interface IVRExpirationWarningProps {
  expiringIVRs: ExpiringIVR[];
}

export default function IVRExpirationWarning({ expiringIVRs }: IVRExpirationWarningProps) {
  if (!expiringIVRs || expiringIVRs.length === 0) {
    return null;
  }

  const urgentIVRs = expiringIVRs.filter(ivr => ivr.days_until_expiration <= 7);
  const warningIVRs = expiringIVRs.filter(ivr => ivr.days_until_expiration > 7 && ivr.days_until_expiration <= 30);

  return (
    <div className="mb-6 space-y-3">
      {urgentIVRs.length > 0 && (
        <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
          <div className="flex items-start">
            <AlertTriangle className="w-5 h-5 text-red-600 dark:text-red-400 mt-0.5 mr-3 flex-shrink-0" />
            <div className="flex-1">
              <h3 className="text-sm font-medium text-red-900 dark:text-red-100">
                {urgentIVRs.length} IVR{urgentIVRs.length === 1 ? '' : 's'} Expiring This Week
              </h3>
              <div className="mt-2 space-y-1">
                {urgentIVRs.slice(0, 3).map((ivr) => (
                  <div key={ivr.id} className="text-sm text-red-700 dark:text-red-200">
                    <span className="font-medium">{ivr.patient_name}</span>
                    <span className="text-red-600 dark:text-red-300">
                      {' '}• {ivr.manufacturer_name} • {ivr.days_until_expiration === 0 ? 'Expires today' : `${ivr.days_until_expiration} day${ivr.days_until_expiration === 1 ? '' : 's'} left`}
                    </span>
                  </div>
                ))}
              </div>
              <Link
                href="/admin/patients/ivr-status?expiring_soon=true"
                className="mt-3 inline-flex items-center text-sm font-medium text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200"
              >
                View all urgent IVRs
                <ChevronRight className="w-4 h-4 ml-1" />
              </Link>
            </div>
          </div>
        </div>
      )}

      {warningIVRs.length > 0 && (
        <div className="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg p-4">
          <div className="flex items-start">
            <Clock className="w-5 h-5 text-orange-600 dark:text-orange-400 mt-0.5 mr-3 flex-shrink-0" />
            <div className="flex-1">
              <h3 className="text-sm font-medium text-orange-900 dark:text-orange-100">
                {warningIVRs.length} IVR{warningIVRs.length === 1 ? '' : 's'} Expiring Within 30 Days
              </h3>
              <p className="mt-1 text-sm text-orange-700 dark:text-orange-200">
                Contact providers to renew IVR documentation before expiration.
              </p>
              <Link
                href="/admin/patients/ivr-status"
                className="mt-2 inline-flex items-center text-sm font-medium text-orange-600 dark:text-orange-400 hover:text-orange-800 dark:hover:text-orange-200"
              >
                Manage IVR status
                <ChevronRight className="w-4 h-4 ml-1" />
              </Link>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}