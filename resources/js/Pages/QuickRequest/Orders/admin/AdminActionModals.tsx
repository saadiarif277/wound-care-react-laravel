
import React, { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/Components/GhostAiUi/ui/dialog';
import { Button } from '@/Components/GhostAiUi/ui/button';
import { Textarea } from '@/Components/GhostAiUi/ui/textarea';
import { RadioGroup, RadioGroupItem } from '@/Components/GhostAiUi/ui/radio-group';
import { Label } from '@/Components/GhostAiUi/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/GhostAiUi/ui/select';
import { AdminOrderData } from '../types/adminTypes';

interface AdminActionModalsProps {
  order: AdminOrderData;
  showIVRModal: boolean;
  showApprovalModal: boolean;
  showUploadModal: boolean;
  onIVRModalChange: (open: boolean) => void;
  onApprovalModalChange: (open: boolean) => void;
  onUploadModalChange: (open: boolean) => void;
  onGenerateIVR: (orderNumber: string, skipIVR?: boolean, reason?: string) => void;
  onStatusChange: (orderNumber: string, newStatus: any, notes?: string) => void;
  onUploadDocument: (orderNumber: string, file: File, documentType: string) => void;
}

export const AdminActionModals: React.FC<AdminActionModalsProps> = ({
  order,
  showIVRModal,
  showApprovalModal,
  showUploadModal,
  onIVRModalChange,
  onApprovalModalChange,
  onUploadModalChange,
  onGenerateIVR,
  onStatusChange,
  onUploadDocument
}) => {
  const [ivrRequired, setIvrRequired] = useState('yes');
  const [ivrReason, setIvrReason] = useState('');
  const [approvalAction, setApprovalAction] = useState('');
  const [approvalNotes, setApprovalNotes] = useState('');
  const [documentType, setDocumentType] = useState('');
  const [selectedFile, setSelectedFile] = useState<File | null>(null);

  const handleIVRSubmit = () => {
    const skipIVR = ivrRequired === 'no';
    onGenerateIVR(order.orderNumber, skipIVR, skipIVR ? ivrReason : undefined);
    onIVRModalChange(false);
    setIvrRequired('yes');
    setIvrReason('');
  };

  const handleApprovalSubmit = () => {
    if (!approvalAction) return;
    
    const statusMap: Record<string, any> = {
      'approve': 'Approved',
      'send-back': 'Send Back',
      'deny': 'Denied'
    };
    
    onStatusChange(order.orderNumber, statusMap[approvalAction], approvalNotes);
    onApprovalModalChange(false);
    setApprovalAction('');
    setApprovalNotes('');
  };

  const handleUploadSubmit = () => {
    if (!selectedFile || !documentType) return;
    
    onUploadDocument(order.orderNumber, selectedFile, documentType);
    onUploadModalChange(false);
    setDocumentType('');
    setSelectedFile(null);
  };

  return (
    <>
      {/* IVR Generation Modal */}
      <Dialog open={showIVRModal} onOpenChange={onIVRModalChange}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Generate IVR Document</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <p className="text-sm text-muted-foreground">
              Does this order require an IVR confirmation from the manufacturer?
            </p>
            <RadioGroup value={ivrRequired} onValueChange={setIvrRequired}>
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="yes" id="ivr-yes" />
                <Label htmlFor="ivr-yes">IVR Required (default)</Label>
              </div>
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="no" id="ivr-no" />
                <Label htmlFor="ivr-no">IVR Not Required</Label>
              </div>
            </RadioGroup>
            {ivrRequired === 'no' && (
              <div className="space-y-2">
                <Label htmlFor="ivr-reason">Justification (required)</Label>
                <Textarea
                  id="ivr-reason"
                  placeholder="Please provide a reason for skipping IVR..."
                  value={ivrReason}
                  onChange={(e) => setIvrReason(e.target.value)}
                  rows={3}
                />
              </div>
            )}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => onIVRModalChange(false)}>
              Cancel
            </Button>
            <Button 
              onClick={handleIVRSubmit}
              disabled={ivrRequired === 'no' && !ivrReason.trim()}
            >
              {ivrRequired === 'yes' ? 'Generate IVR' : 'Skip IVR & Continue'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Order Approval Modal */}
      <Dialog open={showApprovalModal} onOpenChange={onApprovalModalChange}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Review Order</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label>Action</Label>
              <Select value={approvalAction} onValueChange={setApprovalAction}>
                <SelectTrigger>
                  <SelectValue placeholder="Select an action" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="approve">Approve Order</SelectItem>
                  <SelectItem value="send-back">Send Back to Provider</SelectItem>
                  <SelectItem value="deny">Deny Order</SelectItem>
                </SelectContent>
              </Select>
            </div>
            {(approvalAction === 'send-back' || approvalAction === 'deny') && (
              <div className="space-y-2">
                <Label htmlFor="approval-notes">
                  {approvalAction === 'send-back' ? 'Comments (required)' : 'Reason (required)'}
                </Label>
                <Textarea
                  id="approval-notes"
                  placeholder={`Please provide ${approvalAction === 'send-back' ? 'comments' : 'reason'}...`}
                  value={approvalNotes}
                  onChange={(e) => setApprovalNotes(e.target.value)}
                  rows={3}
                />
              </div>
            )}
            {approvalAction === 'approve' && (
              <div className="p-3 bg-green-50 border border-green-200 rounded-md">
                <p className="text-sm text-green-700">
                  This order will be approved and ready for submission to {order.manufacturerName}.
                </p>
              </div>
            )}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => onApprovalModalChange(false)}>
              Cancel
            </Button>
            <Button 
              onClick={handleApprovalSubmit}
              disabled={!approvalAction || ((approvalAction === 'send-back' || approvalAction === 'deny') && !approvalNotes.trim())}
            >
              Confirm Action
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Upload Document Modal */}
      <Dialog open={showUploadModal} onOpenChange={onUploadModalChange}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Upload Supporting Document</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label>Document Type</Label>
              <Select value={documentType} onValueChange={setDocumentType}>
                <SelectTrigger>
                  <SelectValue placeholder="Select document type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="clinical-notes">Clinical Notes</SelectItem>
                  <SelectItem value="photos">Photos</SelectItem>
                  <SelectItem value="patient-id">Patient ID Card</SelectItem>
                  <SelectItem value="ivr">IVR Form</SelectItem>
                  <SelectItem value="order-form">Order Form</SelectItem>
                  <SelectItem value="shipping-slip">Shipping Slip</SelectItem>
                  <SelectItem value="other">Other</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="file-upload">Select File</Label>
              <input
                id="file-upload"
                type="file"
                onChange={(e) => setSelectedFile(e.target.files?.[0] || null)}
                className="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-primary-foreground hover:file:bg-primary/90"
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => onUploadModalChange(false)}>
              Cancel
            </Button>
            <Button 
              onClick={handleUploadSubmit}
              disabled={!selectedFile || !documentType}
            >
              Upload Document
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
};
