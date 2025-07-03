
import React from 'react';
import { SectionCard } from './SectionCard';
import { OrderData } from '../types/orderTypes';
import { Button } from '../ui/button';
import { ClipboardList, FileText, Eye } from 'lucide-react';

interface FormsSectionProps {
  orderData: OrderData;
  isOpen: boolean;
  onToggle: (section: string) => void;
}

export const FormsSection: React.FC<FormsSectionProps> = ({
  orderData,
  isOpen,
  onToggle
}) => (
  <SectionCard
    title="IVR Form & Order Form"
    icon={ClipboardList}
    sectionKey="forms"
    isOpen={isOpen}
    onToggle={onToggle}
  >
    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div className="bg-muted/50 p-4 rounded-lg">
        <h4 className="font-medium text-sm mb-3 flex items-center gap-2">
          <FileText className="h-4 w-4" />
          IVR Form
        </h4>
        <div className="space-y-2">
          <div className="flex justify-between">
            <span className="text-sm">Status:</span>
            <span className="text-sm">{orderData.ivrForm?.status || 'N/A'}</span>
          </div>
          <div className="flex justify-between">
            <span className="text-sm">Submission Date:</span>
            <span className="text-sm">{orderData.ivrForm?.submissionDate || 'N/A'}</span>
          </div>
          <div className="flex gap-2 mt-3">
            <Button variant="outline" size="sm" className="flex-1">
              <Eye className="h-3 w-3 mr-2" />
              View/Download
            </Button>
          </div>
        </div>
      </div>

      <div className="bg-muted/50 p-4 rounded-lg">
        <h4 className="font-medium text-sm mb-3 flex items-center gap-2">
          <FileText className="h-4 w-4" />
          Order Form
        </h4>
        <div className="space-y-2">
          <div className="flex justify-between">
            <span className="text-sm">Status:</span>
            <span className="text-sm">{orderData.orderForm?.status || 'N/A'}</span>
          </div>
          <div className="flex justify-between">
            <span className="text-sm">Submission Date:</span>
            <span className="text-sm">{orderData.orderForm?.submissionDate || 'N/A'}</span>
          </div>
          <div className="flex gap-2 mt-3">
            <Button variant="outline" size="sm" className="flex-1">
              <Eye className="h-3 w-3 mr-2" />
              View/Download
            </Button>
          </div>
        </div>
      </div>
    </div>
  </SectionCard>
);
