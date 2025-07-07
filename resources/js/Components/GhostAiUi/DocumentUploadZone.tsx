import React, { useState, useCallback } from 'react';
import { Upload, FileText, Image, X } from 'lucide-react';
import { cn } from '@/theme/glass-theme';
import { Button } from './ui/button';

interface UploadedDocument {
  id: string;
  file: File;
  type: 'insurance_card' | 'clinical_note' | 'wound_photo' | 'prescription' | 'other';
  preview?: string;
  status: 'pending' | 'processing' | 'completed' | 'error';
  extractedData?: any;
}

interface DocumentUploadZoneProps {
  onDocumentsUploaded: (documents: UploadedDocument[]) => void;
  acceptedTypes?: string;
  maxFiles?: number;
}

const DocumentUploadZone: React.FC<DocumentUploadZoneProps> = ({
  onDocumentsUploaded,
  acceptedTypes = 'image/*,application/pdf',
  maxFiles = 10
}) => {
  const [uploadedDocs, setUploadedDocs] = useState<UploadedDocument[]>([]);
  const [isDragging, setIsDragging] = useState(false);

  const detectDocumentType = (file: File): UploadedDocument['type'] => {
    const fileName = file.name.toLowerCase();
    
    if (fileName.includes('insurance') || fileName.includes('card')) {
      return 'insurance_card';
    } else if (fileName.includes('clinical') || fileName.includes('note') || fileName.includes('referral')) {
      return 'clinical_note';
    } else if (fileName.includes('wound') || fileName.includes('ulcer')) {
      return 'wound_photo';
    } else if (fileName.includes('prescription') || fileName.includes('rx')) {
      return 'prescription';
    }
    
    // Check by file type
    if (file.type.startsWith('image/')) {
      return 'wound_photo';
    } else if (file.type === 'application/pdf') {
      return 'clinical_note';
    }
    
    return 'other';
  };

  const createPreview = (file: File): Promise<string | undefined> => {
    return new Promise((resolve) => {
      if (!file.type.startsWith('image/')) {
        resolve(undefined);
        return;
      }

      const reader = new FileReader();
      reader.onloadend = () => {
        resolve(reader.result as string);
      };
      reader.readAsDataURL(file);
    });
  };

  const handleFiles = useCallback(async (files: FileList) => {
    const newDocs: UploadedDocument[] = [];
    
    for (let i = 0; i < Math.min(files.length, maxFiles - uploadedDocs.length); i++) {
      const file = files[i];
      const preview = await createPreview(file);
      
      const doc: UploadedDocument = {
        id: `doc-${Date.now()}-${i}`,
        file,
        type: detectDocumentType(file),
        preview,
        status: 'pending'
      };
      
      newDocs.push(doc);
    }
    
    const updatedDocs = [...uploadedDocs, ...newDocs];
    setUploadedDocs(updatedDocs);
    onDocumentsUploaded(updatedDocs);
  }, [uploadedDocs, maxFiles, onDocumentsUploaded]);

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
    
    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      handleFiles(e.dataTransfer.files);
    }
  }, [handleFiles]);

  const handleDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(true);
  }, []);

  const handleDragLeave = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
  }, []);

  const handleFileInput = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      handleFiles(e.target.files);
    }
  }, [handleFiles]);

  const removeDocument = (docId: string) => {
    const updatedDocs = uploadedDocs.filter(doc => doc.id !== docId);
    setUploadedDocs(updatedDocs);
    onDocumentsUploaded(updatedDocs);
  };

  const getDocumentIcon = (type: UploadedDocument['type']) => {
    switch (type) {
      case 'insurance_card':
        return '💳';
      case 'clinical_note':
        return '📋';
      case 'wound_photo':
        return '📸';
      case 'prescription':
        return '💊';
      default:
        return '📄';
    }
  };

  const getDocumentLabel = (type: UploadedDocument['type']) => {
    switch (type) {
      case 'insurance_card':
        return 'Insurance Card';
      case 'clinical_note':
        return 'Clinical Note';
      case 'wound_photo':
        return 'Wound Photo';
      case 'prescription':
        return 'Prescription';
      default:
        return 'Document';
    }
  };

  return (
    <div className="space-y-4">
      {/* Drop Zone */}
      <div
        onDrop={handleDrop}
        onDragOver={handleDragOver}
        onDragLeave={handleDragLeave}
        className={cn(
          "relative border-2 border-dashed rounded-2xl p-8 text-center transition-all duration-200",
          isDragging 
            ? "border-msc-blue-500 bg-msc-blue-50/10" 
            : "border-gray-300 hover:border-gray-400",
          "cursor-pointer"
        )}
      >
        <input
          type="file"
          id="document-upload"
          className="hidden"
          accept={acceptedTypes}
          multiple
          onChange={handleFileInput}
        />
        
        <label htmlFor="document-upload" className="cursor-pointer">
          <Upload className={cn(
            "mx-auto h-12 w-12 mb-4 transition-colors",
            isDragging ? "text-msc-blue-500" : "text-gray-400"
          )} />
          
          <p className="text-lg font-medium text-gray-700 mb-2">
            Drop documents here or click to upload
          </p>
          
          <p className="text-sm text-gray-500">
            Upload insurance cards, clinical notes, wound photos, or prescriptions
          </p>
          
          <p className="text-xs text-gray-400 mt-2">
            Supported formats: JPG, PNG, PDF • Max {maxFiles} files
          </p>
        </label>
      </div>

      {/* Uploaded Documents */}
      {uploadedDocs.length > 0 && (
        <div className="space-y-3">
          <h4 className="text-sm font-medium text-gray-700">Uploaded Documents</h4>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            {uploadedDocs.map((doc) => (
              <div
                key={doc.id}
                className="flex items-center space-x-3 p-3 bg-white/50 backdrop-blur-sm rounded-lg border border-gray-200"
              >
                {/* Preview or Icon */}
                <div className="flex-shrink-0">
                  {doc.preview ? (
                    <img
                      src={doc.preview}
                      alt={doc.file.name}
                      className="w-12 h-12 rounded-lg object-cover"
                    />
                  ) : (
                    <div className="w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center text-2xl">
                      {getDocumentIcon(doc.type)}
                    </div>
                  )}
                </div>

                {/* Document Info */}
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-gray-900 truncate">
                    {doc.file.name}
                  </p>
                  <p className="text-xs text-gray-500">
                    {getDocumentLabel(doc.type)} • {(doc.file.size / 1024).toFixed(1)}KB
                  </p>
                  
                  {/* Status */}
                  <div className="mt-1">
                    {doc.status === 'pending' && (
                      <span className="text-xs text-gray-500">Ready to process</span>
                    )}
                    {doc.status === 'processing' && (
                      <span className="text-xs text-blue-600">Processing...</span>
                    )}
                    {doc.status === 'completed' && (
                      <span className="text-xs text-green-600">✓ Processed</span>
                    )}
                    {doc.status === 'error' && (
                      <span className="text-xs text-red-600">Error processing</span>
                    )}
                  </div>
                </div>

                {/* Remove Button */}
                <Button
                  size="sm"
                  variant="ghost"
                  onClick={() => removeDocument(doc.id)}
                  className="flex-shrink-0"
                >
                  <X className="h-4 w-4" />
                </Button>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};

export default DocumentUploadZone;