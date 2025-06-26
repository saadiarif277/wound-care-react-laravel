
import React from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Textarea } from '@/components/ui/textarea';

interface OrderModalsProps {
  showSubmitModal: boolean;
  showSuccessModal: boolean;
  showNoteModal: boolean;
  confirmationChecked: boolean;
  adminNote: string;
  onSubmitModalChange: (open: boolean) => void;
  onSuccessModalChange: (open: boolean) => void;
  onNoteModalChange: (open: boolean) => void;
  onConfirmationChange: (checked: boolean) => void;
  onAdminNoteChange: (note: string) => void;
  onConfirmSubmission: () => void;
  onAddNote: () => void;
  onFinishSubmission: () => void;
}

export const OrderModals: React.FC<OrderModalsProps> = ({
  showSubmitModal,
  showSuccessModal,
  showNoteModal,
  confirmationChecked,
  adminNote,
  onSubmitModalChange,
  onSuccessModalChange,
  onNoteModalChange,
  onConfirmationChange,
  onAdminNoteChange,
  onConfirmSubmission,
  onAddNote,
  onFinishSubmission
}) => (
  <>
    {/* Submit Confirmation Modal */}
    <Dialog open={showSubmitModal} onOpenChange={onSubmitModalChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>Confirm Order Submission</DialogTitle>
        </DialogHeader>
        <div className="space-y-4">
          <p className="text-sm text-muted-foreground">
            By submitting this order, I consent to having the IVR form and Order form submitted to the 
            manufacturer for review and approval. I understand that the order will not be placed with 
            the manufacturer until IVR verification is completed and the order is fully approved.
          </p>
          <div className="flex items-center space-x-2">
            <Checkbox 
              id="confirmation" 
              checked={confirmationChecked}
              onCheckedChange={(checked) => onConfirmationChange(!!checked)}
            />
            <label htmlFor="confirmation" className="text-sm">
              I confirm the information is accurate and complete.
            </label>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onSubmitModalChange(false)}>
            Go Back
          </Button>
          <Button onClick={onConfirmSubmission} disabled={!confirmationChecked}>
            Place Order
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    {/* Success Modal */}
    <Dialog open={showSuccessModal} onOpenChange={onSuccessModalChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            Order Submitted Successfully
          </DialogTitle>
        </DialogHeader>
        <div className="space-y-4">
          <p className="text-sm text-muted-foreground">
            Your order has been submitted to Admin for review and processing. You will be 
            notified once the order is fully approved and sent to the manufacturer.
          </p>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onAddNote}>
            Add Note for Admin
          </Button>
          <Button onClick={onFinishSubmission}>
            Okay
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    {/* Add Note Modal */}
    <Dialog open={showNoteModal} onOpenChange={onNoteModalChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>Add Note for Admin</DialogTitle>
        </DialogHeader>
        <div className="space-y-4">
          <Textarea
            placeholder="Add any special instructions or notes for the admin team..."
            value={adminNote}
            onChange={(e) => onAdminNoteChange(e.target.value)}
            rows={4}
          />
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onFinishSubmission}>
            Skip
          </Button>
          <Button onClick={onFinishSubmission}>
            Add Note & Continue
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </>
);
