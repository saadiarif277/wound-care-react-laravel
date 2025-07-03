import React, { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/Button';
import { Badge } from '@/Components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/Components/ui/dialog';
import { Textarea } from '@/Components/ui/textarea';
import { Label } from '@/Components/ui/label';
import { FileText, Upload, Eye } from 'lucide-react';

interface IVRSectionProps {
  ivrData: {
    status: string;
    sentDate?: string;
    resultsReceivedDate?: string;
    verifiedDate?: string;
    notes?: string;
    resultsFileUrl?: string;
  };
  onUpdateIVRStatus: (status: string, notes?: string) => void;
  onUploadIVRResults: (file: File) => void;
  onGenerateIVR: () => void;
}

const IVRSection: React.FC<IVRSectionProps> = ({
  ivrData,
  onUpdateIVRStatus,
  onUploadIVRResults,
  onGenerateIVR
}) => {
  const [showStatusModal, setShowStatusModal] = useState(false);
  const [showUploadModal, setShowUploadModal] = useState(false);
  const [newStatus, setNewStatus] = useState<string>(ivrData.status);
  const [notes, setNotes] = useState('');
  const [selectedFile, setSelectedFile] = useState<File | null>(null);

  const getStatusColor = (status: string): string => {
    switch (status) {
      case 'Not Required': return 'bg-gray-100 text-gray-800';
      case 'Pending': return 'bg-yellow-100 text-yellow-800';
      case 'Sent': return 'bg-blue-100 text-blue-800';
      case 'Results Received': return 'bg-purple-100 text-purple-800';
      case 'Verified': return 'bg-green-100 text-green-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const handleStatusUpdate = () => {
    onUpdateIVRStatus(newStatus, notes);
    setShowStatusModal(false);
    setNotes('');
  };

  const handleUploadResults = () => {
    if (selectedFile) {
      onUploadIVRResults(selectedFile);
      setShowUploadModal(false);
      setSelectedFile(null);
    }
  };

  return (
    <>
      <Card className="my-6">
        <CardHeader>
          <CardTitle className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <FileText className="h-5 w-5" />
              IVR Management
            </div>
            <Badge className={getStatusColor(ivrData.status)}>
              {ivrData.status}
            </Badge>
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-2 gap-4 text-sm">
            {ivrData.sentDate && (
              <div>
                <span className="font-medium">Sent Date:</span>
                <div>{ivrData.sentDate}</div>
              </div>
            )}
            {ivrData.resultsReceivedDate && (
              <div>
                <span className="font-medium">Results Received:</span>
                <div>{ivrData.resultsReceivedDate}</div>
              </div>
            )}
            {ivrData.verifiedDate && (
              <div>
                <span className="font-medium">Verified Date:</span>
                <div>{ivrData.verifiedDate}</div>
              </div>
            )}
          </div>

          {ivrData.notes && (
            <div className="p-3 bg-muted/50 rounded-lg">
              <span className="font-medium text-sm">Notes:</span>
              <p className="text-sm mt-1">{ivrData.notes}</p>
            </div>
          )}

          <div className="flex gap-2 flex-wrap">
            {ivrData.status === 'Pending' && (
              <Button onClick={onGenerateIVR} size="sm">
                Generate IVR
              </Button>
            )}

            <Button variant="secondary" onClick={() => setShowStatusModal(true)} size="sm">
              Update Status
            </Button>

            {(ivrData.status === 'Sent' || ivrData.status === 'Results Received') && (
              <Button variant="secondary" onClick={() => setShowUploadModal(true)} size="sm">
                <Upload className="h-4 w-4 mr-2" />
                Upload Results
              </Button>
            )}

            {ivrData.resultsFileUrl && (
              <Button variant="secondary" size="sm">
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
              <select
                value={newStatus}
                onChange={(e) => setNewStatus(e.target.value)}
                className="w-full border rounded p-2"
              >
                <option value="Not Required">Not Required</option>
                <option value="Pending">Pending</option>
                <option value="Sent">Sent</option>
                <option value="Results Received">Results Received</option>
                <option value="Verified">Verified</option>
              </select>
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
            <Button variant="secondary" onClick={() => setShowStatusModal(false)}>
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
            <Button variant="secondary" onClick={() => setShowUploadModal(false)}>
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

export default IVRSection;
