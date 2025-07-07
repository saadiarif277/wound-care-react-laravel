
import React from 'react';
import { Button } from '@/Components/GhostAiUi/ui/button';
import { Upload } from 'lucide-react';

interface ActionButtonsProps {
  isRecordingClinicalNotes: boolean;
  onProductRequest: () => void;
  onClinicalNotes: () => void;
  onDocumentUpload?: () => void;
  showDocumentUpload?: boolean;
}

const ActionButtons: React.FC<ActionButtonsProps> = ({
  isRecordingClinicalNotes,
  onProductRequest,
  onClinicalNotes,
  onDocumentUpload,
  showDocumentUpload
}) => {
  return (
    <div className="mt-4 flex justify-center space-x-3">
      <Button
        onClick={onProductRequest}
        className="bg-white/90 text-gray-700 hover:bg-white border border-gray-200 rounded-2xl px-8 py-3 text-sm font-medium transition-all duration-200 shadow-sm hover:shadow-md"
        variant="ghost"
      >
        New Product Request
      </Button>

      <Button
        onClick={onClinicalNotes}
        className={`rounded-2xl px-8 py-3 text-sm font-medium transition-all duration-200 ${
          isRecordingClinicalNotes
            ? 'bg-red-500 text-white hover:bg-red-600 animate-pulse shadow-md'
            : 'bg-white/90 text-gray-700 hover:bg-white border border-gray-200 shadow-sm hover:shadow-md'
        }`}
        variant="ghost"
      >
        {isRecordingClinicalNotes ? 'Stop Recording' : 'Record Clinical Notes'}
      </Button>

      {onDocumentUpload && (
        <Button
          onClick={onDocumentUpload}
          className={`rounded-2xl px-8 py-3 text-sm font-medium transition-all duration-200 flex items-center gap-2 ${
            showDocumentUpload
              ? 'bg-msc-blue-500 text-white hover:bg-msc-blue-600 shadow-md'
              : 'bg-white/90 text-gray-700 hover:bg-white border border-gray-200 shadow-sm hover:shadow-md'
          }`}
          variant="ghost"
        >
          <Upload className="h-4 w-4" />
          Upload Documents
        </Button>
      )}
    </div>
  );
};

export default ActionButtons;
