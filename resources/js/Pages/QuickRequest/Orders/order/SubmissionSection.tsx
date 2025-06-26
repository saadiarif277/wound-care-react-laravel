
import React from 'react';
import { SectionCard } from './SectionCard';
import { OrderData, UserRole } from '../../types/orderTypes';
import { ClipboardList } from 'lucide-react';

interface SubmissionSectionProps {
  orderData: OrderData;
  userRole: UserRole;
  orderSubmitted: boolean;
  isOpen: boolean;
  onToggle: (section: string) => void;
}

export const SubmissionSection: React.FC<SubmissionSectionProps> = ({
  orderData,
  userRole,
  orderSubmitted,
  isOpen,
  onToggle
}) => {
  if (!(userRole === 'Admin' || orderSubmitted)) {
    return null;
  }

  return (
    <SectionCard 
      title="Submission Details & Audit Trail" 
      icon={ClipboardList} 
      sectionKey="submission"
      isOpen={isOpen}
      onToggle={onToggle}
    >
      <div className="space-y-4">
        <div className="bg-muted/50 p-4 rounded-lg">
          <h4 className="font-medium text-sm mb-3">Order Timeline</h4>
          <div className="space-y-3">
            <div className="flex items-start gap-3">
              <div className="w-2 h-2 bg-primary rounded-full mt-2"></div>
              <div className="flex-1">
                <div className="text-sm font-medium">Order Created</div>
                <div className="text-xs text-muted-foreground">
                  {orderData.createdDate} by {orderData.createdBy}
                </div>
              </div>
            </div>
            <div className="flex items-start gap-3">
              <div className="w-2 h-2 bg-primary rounded-full mt-2"></div>
              <div className="flex-1">
                <div className="text-sm font-medium">IVR Form Completed</div>
                <div className="text-xs text-muted-foreground">
                  {orderData.ivrForm.submissionDate}
                </div>
              </div>
            </div>
            <div className="flex items-start gap-3">
              <div className="w-2 h-2 bg-primary rounded-full mt-2"></div>
              <div className="flex-1">
                <div className="text-sm font-medium">Order Form Completed</div>
                <div className="text-xs text-muted-foreground">
                  {orderData.orderForm.submissionDate}
                </div>
              </div>
            </div>
            {orderSubmitted && (
              <div className="flex items-start gap-3">
                <div className="w-2 h-2 bg-green-500 rounded-full mt-2"></div>
                <div className="flex-1">
                  <div className="text-sm font-medium">Order Submitted for Review</div>
                  <div className="text-xs text-muted-foreground">
                    {new Date().toLocaleDateString()} by {orderData.createdBy}
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </SectionCard>
  );
};
