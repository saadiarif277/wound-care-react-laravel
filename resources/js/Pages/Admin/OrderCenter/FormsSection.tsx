import React from 'react';
import { SectionCard } from '@/Pages/QuickRequest/Orders/order/SectionCard';
import { Button } from '@/Components/Button';
import { ClipboardList, FileText, Eye } from 'lucide-react';

interface OrderData {
  orderNumber: string;
  createdDate: string;
  createdBy: string;
  patient: any;
  product: any;
  forms: {
    consent: boolean;
    assignmentOfBenefits: boolean;
    medicalNecessity: boolean;
  };
  clinical: any;
  provider: any;
  submission: any;
}

interface FormsSectionProps {
  orderData: OrderData;
  isOpen: boolean;
  onToggle: (section: string) => void;
}

const FormsSection: React.FC<FormsSectionProps> = ({
  orderData,
  isOpen,
  onToggle
}) => (
  <SectionCard
    title="Forms & Documentation"
    icon={ClipboardList}
    sectionKey="forms"
    isOpen={isOpen}
    onToggle={onToggle}
  >
    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div className="bg-muted/50 p-4 rounded-lg">
        <h4 className="font-medium text-sm mb-3 flex items-center gap-2">
          <FileText className="h-4 w-4" />
          Consent Forms
        </h4>
        <div className="space-y-2">
          <div className="flex justify-between">
            <span className="text-sm">Patient Consent:</span>
            <span className="text-sm text-green-600">✓ Completed</span>
          </div>
          <div className="flex justify-between">
            <span className="text-sm">Assignment of Benefits:</span>
            <span className="text-sm text-green-600">✓ Completed</span>
          </div>
          <div className="flex justify-between">
            <span className="text-sm">Medical Necessity:</span>
            <span className="text-sm text-green-600">✓ Established</span>
          </div>
          <div className="flex gap-2 mt-3">
            <Button variant="secondary" size="sm" className="flex-1">
              <Eye className="h-3 w-3 mr-2" />
              View Forms
            </Button>
          </div>
        </div>
      </div>

      <div className="bg-muted/50 p-4 rounded-lg">
        <h4 className="font-medium text-sm mb-3 flex items-center gap-2">
          <FileText className="h-4 w-4" />
          IVR Documentation
        </h4>
        <div className="space-y-2">
          <div className="flex justify-between">
            <span className="text-sm">Status:</span>
            <span className="text-sm text-blue-600">Pending Review</span>
          </div>
          <div className="flex justify-between">
            <span className="text-sm">Submission Date:</span>
            <span className="text-sm">{orderData.createdDate}</span>
          </div>
          <div className="flex gap-2 mt-3">
            <Button variant="secondary" size="sm" className="flex-1">
              <Eye className="h-3 w-3 mr-2" />
              View IVR
            </Button>
          </div>
        </div>
      </div>
    </div>
  </SectionCard>
);

export default FormsSection;
