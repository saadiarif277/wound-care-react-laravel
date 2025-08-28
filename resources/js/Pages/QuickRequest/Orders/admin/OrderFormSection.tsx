
import React, { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { Button } from '../ui/button';
import { Badge } from '../ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '../ui/dialog';
import { Textarea } from '../ui/textarea';
import { Label } from '../ui/label';
import { AdminOrderData, OrderFormStatus } from '../types/adminTypes';
import { FileText, Eye } from 'lucide-react';

interface OrderFormSectionProps {
  order: AdminOrderData;
  onUpdateOrderFormStatus: (orderNumber: string, status: OrderFormStatus, notes?: string) => void;
}

export const OrderFormSection: React.FC<OrderFormSectionProps> = ({
  order,
  onUpdateOrderFormStatus
}) => {
  const [showStatusModal, setShowStatusModal] = useState(false);
  const [newStatus, setNewStatus] = useState<OrderFormStatus>(order.orderFormData.status);
  const [notes, setNotes] = useState('');

  const getStatusColor = (status: OrderFormStatus): string => {
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
    onUpdateOrderFormStatus(order.orderNumber, newStatus, notes);
    setShowStatusModal(false);
    setNotes('');
  };

  return (
    <>
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <FileText className="h-5 w-5" />
              Order Form Management
            </div>
            <Badge className={getStatusColor(order.orderFormData.status)}>
              {order.orderFormData.status}
            </Badge>
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-2 gap-4 text-sm">
            {order.orderFormData.submissionDate && (
              <div>
                <span className="font-medium">Submission Date:</span>
                <div>{order.orderFormData.submissionDate}</div>
              </div>
            )}
            {order.orderFormData.reviewDate && (
              <div>
                <span className="font-medium">Review Date:</span>
                <div>{order.orderFormData.reviewDate}</div>
              </div>
            )}
            {order.orderFormData.approvalDate && (
              <div>
                <span className="font-medium">Approval Date:</span>
                <div>{order.orderFormData.approvalDate}</div>
              </div>
            )}
            {/* Show submission ID if available */}
            {order.orderFormData.submissionId && (
              <div>
                <span className="font-medium">Submission ID:</span>
                <div className="font-mono text-xs bg-gray-100 p-1 rounded">
                  {order.orderFormData.submissionId}
                </div>
              </div>
            )}
          </div>

          {order.orderFormData.notes && (
            <div className="p-3 bg-muted/50 rounded-lg">
              <span className="font-medium text-sm">Notes:</span>
              <p className="text-sm mt-1">{order.orderFormData.notes}</p>
            </div>
          )}

          <div className="flex gap-2 flex-wrap">
            <Button variant="outline" onClick={() => setShowStatusModal(true)} size="sm">
              Update Status
            </Button>

            {/* Only show View Order Form button if form has been submitted */}
            {order.orderFormData.status === 'Submitted' || order.orderFormData.status === 'Under Review' || order.orderFormData.status === 'Approved' ? (
              <Button variant="outline" size="sm">
                <Eye className="h-4 w-4 mr-2" />
                View Order Form
              </Button>
            ) : (
              <Button variant="outline" size="sm" disabled className="opacity-50 cursor-not-allowed">
                <Eye className="h-4 w-4 mr-2" />
                View Order Form (Submit First)
              </Button>
            )}

            {order.orderFormData.fileUrl && (
              <Button variant="outline" size="sm">
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
              <Select value={newStatus} onValueChange={(value) => setNewStatus(value as OrderFormStatus)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="Draft">Draft</SelectItem>
                  <SelectItem value="Submitted">Submitted</SelectItem>
                  <SelectItem value="Under Review">Under Review</SelectItem>
                  <SelectItem value="Approved">Approved</SelectItem>
                  <SelectItem value="Rejected">Rejected</SelectItem>
                </SelectContent>
              </Select>
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
            <Button variant="outline" onClick={() => setShowStatusModal(false)}>
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
