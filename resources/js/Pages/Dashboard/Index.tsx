import { Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import React from 'react';
import { UserRole, UserWithRole, RoleRestrictions } from '@/types/roles';
import {
  getDashboardTitle,
  getDashboardDescription,
  getFeatureFlags,
  hasPermission
} from '@/lib/roleUtils';
import { usePage } from '@inertiajs/react';

// Import role-specific dashboards
import ProviderDashboard from './Provider/ProviderDashboard';
import OfficeManagerDashboard from './OfficeManager/OfficeManagerDashboard';
import MscAdminDashboard from './Admin/MscAdminDashboard';
import SuperAdminDashboard from './Admin/SuperAdminDashboard';
import MscRepDashboard from './Sales/MscRepDashboard';
import MscSubrepDashboard from './Sales/MscSubrepDashboard';

// Define the types - updated to match backend data
interface ActionItem {
  id: string;
  type: 'document_required' | 'pa_approval' | 'mac_validation' | 'review_needed';
  patient_name: string;
  description: string;
  priority: 'high' | 'medium' | 'low';
  due_date?: string;
  request_id: string;
}

interface RecentRequest {
  id: string;
  request_number: string;
  patient_name: string;
  wound_type: string;
  status: string;
  created_at: string;
  facility_name: string;
  total_amount?: number;
}

interface DashboardMetrics {
  total_requests: number;
  pending_requests: number;
  approved_requests: number;
  monthly_requests: number;
  monthly_revenue?: number;
}

interface DashboardData {
  recent_requests: RecentRequest[];
  action_items: ActionItem[];
  metrics: DashboardMetrics;
  // Role-specific data will be added here
  clinical_opportunities?: any[];
  eligibility_status?: any[];
  facility_metrics?: any;
  provider_activity?: any[];
  commission_summary?: any[];
  territory_performance?: any[];
  business_metrics?: any;
  pending_approvals?: any[];
  system_metrics?: any;
  security_overview?: any;
  platform_health?: any;
}

interface DashboardProps {
  userRole?: UserRole;
  user: UserWithRole;
  dashboardData: DashboardData;
  roleRestrictions: RoleRestrictions;
}

export default function Dashboard({ user, dashboardData, roleRestrictions }: DashboardProps) {
  const renderRoleSpecificDashboard = () => {
    const role = user.role;

    // Pass real data from backend instead of mock data
    const commonProps = {
      user,
      dashboardData,
      roleRestrictions
    };

    switch (role) {
      case 'provider':
        return <ProviderDashboard {...commonProps} />;
      case 'office-manager':
        return <OfficeManagerDashboard {...commonProps} />;
      case 'msc-rep':
        return <MscRepDashboard {...commonProps} />;
      case 'msc-subrep':
        return <MscSubrepDashboard {...commonProps} />;
      case 'msc-admin':
        return <MscAdminDashboard {...commonProps} />;
      case 'super-admin':
        return <SuperAdminDashboard {...commonProps} />;
      default:
        return <ProviderDashboard {...commonProps} />;
    }
  };

  return (
    <MainLayout>
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">
              {getDashboardTitle(user.role)}
            </h1>
            <p className="text-gray-600">
              {getDashboardDescription(user.role)}
            </p>
          </div>
        </div>

        {renderRoleSpecificDashboard()}
      </div>
    </MainLayout>
  );
}
