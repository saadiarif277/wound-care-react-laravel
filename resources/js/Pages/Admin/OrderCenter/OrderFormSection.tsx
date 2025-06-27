import React, { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/Components/Button';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { FileText, Eye } from 'lucide-react';

interface OrderFormSectionProps {
  orderFormData: {
    status: string;
    submissionDate?: string;
    reviewDate?: string;
    approvalDate?: string;
    notes?: string;
    fileUrl?: string;
  };
  onUpdateOrderFormStatus: (status: string, notes?: string) => void;
}

const OrderFormSection: React.FC<OrderFormSectionProps> = ({
  orderFormData,
  onUpdateOrderFormStatus
}) => {
  const [showStatusModal, setShowStatusModal] = useState(false);
  const [newStatus, setNewStatus] = useState<string>(orderFormData.status);
  const [notes, setNotes] = useState('');

  const getStatusColor = (status: string): string => {
    switch (status) {
      case 'Draft': return 'bg-gray-100 text-gray-800';
      case 'Submitted': return 'bg-blue-100 text-blue-800';
      case 'Under Review': return 'bg-yellow-100 text-yellow-800';
      case 'Approved': return 'bg-green-100 text-green-800';
      case 'Rejected': return 'bg-red-100 text-red-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const handleStatusUpdate = () => {
    onUpdateOrderFormStatus(newStatus, notes);
    setShowStatusModal(false);
    setNotes('');
  };

  return (
    <>
      <Card className="my-6">
        <CardHeader>
          <CardTitle className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <FileText className="h-5 w-5" />
              Order Form Management
            </div>
            <Badge className={getStatusColor(orderFormData.status)}>
              {orderFormData.status}
            </Badge>
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-2 gap-4 text-sm">
            {orderFormData.submissionDate && (
              <div>
                <span className="font-medium">Submission Date:</span>
                <div>{orderFormData.submissionDate}</div>
              </div>
            )}
            {orderFormData.reviewDate && (
              <div>
                <span className="font-medium">Review Date:</span>
                <div>{orderFormData.reviewDate}</div>
              </div>
            )}
            {orderFormData.approvalDate && (
              <div>
                <span className="font-medium">Approval Date:</span>
                <div>{orderFormData.approvalDate}</div>
              </div>
            )}
          </div>

          {orderFormData.notes && (
            <div className="p-3 bg-muted/50 rounded-lg">
              <span className="font-medium text-sm">Notes:</span>
              <p className="text-sm mt-1">{orderFormData.notes}</p>
            </div>
          )}

          <div className="flex gap-2 flex-wrap">
            <Button variant="secondary" onClick={() => setShowStatusModal(true)} size="sm">
              Update Status
            </Button>

            {orderFormData.fileUrl && (
              <Button variant="secondary" size="sm">
                <Eye className="h-4 w-4 mr-2" />
                View/Download
              </Button>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Status Update Modal */}
      <Dialog open={showStatusModal} onOpenChange={setShowStatusModal}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Update Order Form Status</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label>New Status</Label>
              <select
                value={newStatus}
                onChange={(e) => setNewStatus(e.target.value)}
                className="w-full border rounded p-2"
              >
                <option value="Draft">Draft</option>
                <option value="Submitted">Submitted</option>
                <option value="Under Review">Under Review</option>
                <option value="Approved">Approved</option>
                <option value="Rejected">Rejected</option>
              </select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="form-status-notes">Notes (optional)</Label>
              <Textarea
                id="form-status-notes"
                placeholder="Add any relevant notes..."
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                rows={3}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="secondary" onClick={() => setShowStatusModal(false)}>
              Cancel
            </Button>
            <Button onClick={handleStatusUpdate}>
              Update Status
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
};

export default OrderFormSection;
