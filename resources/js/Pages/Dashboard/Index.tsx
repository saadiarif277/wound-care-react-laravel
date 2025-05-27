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

// Define the types
interface Verification {
  id: string;
  customerId: string;
  requestDate?: string;
  patientInfo: {
    firstName: string;
    lastName: string;
  };
  insuranceInfo: {
    payerId: string;
  };
}

interface VerificationResult {
  id: string;
  status: 'active' | 'inactive' | 'pending' | 'error';
}

interface ActionItem {
  id: string;
  type: 'document_required' | 'pa_approval' | 'mac_validation' | 'review_needed';
  title: string;
  description: string;
  priority: 'high' | 'medium' | 'low';
  dueDate?: string;
  link: string;
}

interface ClinicalOpportunity {
  id: string;
  patientName: string;
  opportunity: string;
  rationale: string;
  potentialValue: string;
  urgency: 'high' | 'medium' | 'low';
}

interface Request {
  id: string;
  type: 'product_request' | 'eligibility_check' | 'pa_request' | 'order';
  patientName: string;
  status: 'pending' | 'approved' | 'denied' | 'in_review' | 'completed';
  requestDate: string;
  description: string;
}

interface DashboardData {
  recent_requests: any[];
  action_items: any[];
  metrics: any;
}

interface DashboardProps {
  userRole?: UserRole;
  user: UserWithRole;
  dashboardData: DashboardData;
  roleRestrictions: RoleRestrictions;
}

// Create dummy data
const dummyVerifications: Verification[] = [
  {
    id: 'ver-001',
    customerId: 'customer-001',
    requestDate: '2023-06-15',
    patientInfo: {
      firstName: 'John',
      lastName: 'Doe'
    },
    insuranceInfo: {
      payerId: 'AETNA-123'
    }
  },
  {
    id: 'ver-002',
    customerId: 'customer-002',
    requestDate: '2023-06-14',
    patientInfo: {
      firstName: 'Jane',
      lastName: 'Smith'
    },
    insuranceInfo: {
      payerId: 'BCBS-456'
    }
  },
  {
    id: 'ver-003',
    customerId: 'customer-003',
    requestDate: '2023-06-13',
    patientInfo: {
      firstName: 'Robert',
      lastName: 'Johnson'
    },
    insuranceInfo: {
      payerId: 'MEDICARE-789'
    }
  },
  {
    id: 'ver-004',
    customerId: 'customer-001',
    requestDate: '2023-06-12',
    patientInfo: {
      firstName: 'Sarah',
      lastName: 'Williams'
    },
    insuranceInfo: {
      payerId: 'UNITED-101'
    }
  },
  {
    id: 'ver-005',
    customerId: 'customer-002',
    requestDate: '2023-06-11',
    patientInfo: {
      firstName: 'Michael',
      lastName: 'Brown'
    },
    insuranceInfo: {
      payerId: 'CIGNA-202'
    }
  }
];

const dummyResults: Record<string, VerificationResult> = {
  'ver-001': { id: 'ver-001', status: 'active' },
  'ver-002': { id: 'ver-002', status: 'inactive' },
  'ver-003': { id: 'ver-003', status: 'pending' },
  'ver-004': { id: 'ver-004', status: 'error' },
  'ver-005': { id: 'ver-005', status: 'active' }
};

// New dummy data for enhanced dashboard
const dummyActionItems: ActionItem[] = [
  {
    id: 'action-001',
    type: 'document_required',
    title: 'Additional Documentation Required',
    description: 'Wound assessment photos needed for Request #WC-2024-001',
    priority: 'high',
    dueDate: '2024-01-20',
    link: '/orders/WC-2024-001'
  },
  {
    id: 'action-002',
    type: 'pa_approval',
    title: 'Prior Authorization Pending',
    description: 'PA request for Smith, Jane - Advanced wound dressing',
    priority: 'medium',
    dueDate: '2024-01-22',
    link: '/pa/PA-2024-015'
  },
  {
    id: 'action-003',
    type: 'mac_validation',
    title: 'MAC Validation Warning',
    description: 'Missing osteomyelitis documentation for DFU case',
    priority: 'high',
    link: '/mac-validation/MV-2024-008'
  }
];

const dummyClinicalOpportunities: ClinicalOpportunity[] = [
  {
    id: 'opp-001',
    patientName: 'Robert Johnson',
    opportunity: 'Consider Offloading DME L4631',
    rationale: 'Wagner Grade 3 DFU detected - offloading recommended for optimal healing',
    potentialValue: 'Improved healing outcomes',
    urgency: 'high'
  },
  {
    id: 'opp-002',
    patientName: 'Sarah Williams',
    opportunity: 'Advanced Wound Matrix Therapy',
    rationale: 'Chronic wound >12 weeks with poor healing response to standard care',
    potentialValue: '$2,400 potential revenue',
    urgency: 'medium'
  }
];

const dummyRecentRequests: Request[] = [
  {
    id: 'req-001',
    type: 'product_request',
    patientName: 'John Doe',
    status: 'pending',
    requestDate: '2024-01-15',
    description: 'Advanced alginate dressing for diabetic foot ulcer'
  },
  {
    id: 'req-002',
    type: 'pa_request',
    patientName: 'Jane Smith',
    status: 'approved',
    requestDate: '2024-01-14',
    description: 'Prior authorization for negative pressure wound therapy'
  },
  {
    id: 'req-003',
    type: 'eligibility_check',
    patientName: 'Robert Johnson',
    status: 'completed',
    requestDate: '2024-01-13',
    description: 'Medicare coverage verification'
  },
  {
    id: 'req-004',
    type: 'order',
    patientName: 'Sarah Williams',
    status: 'in_review',
    requestDate: '2024-01-12',
    description: 'Hydrocolloid dressing order - 30 units'
  }
];

export default function Dashboard({ user, dashboardData, roleRestrictions }: DashboardProps) {
  const { auth } = usePage().props as any;
  const currentUserWithRole = auth?.user || user;
  // Map to expected User shape for dashboards
  const currentUser = {
    ...currentUserWithRole,
    name: currentUserWithRole.first_name + ' ' + currentUserWithRole.last_name,
    role_display_name: currentUserWithRole.role || '',
  };
  const currentRoleRestrictions = roleRestrictions || {} as RoleRestrictions;
  const currentDashboardData = dashboardData || { recent_requests: [], action_items: [], metrics: {} };

  // Use user.role for switch
  const userRole = currentUserWithRole.role || '';

  const renderRoleSpecificDashboard = () => {
    switch (userRole) {
      case 'provider':
        return <ProviderDashboard user={currentUser} dashboardData={currentDashboardData} roleRestrictions={currentRoleRestrictions} />;
      case 'office-manager':
        return <OfficeManagerDashboard user={currentUser} dashboardData={currentDashboardData} roleRestrictions={currentRoleRestrictions} />;
      case 'msc-admin':
        return <MscAdminDashboard user={currentUser} />;
      case 'super-admin':
      case 'superadmin':
        return <SuperAdminDashboard user={currentUser} />;
      case 'msc-rep':
        return <MscRepDashboard user={currentUser} />;
      case 'msc-subrep':
        return <MscSubrepDashboard user={currentUser} />;
      default:
        return null;
    }
  };

  return (
    <MainLayout user={currentUser} dashboardData={currentDashboardData} roleRestrictions={currentRoleRestrictions}>
      {renderRoleSpecificDashboard()}
    </MainLayout>
  );
}
