import { useState, useCallback } from 'react';
import { DocumentUpload, DocumentType, UploadedFile } from '@/types/document-upload';
import axios from 'axios';

interface UseDocumentUploadProps {
  onUploadComplete?: (upload: DocumentUpload) => void;
  onInsuranceProcessed?: (data: any) => void;
}

export function useDocumentUpload({ 
  onUploadComplete, 
  onInsuranceProcessed 
}: UseDocumentUploadProps = {}) {
  const [uploads, setUploads] = useState<DocumentUpload[]>([]);
  const [isProcessing, setIsProcessing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  };

  const processInsuranceCard = async (frontFile: File, backFile?: File) => {
    setIsProcessing(true);
    setError(null);

    try {
      const formData = new FormData();
      formData.append('front', frontFile);
      if (backFile) {
        formData.append('back', backFile);
      }

      const response = await axios.post('/api/insurance-card/analyze', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });

      if (response.data.success && response.data.data) {
        onInsuranceProcessed?.(response.data.data);
      }
    } catch (err) {
      console.error('Error processing insurance card:', err);
      setError('Failed to process insurance card');
    } finally {
      setIsProcessing(false);
    }
  };

  const addUpload = useCallback(async (
    type: DocumentType, 
    files: { primary: File; secondary?: File }
  ) => {
    const uploadId = `${type}_${Date.now()}`;
    
    const newUpload: DocumentUpload = {
      id: uploadId,
      type,
      files: {
        primary: {
          name: files.primary.name,
          size: files.primary.size,
          type: files.primary.type,
          file: files.primary,
        },
      },
    };

    if (files.secondary) {
      newUpload.files.secondary = {
        name: files.secondary.name,
        size: files.secondary.size,
        type: files.secondary.type,
        file: files.secondary,
      };
    }

    setUploads(prev => [...prev, newUpload]);
    onUploadComplete?.(newUpload);

    // Process insurance cards automatically
    if (type === 'insurance_card' && files.primary) {
      await processInsuranceCard(files.primary, files.secondary);
    }

    return newUpload;
  }, [onUploadComplete, onInsuranceProcessed]);

  const removeUpload = useCallback((uploadId: string) => {
    setUploads(prev => prev.filter(upload => upload.id !== uploadId));
  }, []);

  const getUploadsByType = useCallback((type: DocumentType) => {
    return uploads.filter(upload => upload.type === type);
  }, [uploads]);

  const clearAllUploads = useCallback(() => {
    setUploads([]);
  }, []);

  return {
    uploads,
    isProcessing,
    error,
    addUpload,
    removeUpload,
    getUploadsByType,
    clearAllUploads,
    formatFileSize,
  };
}