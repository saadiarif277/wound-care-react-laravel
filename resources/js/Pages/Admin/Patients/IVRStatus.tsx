import React, { useState, useMemo, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import {
  Search,
  Calendar,
  AlertTriangle,
  CheckCircle,
  Clock,
  FileText,
  RefreshCw,
  Building2,
  User,
  ChevronRight,
  Filter,
  Download,
} from 'lucide-react';

interface PatientIVR {
  id: number;
  patient_fhir_id: string;
  patient_name: string;
  patient_display_id: string;
  manufacturer: {
    id: number;
    name: string;
  };
  last_verified_date: string | null;
  expiration_date: string | null;
  frequency: 'weekly' | 'monthly' | 'quarterly' | 'yearly';
  status: 'active' | 'expired' | 'pending';
  latest_docuseal_submission_id: string | null;
  notes: string | null;
}

interface IVRStatusProps {
  patientIVRs: PatientIVR[];
  expiringIVRs: PatientIVR[];
  filters: {
    search?: string;
    status?: string;
    manufacturer?: string;
    expiring_soon?: boolean;
  };
  manufacturers: Array<{ id: number; name: string }>;
  patientId: string;
}

export default function IVRStatus({
  patientIVRs,
  expiringIVRs,
  filters,
  manufacturers,
  patientId,
}: IVRStatusProps) {
  const [searchTerm, setSearchTerm] = useState(filters.search || '');
  const [selectedStatus, setSelectedStatus] = useState(filters.status || '');
  const [selectedManufacturer, setSelectedManufacturer] = useState(filters.manufacturer || '');
  const [showExpiringSoon, setShowExpiringSoon] = useState(filters.expiring_soon || false);
  const [ivrEpisodes, setIvrEpisodes] = useState([]);

  const formatDate = (dateString: string | null) => {
    if (!dateString) return 'Never';
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  };

  const getDaysUntilExpiration = (expirationDate: string | null) => {
    if (!expirationDate) return null;
    const days = Math.ceil((new Date(expirationDate).getTime() - new Date().getTime()) / (1000 * 60 * 60 * 24));
    return days;
  };

  const getStatusBadge = (ivr: PatientIVR) => {
    const daysUntilExpiration = getDaysUntilExpiration(ivr.expiration_date);

    if (ivr.status === 'expired' || (daysUntilExpiration !== null && daysUntilExpiration < 0)) {
      return (
        <Badge className="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
          <AlertTriangle className="w-3 h-3 mr-1" />
          Expired
        </Badge>
      );
    } else if (daysUntilExpiration !== null && daysUntilExpiration <= 30) {
      return (
        <Badge className="bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
          <Clock className="w-3 h-3 mr-1" />
          Expires in {daysUntilExpiration} days
        </Badge>
      );
    } else if (ivr.status === 'active') {
      return (
        <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
          <CheckCircle className="w-3 h-3 mr-1" />
          Active
        </Badge>
      );
    } else {
      return (
        <Badge className="bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200">
          <Clock className="w-3 h-3 mr-1" />
          Pending
        </Badge>
      );
    }
  };

  const getFrequencyBadge = (frequency: string) => {
    const frequencyConfig = {
      weekly: { label: 'Weekly', color: 'purple' },
      monthly: { label: 'Monthly', color: 'blue' },
      quarterly: { label: 'Quarterly', color: 'green' },
      yearly: { label: 'Yearly', color: 'gray' },
    };

    const config = frequencyConfig[frequency as keyof typeof frequencyConfig] || { label: frequency, color: 'gray' };

    return (
      <Badge variant="outline" className={`border-${config.color}-300 text-${config.color}-700`}>
        <RefreshCw className="w-3 h-3 mr-1" />
        {config.label}
      </Badge>
    );
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    router.get(window.location.pathname, {
      search: searchTerm,
      status: selectedStatus,
      manufacturer: selectedManufacturer,
      expiring_soon: showExpiringSoon
    });
  };

  // Group IVRs by patient
  const groupedByPatient = useMemo(() => {
    const grouped: { [key: string]: PatientIVR[] } = {};

    patientIVRs.forEach(ivr => {
      const key = ivr.patient_fhir_id;
      if (!grouped[key]) {
        grouped[key] = [];
      }
      grouped[key].push(ivr);
    });

    return Object.entries(grouped).map(([patientFhirId, ivrs]) => ({
      patient_fhir_id: patientFhirId,
      patient_name: ivrs[0].patient_name,
      patient_display_id: ivrs[0].patient_display_id,
      ivrs: ivrs.sort((a, b) => {
        // Sort by expiration date, expired first
        if (!a.expiration_date) return -1;
        if (!b.expiration_date) return 1;
        return new Date(a.expiration_date).getTime() - new Date(b.expiration_date).getTime();
      })
    }));
  }, [patientIVRs]);

  useEffect(() => {
    // Fetch all IVR episodes for this patient (API endpoint needed)
  }, [patientId]);

  return (
    <MainLayout>
      <Head title="Patient IVR Status" />

      <div className="w-full min-h-screen bg-gray-50 dark:bg-gray-900 py-4 px-2 sm:px-4 md:px-8">
        {/* Header */}
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Patient IVR Status</h1>
          <p className="text-gray-600 dark:text-gray-300">Track IVR verification status by patient and manufacturer</p>
        </div>

        {/* Expiring Soon Alert */}
        {expiringIVRs.length > 0 && (
          <Card className="mb-6 p-4 bg-orange-50 dark:bg-orange-900/20 border-orange-200 dark:border-orange-800">
            <div className="flex items-start space-x-3">
              <AlertTriangle className="w-5 h-5 text-orange-600 dark:text-orange-300 mt-0.5" />
              <div className="flex-1">
                <h3 className="text-sm font-medium text-orange-900 dark:text-orange-100">
                  {expiringIVRs.length} IVR{expiringIVRs.length === 1 ? '' : 's'} Expiring Soon
                </h3>
                <p className="text-sm text-orange-700 dark:text-orange-200 mt-1">
                  These IVRs will expire within the next 30 days and need to be renewed.
                </p>
                <div className="mt-3 space-y-2">
                  {expiringIVRs.slice(0, 3).map((ivr) => (
                    <div key={ivr.id} className="text-sm">
                      <span className="font-medium text-orange-900 dark:text-orange-100">
                        {ivr.patient_name}
                      </span>
                      <span className="text-orange-700 dark:text-orange-200">
                        {' '}• {ivr.manufacturer.name} • Expires {formatDate(ivr.expiration_date)}
                      </span>
                    </div>
                  ))}
                  {expiringIVRs.length > 3 && (
                    <button
                      onClick={() => setShowExpiringSoon(true)}
                      className="text-sm text-orange-600 dark:text-orange-300 hover:text-orange-800 dark:hover:text-orange-100"
                    >
                      View all {expiringIVRs.length} expiring IVRs →
                    </button>
                  )}
                </div>
              </div>
            </div>
          </Card>
        )}

        {/* Search and Filters */}
        <Card className="p-4 mb-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
          <form onSubmit={handleSearch} className="flex flex-wrap gap-4">
            <div className="flex-1 min-w-[200px] relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500 w-5 h-5" />
              <input
                type="text"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                placeholder="Search patients..."
                className="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:bg-gray-900 dark:text-white dark:placeholder-gray-400"
              />
            </div>

            <select
              aria-label="Filter by status"
              value={selectedStatus}
              onChange={(e) => setSelectedStatus(e.target.value)}
              className="px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:bg-gray-900 dark:text-white"
            >
              <option value="">All Statuses</option>
              <option value="active">Active</option>
              <option value="expired">Expired</option>
              <option value="pending">Pending</option>
            </select>

            <select
              aria-label="Filter by manufacturer"
              value={selectedManufacturer}
              onChange={(e) => setSelectedManufacturer(e.target.value)}
              className="px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:bg-gray-900 dark:text-white"
            >
              <option value="">All Manufacturers</option>
              {manufacturers.map((manufacturer) => (
                <option key={manufacturer.id} value={manufacturer.id}>
                  {manufacturer.name}
                </option>
              ))}
            </select>

            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={showExpiringSoon}
                onChange={(e) => setShowExpiringSoon(e.target.checked)}
                className="rounded border-gray-300 dark:border-gray-700 text-red-600 focus:ring-red-500"
              />
              <span className="text-sm text-gray-700 dark:text-gray-200">Expiring Soon Only</span>
            </label>

            <button
              type="submit"
              className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 flex items-center gap-2"
            >
              <Filter className="w-4 h-4" />
              Apply Filters
            </button>
          </form>
        </Card>

        {/* Patient IVR List */}
        <div className="space-y-4">
          {groupedByPatient.length === 0 ? (
            <Card className="p-8 text-center bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
              <FileText className="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600 mb-4" />
              <p className="text-gray-500 dark:text-gray-300">No patient IVR records found</p>
              <p className="text-sm text-gray-400 dark:text-gray-500 mt-1">
                IVR records will appear here as orders are processed
              </p>
            </Card>
          ) : (
            groupedByPatient.map((patientGroup) => (
              <Card key={patientGroup.patient_fhir_id} className="p-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <div className="mb-4">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-3">
                      <User className="w-5 h-5 text-gray-500" />
                      <div>
                        <h3 className="text-lg font-medium text-gray-900 dark:text-white">
                          {patientGroup.patient_name}
                        </h3>
                        <p className="text-sm text-gray-500 dark:text-gray-300">
                          ID: {patientGroup.patient_display_id}
                        </p>
                      </div>
                    </div>
                    <Link
                      href={`/admin/patients/${patientGroup.patient_fhir_id}/orders`}
                      className="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200 flex items-center gap-1"
                    >
                      View Orders
                      <ChevronRight className="w-4 h-4" />
                    </Link>
                  </div>
                </div>

                <div className="space-y-3">
                  {patientGroup.ivrs.map((ivr) => (
                    <div key={ivr.id} className="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                      <div className="flex items-center space-x-4">
                        <Building2 className="w-4 h-4 text-gray-500" />
                        <div>
                          <p className="text-sm font-medium text-gray-900 dark:text-white">
                            {ivr.manufacturer.name}
                          </p>
                          <p className="text-xs text-gray-500 dark:text-gray-300">
                            Last Verified: {formatDate(ivr.last_verified_date)}
                          </p>
                        </div>
                      </div>

                      <div className="flex items-center space-x-3">
                        {getFrequencyBadge(ivr.frequency)}
                        {getStatusBadge(ivr)}

                        {ivr.latest_docuseal_submission_id && (
                          <button
                            className="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200"
                            title="Download IVR Document"
                          >
                            <Download className="w-4 h-4" />
                          </button>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </Card>
            ))
          )}
        </div>
      </div>
    </MainLayout>
  );
}
