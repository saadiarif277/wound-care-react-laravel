import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { useTheme } from '@/contexts/ThemeContext';
import { themes } from '@/theme/glass-theme';
import { Button } from '@/Components/ui/button';
import {
  Heart,
  Clock,
  CheckCircle,
  AlertTriangle,
  Package,
  Calendar,
  Building2,
  ChevronRight,
  Search,
  Filter,
  RefreshCw,
  ShieldCheck,
  FileText,
  TrendingUp,
  AlertCircle,
  Activity,
} from 'lucide-react';

interface Episode {
  id: string;
  patient_id: string;
  patient_name?: string;
  patient_display_id: string;
  status: string;
  ivr_status: 'pending' | 'verified' | 'expired';
  verification_date?: string;
  expiration_date?: string;
  manufacturer: {
    id: number;
    name: string;
  };
  orders: Array<{
    id: string;
    order_number: string;
    order_status: string;
    created_at: string;
    products: Array<{
      id: number;
      name: string;
      quantity: number;
    }>;
  }>;
  created_at: string;
  updated_at: string;
}

interface Props {
  episodes: {
    data: Episode[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

// Status configurations
const episodeStatusConfig = {
  ready_for_review: {
    label: 'Under Review',
    color: 'text-blue-600',
    bgColor: 'bg-blue-50',
    borderColor: 'border-blue-200',
    icon: Clock,
  },
  ivr_verified: {
    label: 'Insurance Verified',
    color: 'text-green-600',
    bgColor: 'bg-green-50',
    borderColor: 'border-green-200',
    icon: CheckCircle,
  },
  sent_to_manufacturer: {
    label: 'In Production',
    color: 'text-purple-600',
    bgColor: 'bg-purple-50',
    borderColor: 'border-purple-200',
    icon: Package,
  },
  tracking_added: {
    label: 'Shipped',
    color: 'text-indigo-600',
    bgColor: 'bg-indigo-50',
    borderColor: 'border-indigo-200',
    icon: Package,
  },
  completed: {
    label: 'Completed',
    color: 'text-green-600',
    bgColor: 'bg-green-50',
    borderColor: 'border-green-200',
    icon: CheckCircle,
  },
};

const ivrStatusConfig = {
  pending: {
    label: 'Pending Verification',
    color: 'text-amber-600',
    bgColor: 'bg-amber-50',
    borderColor: 'border-amber-200',
    icon: Clock,
  },
  verified: {
    label: 'Insurance Verified',
    color: 'text-green-600',
    bgColor: 'bg-green-50',
    borderColor: 'border-green-200',
    icon: ShieldCheck,
  },
  expired: {
    label: 'Verification Expired',
    color: 'text-red-600',
    bgColor: 'bg-red-50',
    borderColor: 'border-red-200',
    icon: AlertTriangle,
  },
};

export default function ProviderEpisodesIndex({ episodes }: Props) {
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme
  }

  const [searchQuery, setSearchQuery] = useState('');
  const [selectedFilter, setSelectedFilter] = useState<string>('all');
  const [selectedManufacturer, setSelectedManufacturer] = useState<string>('all');

  // Get unique manufacturers from episodes
  const manufacturers = Array.from(new Set(episodes.data.map(e => e.manufacturer.name))).sort();

  // Filter episodes
  const filteredEpisodes = episodes.data.filter(episode => {
    const matchesSearch = searchQuery === '' ||
      episode.patient_display_id.toLowerCase().includes(searchQuery.toLowerCase()) ||
      episode.patient_name?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      episode.manufacturer.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      episode.orders.some(order => order.order_number.toLowerCase().includes(searchQuery.toLowerCase()));

    const matchesFilter = selectedFilter === 'all' ||
      (selectedFilter === 'pending_ivr' && episode.ivr_status === 'pending') ||
      (selectedFilter === 'verified' && episode.ivr_status === 'verified') ||
      (selectedFilter === 'expired' && episode.ivr_status === 'expired') ||
      (selectedFilter === 'completed' && episode.status === 'completed');

    const matchesManufacturer = selectedManufacturer === 'all' ||
      episode.manufacturer.name === selectedManufacturer;

    return matchesSearch && matchesFilter && matchesManufacturer;
  });

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    });
  };

  const getDaysUntilExpiration = (expirationDate?: string) => {
    if (!expirationDate) return null;
    const today = new Date();
    const expDate = new Date(expirationDate);
    const diffTime = expDate.getTime() - today.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays;
  };

  // Calculate statistics
  const stats = {
    total: episodes.total,
    pending: episodes.data.filter(e => e.ivr_status === 'pending').length,
    verified: episodes.data.filter(e => e.ivr_status === 'verified').length,
    expired: episodes.data.filter(e => e.ivr_status === 'expired').length,
    expiringSoon: episodes.data.filter(e => {
      const days = getDaysUntilExpiration(e.expiration_date);
      return days !== null && days > 0 && days <= 30;
    }).length,
  };

  return (
    <MainLayout>
      <Head title="My Episodes | MSC Healthcare" />

      <div className={`min-h-screen ${theme === 'dark' ? 'bg-gray-900' : 'bg-gray-50'}`}>
        {/* Header */}
        <div className={`${t.glass.card} ${t.glass.border} p-6 mb-6`}>
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-3">
              <Heart className="w-8 h-8 text-purple-600" />
              <div>
                <h1 className={`text-2xl font-bold ${t.text.primary}`}>Patient Episodes</h1>
                <p className={`text-sm ${t.text.secondary}`}>
                  Manage patient IVR episodes and track orders
                </p>
              </div>
            </div>
            <div className="flex items-center space-x-3">
              <Button
                variant="ghost"
                onClick={() => router.reload()}
              >
                <RefreshCw className="w-4 h-4 mr-2" />
                Refresh
              </Button>
              <Button
                onClick={() => router.visit(route('product-requests.create'))}
              >
                New Request
              </Button>
            </div>
          </div>
        </div>

        {/* Statistics Cards */}
        <div className="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
          <div className={`${t.glass.card} ${t.glass.border} p-4`}>
            <div className="flex items-center justify-between mb-2">
              <Activity className="w-5 h-5 text-blue-500" />
              <span className={`text-2xl font-bold ${t.text.primary}`}>{stats.total}</span>
            </div>
            <p className={`text-sm ${t.text.secondary}`}>Total Episodes</p>
          </div>

          <div className={`${t.glass.card} ${t.glass.border} p-4`}>
            <div className="flex items-center justify-between mb-2">
              <Clock className="w-5 h-5 text-amber-500" />
              <span className={`text-2xl font-bold ${t.text.primary}`}>{stats.pending}</span>
            </div>
            <p className={`text-sm ${t.text.secondary}`}>Pending IVR</p>
          </div>

          <div className={`${t.glass.card} ${t.glass.border} p-4`}>
            <div className="flex items-center justify-between mb-2">
              <ShieldCheck className="w-5 h-5 text-green-500" />
              <span className={`text-2xl font-bold ${t.text.primary}`}>{stats.verified}</span>
            </div>
            <p className={`text-sm ${t.text.secondary}`}>Verified</p>
          </div>

          <div className={`${t.glass.card} ${t.glass.border} p-4`}>
            <div className="flex items-center justify-between mb-2">
              <AlertTriangle className="w-5 h-5 text-red-500" />
              <span className={`text-2xl font-bold ${t.text.primary}`}>{stats.expired}</span>
            </div>
            <p className={`text-sm ${t.text.secondary}`}>Expired</p>
          </div>

          <div className={`${t.glass.card} ${t.glass.border} p-4`}>
            <div className="flex items-center justify-between mb-2">
              <AlertCircle className="w-5 h-5 text-orange-500" />
              <span className={`text-2xl font-bold ${t.text.primary}`}>{stats.expiringSoon}</span>
            </div>
            <p className={`text-sm ${t.text.secondary}`}>Expiring Soon</p>
          </div>
        </div>

        {/* Filters */}
        <div className={`${t.glass.card} ${t.glass.border} p-4 mb-6`}>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="relative">
              <Search className={`absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 ${t.text.muted}`} />
              <input
                type="text"
                placeholder="Search by patient ID, name, or order number..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className={`w-full pl-10 pr-4 py-2 ${t.input.base} ${t.input.focus} rounded-lg`}
              />
            </div>

            <select
              value={selectedFilter}
              onChange={(e) => setSelectedFilter(e.target.value)}
              className={`px-4 py-2 ${t.input.select} rounded-lg`}
            >
              <option value="all">All Statuses</option>
              <option value="pending_ivr">Pending IVR</option>
              <option value="verified">Verified</option>
              <option value="expired">Expired</option>
              <option value="completed">Completed</option>
            </select>

            <select
              value={selectedManufacturer}
              onChange={(e) => setSelectedManufacturer(e.target.value)}
              className={`px-4 py-2 ${t.input.select} rounded-lg`}
            >
              <option value="all">All Manufacturers</option>
              {manufacturers.map(mfr => (
                <option key={mfr} value={mfr}>{mfr}</option>
              ))}
            </select>
          </div>
        </div>

        {/* Episodes List */}
        <div className={`${t.glass.card} ${t.glass.border} overflow-hidden`}>
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className={theme === 'dark' ? 'bg-gray-800/50' : 'bg-gray-50'}>
                <tr>
                  <th className={`px-6 py-4 text-left text-xs font-medium ${t.text.secondary} uppercase tracking-wider`}>
                    Patient
                  </th>
                  <th className={`px-6 py-4 text-left text-xs font-medium ${t.text.secondary} uppercase tracking-wider`}>
                    Manufacturer
                  </th>
                  <th className={`px-6 py-4 text-left text-xs font-medium ${t.text.secondary} uppercase tracking-wider`}>
                    IVR Status
                  </th>
                  <th className={`px-6 py-4 text-left text-xs font-medium ${t.text.secondary} uppercase tracking-wider`}>
                    Episode Status
                  </th>
                  <th className={`px-6 py-4 text-left text-xs font-medium ${t.text.secondary} uppercase tracking-wider`}>
                    Orders
                  </th>
                  <th className={`px-6 py-4 text-left text-xs font-medium ${t.text.secondary} uppercase tracking-wider`}>
                    Expiration
                  </th>
                  <th className={`px-6 py-4 text-left text-xs font-medium ${t.text.secondary} uppercase tracking-wider`}>
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className={`divide-y ${theme === 'dark' ? 'divide-gray-700' : 'divide-gray-200'}`}>
                {filteredEpisodes.map((episode) => {
                  const episodeStatus = episodeStatusConfig[episode.status] || episodeStatusConfig.ready_for_review;
                  const ivrStatus = ivrStatusConfig[episode.ivr_status];
                  const IvrIcon = ivrStatus.icon;
                  const StatusIcon = episodeStatus.icon;
                  const daysUntilExpiration = getDaysUntilExpiration(episode.expiration_date);

                  return (
                    <tr
                      key={episode.id}
                      className={`${theme === 'dark' ? 'hover:bg-gray-800/30' : 'hover:bg-gray-50'} transition-colors cursor-pointer`}
                      onClick={() => router.visit(route('provider.episodes.show', episode.id))}
                    >
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div>
                          <div className={`text-sm font-medium ${t.text.primary}`}>
                            {episode.patient_name || episode.patient_display_id}
                          </div>
                          <div className={`text-sm ${t.text.secondary}`}>
                            {episode.patient_display_id}
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center">
                          <Building2 className="w-4 h-4 mr-2 text-gray-500" />
                          <span className={`text-sm ${t.text.primary}`}>
                            {episode.manufacturer.name}
                          </span>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center space-x-2">
                          <IvrIcon className={`w-4 h-4 ${ivrStatus.color}`} />
                          <span className={`inline-flex px-2 py-1 text-xs font-medium rounded-full ${ivrStatus.bgColor} ${ivrStatus.color} ${ivrStatus.borderColor} border`}>
                            {ivrStatus.label}
                          </span>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center space-x-2">
                          <StatusIcon className={`w-4 h-4 ${episodeStatus.color}`} />
                          <span className={`inline-flex px-2 py-1 text-xs font-medium rounded-full ${episodeStatus.bgColor} ${episodeStatus.color} ${episodeStatus.borderColor} border`}>
                            {episodeStatus.label}
                          </span>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center space-x-2">
                          <Package className="w-4 h-4 text-gray-500" />
                          <span className={`text-sm ${t.text.primary}`}>
                            {episode.orders.length} order{episode.orders.length !== 1 ? 's' : ''}
                          </span>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        {episode.expiration_date ? (
                          <div>
                            <div className={`text-sm ${
                              daysUntilExpiration && daysUntilExpiration <= 0
                                ? 'text-red-600 font-medium'
                                : daysUntilExpiration && daysUntilExpiration <= 30
                                ? 'text-orange-600 font-medium'
                                : t.text.primary
                            }`}>
                              {formatDate(episode.expiration_date)}
                            </div>
                            {daysUntilExpiration !== null && (
                              <div className={`text-xs ${
                                daysUntilExpiration <= 0
                                  ? 'text-red-500'
                                  : daysUntilExpiration <= 30
                                  ? 'text-orange-500'
                                  : t.text.secondary
                              }`}>
                                {daysUntilExpiration <= 0
                                  ? 'Expired'
                                  : `${daysUntilExpiration} days`}
                              </div>
                            )}
                          </div>
                        ) : (
                          <span className={`text-sm ${t.text.muted}`}>—</span>
                        )}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center space-x-2">
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={(e) => {
                              e.stopPropagation();
                              router.visit(route('provider.episodes.show', episode.id));
                            }}
                          >
                            View
                            <ChevronRight className="w-4 h-4 ml-1" />
                          </Button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {filteredEpisodes.length === 0 && (
            <div className="text-center py-12">
              <Heart className={`w-12 h-12 ${t.text.muted} mx-auto mb-4`} />
              <p className={`text-lg ${t.text.secondary}`}>No episodes found</p>
              <p className={`text-sm ${t.text.muted} mt-2`}>Try adjusting your search or filters</p>
            </div>
          )}

          {/* Pagination */}
          {episodes.last_page > 1 && (
            <div className={`px-6 py-4 border-t ${theme === 'dark' ? 'border-gray-700' : 'border-gray-200'}`}>
              <div className="flex items-center justify-between">
                <p className={`text-sm ${t.text.secondary}`}>
                  Showing {(episodes.current_page - 1) * episodes.per_page + 1} to{' '}
                  {Math.min(episodes.current_page * episodes.per_page, episodes.total)} of{' '}
                  {episodes.total} episodes
                </p>
                <div className="flex items-center space-x-2">
                  <Button
                    variant="outline"
                    size="sm"
                    disabled={episodes.current_page === 1}
                    onClick={() => router.visit(`${route('provider.episodes')}?page=${episodes.current_page - 1}`)}
                  >
                    Previous
                  </Button>
                  <span className={`text-sm ${t.text.primary}`}>
                    Page {episodes.current_page} of {episodes.last_page}
                  </span>
                  <Button
                    variant="outline"
                    size="sm"
                    disabled={episodes.current_page === episodes.last_page}
                    onClick={() => router.visit(`${route('provider.episodes')}?page=${episodes.current_page + 1}`)}
                  >
                    Next
                  </Button>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </MainLayout>
  );
}