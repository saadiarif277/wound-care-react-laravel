import React, { useState, useRef, useCallback } from 'react';
import { Upload, X, FileText, Image, File, CheckCircle, AlertCircle, Trash2, Eye } from 'lucide-react';
import { Button } from '@/Components/Button';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface UploadedFile {
  id: string;
  file: File;
  name: string;
  size: number;
  type: string;
  preview?: string;
  uploadedAt: Date;
}

interface MultiFileUploadProps {
  title: string;
  description?: string;
  accept?: string;
  maxFiles?: number;
  maxFileSize?: number; // in bytes
  onFilesChange: (files: UploadedFile[]) => void;
  onFileRemove?: (fileId: string) => void;
  className?: string;
  disabled?: boolean;
  showPreview?: boolean;
}

export default function MultiFileUpload({
  title,
  description = "Drag and drop files here or click to browse",
  accept = ".pdf,.doc,.docx,.jpg,.jpeg,.png",
  maxFiles = 10,
  maxFileSize = 10 * 1024 * 1024, // 10MB default
  onFilesChange,
  onFileRemove,
  className,
  disabled = false,
  showPreview = true
}: MultiFileUploadProps) {
  // Theme setup
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme
  }

  const [files, setFiles] = useState<UploadedFile[]>([]);
  const [isDragActive, setIsDragActive] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  const getFileIcon = (fileType: string) => {
    if (fileType.startsWith('image/')) return Image;
    if (fileType === 'application/pdf') return FileText;
    return File;
  };

  const validateFile = (file: File): string | null => {
    // Check file size
    if (file.size > maxFileSize) {
      return `File size exceeds ${formatFileSize(maxFileSize)} limit`;
    }

    // Check file count
    if (files.length >= maxFiles) {
      return `Maximum ${maxFiles} files allowed`;
    }

    // Check file type
    const acceptedTypes = accept.split(',').map(type => type.trim());
    const fileExtension = '.' + file.name.split('.').pop()?.toLowerCase();
    const fileType = file.type;

    const isAccepted = acceptedTypes.some(type => {
      if (type.startsWith('.')) {
        return fileExtension === type.toLowerCase();
      }
      if (type.includes('/*')) {
        const baseType = type.split('/')[0];
        return fileType.startsWith(baseType + '/');
      }
      return fileType === type;
    });

    if (!isAccepted) {
      return `File type not supported. Accepted types: ${accept}`;
    }

    return null;
  };

  const createFilePreview = async (file: File): Promise<string | undefined> => {
    if (file.type.startsWith('image/')) {
      return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = (e) => resolve(e.target?.result as string);
        reader.readAsDataURL(file);
      });
    }
    return undefined;
  };

  const addFiles = useCallback(async (newFiles: FileList) => {
    setError(null);
    const validFiles: UploadedFile[] = [];

    for (const file of Array.from(newFiles)) {
      const validationError = validateFile(file);
      if (validationError) {
        setError(validationError);
        continue;
      }

      const preview = await createFilePreview(file);
      const uploadedFile: UploadedFile = {
        id: `${Date.now()}-${Math.random()}`,
        file,
        name: file.name,
        size: file.size,
        type: file.type,
        preview,
        uploadedAt: new Date()
      };

      validFiles.push(uploadedFile);
    }

    if (validFiles.length > 0) {
      const updatedFiles = [...files, ...validFiles];
      setFiles(updatedFiles);
      onFilesChange(updatedFiles);
    }
  }, [files, maxFiles, maxFileSize, accept, onFilesChange]);

  const removeFile = useCallback((fileId: string) => {
    const updatedFiles = files.filter(f => f.id !== fileId);
    setFiles(updatedFiles);
    onFilesChange(updatedFiles);
    onFileRemove?.(fileId);
  }, [files, onFilesChange, onFileRemove]);

  const handleFileInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const selectedFiles = e.target.files;
    if (selectedFiles) {
      addFiles(selectedFiles);
    }
    // Reset input value to allow selecting the same file again
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  const handleDragEnter = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragActive(true);
  };

  const handleDragLeave = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragActive(false);
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragActive(false);

    const droppedFiles = e.dataTransfer.files;
    if (droppedFiles.length > 0) {
      addFiles(droppedFiles);
    }
  };

  const handleClick = () => {
    if (!disabled && fileInputRef.current) {
      fileInputRef.current.click();
    }
  };

  return (
    <div className={cn("space-y-4", className)}>
      {/* Upload Area */}
      <div
        className={cn(
          "border-2 border-dashed rounded-lg p-6 transition-all duration-200",
          isDragActive
            ? "border-blue-500 bg-blue-50 dark:bg-blue-900/20"
            : "border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500",
          disabled && "opacity-50 cursor-not-allowed",
          !disabled && "cursor-pointer"
        )}
        onDragEnter={handleDragEnter}
        onDragLeave={handleDragLeave}
        onDragOver={handleDragOver}
        onDrop={handleDrop}
        onClick={handleClick}
      >
        <div className="text-center">
          <Upload className={cn(
            "w-8 h-8 mx-auto mb-2",
            isDragActive ? "text-blue-500" : "text-gray-400"
          )} />
          <h3 className={cn("text-sm font-medium mb-1", t.text.primary)}>
            {title}
          </h3>
          <p className={cn("text-xs", t.text.secondary)}>
            {description}
          </p>
          <p className={cn("text-xs mt-1", t.text.muted)}>
            Max {maxFiles} files, {formatFileSize(maxFileSize)} each
          </p>
        </div>
      </div>

      {/* Error Display */}
      {error && (
        <div className="flex items-center gap-2 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
          <AlertCircle className="w-4 h-4 text-red-500" />
          <span className={cn("text-sm", t.status.error)}>{error}</span>
        </div>
      )}

      {/* File List */}
      {files.length > 0 && (
        <div className="space-y-2">
          <h4 className={cn("text-sm font-medium", t.text.primary)}>
            Uploaded Files ({files.length}/{maxFiles})
          </h4>
          <div className="space-y-2">
            {files.map((file) => {
              const FileIcon = getFileIcon(file.type);
              return (
                <div
                  key={file.id}
                  className={cn(
                    "flex items-center justify-between p-3 rounded-lg border",
                    "bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700"
                  )}
                >
                  <div className="flex items-center gap-3 flex-1 min-w-0">
                    <FileIcon className="w-5 h-5 text-gray-400 flex-shrink-0" />
                    <div className="flex-1 min-w-0">
                      <p className={cn("text-sm font-medium truncate", t.text.primary)}>
                        {file.name}
                      </p>
                      <p className={cn("text-xs", t.text.secondary)}>
                        {formatFileSize(file.size)}
                      </p>
                    </div>
                  </div>

                  <div className="flex items-center gap-2">
                    {showPreview && file.preview && (
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                          // Open preview in new window/tab
                          const w = window.open();
                          if (w) {
                            w.document.write(`
                              <html>
                                <head><title>${file.name}</title></head>
                                <body style="margin:0;padding:20px;text-align:center;">
                                  <img src="${file.preview}" style="max-width:100%;max-height:80vh;" />
                                </body>
                              </html>
                            `);
                          }
                        }}
                      >
                        <Eye className="w-4 h-4" />
                      </Button>
                    )}
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => removeFile(file.id)}
                      className="text-red-500 hover:text-red-700"
                    >
                      <Trash2 className="w-4 h-4" />
                    </Button>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Hidden File Input */}
      <input
        ref={fileInputRef}
        type="file"
        multiple
        accept={accept}
        onChange={handleFileInputChange}
        className="hidden"
        disabled={disabled}
      />
    </div>
  );
}
