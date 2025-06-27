import React from 'react';
import { SectionCard } from '@/Pages/QuickRequest/Orders/order/SectionCard';
import { ClipboardList, Clock, CheckCircle, AlertCircle } from 'lucide-react';

interface OrderData {
  orderNumber: string;
  createdDate: string;
  createdBy: string;
  patient: any;
  product: any;
  forms: any;
  clinical: any;
  provider: any;
}

interface SubmissionSectionProps {
  orderData: OrderData;
  userRole: 'Provider' | 'OM' | 'Admin';
  orderSubmitted: boolean;
  isOpen: boolean;
  onToggle: (section: string) => void;
}

const SubmissionSection: React.FC<SubmissionSectionProps> = ({
  orderData,
  userRole,
  orderSubmitted,
  isOpen,
  onToggle
}) => {
  if (!(userRole === 'Admin' || orderSubmitted)) {
    return null;
  }

  // Mock activity log data - in real implementation this would come from the database
  const activityLog = [
    {
      id: 1,
      action: 'Order created',
      timestamp: orderData.createdDate,
      user: orderData.createdBy,
      status: 'completed'
    },
    {
      id: 2,
      action: 'IVR status updated to Pending',
      timestamp: '2024-07-01',
      user: 'System',
      status: 'completed'
    },
    {
      id: 3,
      action: 'Order form status updated to Draft',
      timestamp: '2024-07-01',
      user: 'System',
      status: 'completed'
    },
    {
      id: 4,
      action: 'Order submitted for admin review',
      timestamp: orderSubmitted ? new Date().toLocaleDateString() : '2024-07-01',
      user: orderData.createdBy,
      status: 'completed'
    }
  ];

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'completed':
        return <CheckCircle className="h-4 w-4 text-green-500" />;
      case 'pending':
        return <Clock className="h-4 w-4 text-yellow-500" />;
      case 'error':
        return <AlertCircle className="h-4 w-4 text-red-500" />;
      default:
        return <Clock className="h-4 w-4 text-gray-500" />;
    }
  };

  return (
    <SectionCard
      title="Activity Log"
      icon={ClipboardList}
      sectionKey="submission"
      isOpen={isOpen}
      onToggle={onToggle}
    >
      <div className="space-y-4">
        <div className="bg-muted/50 p-4 rounded-lg">
          <h4 className="font-medium text-sm mb-3">Order Activity Timeline</h4>
          <div className="space-y-3">
            {activityLog.map((activity) => (
              <div key={activity.id} className="flex items-start gap-3">
                <div className="flex-shrink-0 mt-1">
                  {getStatusIcon(activity.status)}
                </div>
                <div className="flex-1">
                  <div className="text-sm font-medium">{activity.action}</div>
                  <div className="text-xs text-muted-foreground">
                    {activity.timestamp} by {activity.user}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </SectionCard>
  );
};

export default SubmissionSection;
