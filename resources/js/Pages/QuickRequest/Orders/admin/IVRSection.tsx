
import React, { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { Button } from '../ui/button';
import { Badge } from '../ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '../ui/dialog';
import { Textarea } from '../ui/textarea';
import { Label } from '../ui/label';
import { AdminOrderData, IVRStatus } from '../types/adminTypes';
import { FileText, Upload, Eye } from 'lucide-react';

interface IVRSectionProps {
  order: AdminOrderData;
  onUpdateIVRStatus: (orderNumber: string, status: IVRStatus, notes?: string) => void;
  onUploadIVRResults: (orderNumber: string, file: File) => void;
  onGenerateIVR: (orderNumber: string) => void;
}

export const IVRSection: React.FC<IVRSectionProps> = ({
  order,
  onUpdateIVRStatus,
  onUploadIVRResults,
  onGenerateIVR
}) => {
  const [showStatusModal, setShowStatusModal] = useState(false);
  const [showUploadModal, setShowUploadModal] = useState(false);
  const [newStatus, setNewStatus] = useState<IVRStatus>(order.ivrData.status);
  const [notes, setNotes] = useState('');
  const [selectedFile, setSelectedFile] = useState<File | null>(null);

  const getStatusColor = (status: IVRStatus): string => {
    switch (status) {
      case "Pending IVR": return 'bg-yellow-100 text-yellow-800';
      case "IVR Sent": return 'bg-blue-100 text-blue-800';
      case "IVR Verified": return 'bg-purple-100 text-purple-800';
      // (Remove this line, as "Approved" is not a valid IVRStatus anymore)
     // case "Denied": return 'bg-red-100 text-red-800';
      // case "Send Back": return 'bg-orange-100 text-orange-800';
    //  case "Submitted to Manufacturer": return 'bg-indigo-100 text-indigo-800';
      case "N/A": return 'bg-gray-100 text-gray-800';
      case "Rejected": return 'bg-red-100 text-red-800';
      default: return '';
    }
  };

  const handleStatusUpdate = () => {
    onUpdateIVRStatus(order.orderNumber, newStatus, notes);
    setShowStatusModal(false);
    setNotes('');
  };

  const handleUploadResults = () => {
    if (selectedFile) {
      onUploadIVRResults(order.orderNumber, selectedFile);
      setShowUploadModal(false);
      setSelectedFile(null);
    }
  };

  return (
    <>
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <FileText className="h-5 w-5" />
              IVR Management
            </div>
            <Badge className={getStatusColor(order.ivrData.status)}>
              {order.ivrData.status}
            </Badge>
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-2 gap-4 text-sm">
            {order.ivrData.sentDate && (
              <div>
                <span className="font-medium">Sent Date:</span>
                <div>{order.ivrData.sentDate}</div>
              </div>
            )}
            {order.ivrData.resultsReceivedDate && (
              <div>
                <span className="font-medium">Results Received:</span>
                <div>{order.ivrData.resultsReceivedDate}</div>
              </div>
            )}
            {order.ivrData.verifiedDate && (
              <div>
                <span className="font-medium">Verified Date:</span>
                <div>{order.ivrData.verifiedDate}</div>
              </div>
            )}
          </div>

          {order.ivrData.notes && (
            <div className="p-3 bg-muted/50 rounded-lg">
              <span className="font-medium text-sm">Notes:</span>
              <p className="text-sm mt-1">{order.ivrData.notes}</p>
            </div>
          )}

          <div className="flex gap-2 flex-wrap">
            {order.ivrData.status === ('Pending' as IVRStatus) && (
              <Button onClick={() => onGenerateIVR(order.orderNumber)} size="sm">
                Generate IVR
              </Button>
            )}

            <Button variant="outline" onClick={() => setShowStatusModal(true)} size="sm">
              Update Status
            </Button>

            {(order.ivrData.status === ('Sent' as IVRStatus) || order.ivrData.status === ('Results Received' as IVRStatus)) && (
              <Button variant="outline" onClick={() => setShowUploadModal(true)} size="sm">
                <Upload className="h-4 w-4 mr-2" />
                Upload Results
              </Button>
            )}

            {order.ivrData.resultsFileUrl && (
              <Button variant="outline" size="sm">
                <Eye className="h-4 w-4 mr-2" />
                View Results
              </Button>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Status Update Modal */}
      <Dialog open={showStatusModal} onOpenChange={setShowStatusModal}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Update IVR Status</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label>New Status</Label>
              <Select value={newStatus} onValueChange={(value) => setNewStatus(value as IVRStatus)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="Not Required">Not Required</SelectItem>
                  <SelectItem value="Pending">Pending</SelectItem>
                  <SelectItem value="Sent">Sent</SelectItem>
                  <SelectItem value="Results Received">Results Received</SelectItem>
                  <SelectItem value="Verified">Verified</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="status-notes">Notes (optional)</Label>
              <Textarea
                id="status-notes"
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

      {/* Upload Results Modal */}
      <Dialog open={showUploadModal} onOpenChange={setShowUploadModal}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Upload IVR Results</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="ivr-file-upload">Select IVR Results File</Label>
              <input
                id="ivr-file-upload"
                type="file"
                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                onChange={(e) => setSelectedFile(e.target.files?.[0] || null)}
                className="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-primary-foreground hover:file:bg-primary/90"
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowUploadModal(false)}>
              Cancel
            </Button>
            <Button onClick={handleUploadResults} disabled={!selectedFile}>
              Upload Results
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
};
