import React from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/Components/ui/dialog';
import { Button } from '../ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Textarea } from '@/Components/ui/textarea';
import ConfirmationModal from '@/Components/ConfirmationModal';

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
    <ConfirmationModal
      isOpen={showSubmitModal}
      onClose={() => onSubmitModalChange(false)}
      onConfirm={(comment) => {
        // Handle the comment and then call the original confirmation
        if (comment) {
          onAdminNoteChange(comment);
        }
        onConfirmSubmission();
      }}
      title="Confirm Order Submission"
      message="By submitting this order, I consent to having the Order submitted to Admin for review and approval. I understand that the order will not be placed with the manufacturer until IVR verification (if required) is completed and the order is fully approved."
      confirmText="Confirm"
      cancelText="Go Back"
      showComment={true}
      maxCommentLength={500}
    />

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
          <Button variant="secondary" onClick={onAddNote}>
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
          <Button variant="secondary" onClick={onFinishSubmission}>
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
