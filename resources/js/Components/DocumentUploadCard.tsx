import { useState, useRef } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Pages/QuickRequest/Orders/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Pages/QuickRequest/Orders/ui/select';
import { FiUpload, FiFile, FiX, FiCheck, FiRefreshCw } from 'react-icons/fi';
import { cn } from '@/theme/glass-theme';
import { useTheme } from '@/contexts/ThemeContext';
import { themes } from '@/theme/glass-theme';
import { 
  DocumentType, 
  DocumentUpload, 
  DOCUMENT_TYPE_CONFIGS 
} from '@/types/document-upload';
import { useDocumentUpload } from '@/Hooks/useDocumentUpload';

interface DocumentUploadCardProps {
  onDocumentsChange?: (documents: DocumentUpload[]) => void;
  onInsuranceDataExtracted?: (data: any) => void;
  allowMultiple?: boolean;
  className?: string;
  title?: string;
  description?: string;
}

export default function DocumentUploadCard({
  onDocumentsChange,
  onInsuranceDataExtracted,
  allowMultiple = true,
  className,
  title = "Document Upload",
  description = "Upload required documents by selecting the type and clicking to upload"
}: DocumentUploadCardProps) {
  // Theme context with fallback
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme if outside ThemeProvider
  }

  const [selectedType, setSelectedType] = useState<DocumentType>('demographics');
  const [tempFiles, setTempFiles] = useState<{ primary?: File; secondary?: File }>({});
  
  const primaryInputRef = useRef<HTMLInputElement>(null);
  const secondaryInputRef = useRef<HTMLInputElement>(null);

  const {
    uploads,
    isProcessing,
    error,
    addUpload,
    removeUpload,
    formatFileSize,
  } = useDocumentUpload({
    onUploadComplete: () => {
      onDocumentsChange?.(uploads);
    },
    onInsuranceProcessed: onInsuranceDataExtracted,
  });

  const config = DOCUMENT_TYPE_CONFIGS[selectedType];
  const requiresSecondary = selectedType === 'insurance_card';

  const handleFileSelect = async (file: File, isPrimary: boolean = true) => {
    if (isPrimary) {
      setTempFiles(prev => ({ ...prev, primary: file }));
    } else {
      setTempFiles(prev => ({ ...prev, secondary: file }));
    }
  };

  const handleUploadComplete = async () => {
    if (!tempFiles.primary) return;

    if (requiresSecondary && !tempFiles.secondary) {
      return; // Wait for both files
    }

    await addUpload(selectedType, tempFiles);
    setTempFiles({});
    
    // Reset file inputs
    if (primaryInputRef.current) primaryInputRef.current.value = '';
    if (secondaryInputRef.current) secondaryInputRef.current.value = '';
  };

  // Auto-upload when both files are selected for insurance cards
  if (requiresSecondary && tempFiles.primary && tempFiles.secondary) {
    handleUploadComplete();
  } else if (!requiresSecondary && tempFiles.primary) {
    handleUploadComplete();
  }

  const renderUploadArea = (isPrimary: boolean = true) => {
    const file = isPrimary ? tempFiles.primary : tempFiles.secondary;
    const inputRef = isPrimary ? primaryInputRef : secondaryInputRef;
    const label = requiresSecondary 
      ? (isPrimary ? config.subLabels?.primary : config.subLabels?.secondary)
      : 'Click to upload';

    return (
      <div className={requiresSecondary ? '' : 'col-span-2'}>
        {requiresSecondary && (
          <label className={cn("text-sm font-medium mb-2 block", t.text.primary)}>
            {label}
          </label>
        )}
        <div
          onClick={() => inputRef.current?.click()}
          className={cn(
            "border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-all",
            theme === 'dark'
              ? 'border-gray-700 hover:border-blue-500 hover:bg-gray-800'
              : 'border-gray-300 hover:border-blue-500 hover:bg-gray-50',
            file ? 'border-green-500 bg-green-50/5' : ''
          )}
        >
          <input
            ref={inputRef}
            type="file"
            accept={config.accept}
            className="hidden"
            onChange={(e) => {
              const selectedFile = e.target.files?.[0];
              if (selectedFile) handleFileSelect(selectedFile, isPrimary);
            }}
          />
          
          {file ? (
            <div className="flex items-center justify-center">
              <FiFile className="h-8 w-8 text-green-500 mr-2" />
              <div className="text-left">
                <p className={cn("text-sm font-medium", t.text.primary)}>
                  {file.name}
                </p>
                <p className={cn("text-xs", t.text.secondary)}>
                  {formatFileSize(file.size)}
                </p>
              </div>
            </div>
          ) : (
            <div>
              <FiUpload className="mx-auto h-8 w-8 text-gray-400 mb-2" />
              <p className={cn("text-sm font-medium", t.text.primary)}>
                {!requiresSecondary ? 'Click to upload' : label}
              </p>
              <p className={cn("text-xs mt-1", t.text.secondary)}>
                {config.accept.replace(/\./g, '').toUpperCase()}
              </p>
            </div>
          )}
        </div>
      </div>
    );
  };

  return (
    <Card className={cn(t.glass.card, className)}>
      <CardHeader>
        <CardTitle className={t.text.primary}>{title}</CardTitle>
        <CardDescription className={t.text.secondary}>
          {description}
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {/* Document Type Selector */}
        <div>
          <label className={cn("text-sm font-medium mb-2 block", t.text.primary)}>
            Document Type
          </label>
          <Select value={selectedType} onValueChange={(value) => setSelectedType(value as DocumentType)}>
            <SelectTrigger className={cn(t.input.base, t.input.focus)}>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {Object.entries(DOCUMENT_TYPE_CONFIGS).map(([type, config]) => (
                <SelectItem key={type} value={type}>
                  {config.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {config.description && (
            <p className={cn("text-xs mt-1", t.text.secondary)}>
              {config.description}
            </p>
          )}
        </div>

        {/* Upload Areas */}
        <div className={cn(
          "grid gap-4",
          requiresSecondary ? "grid-cols-2" : "grid-cols-1"
        )}>
          {renderUploadArea(true)}
          {requiresSecondary && renderUploadArea(false)}
        </div>

        {/* Processing Indicator */}
        {isProcessing && (
          <div className="flex items-center justify-center py-2">
            <FiRefreshCw className="animate-spin h-5 w-5 mr-2 text-blue-500" />
            <span className={cn("text-sm", t.text.secondary)}>
              Processing document...
            </span>
          </div>
        )}

        {/* Error Display */}
        {error && (
          <div className={cn(
            "p-3 rounded-lg",
            theme === 'dark' ? 'bg-red-900/20 text-red-400' : 'bg-red-50 text-red-700'
          )}>
            <p className="text-sm">{error}</p>
          </div>
        )}

        {/* Uploaded Documents List */}
        {uploads.length > 0 && (
          <div className="space-y-2">
            <h4 className={cn("text-sm font-medium", t.text.primary)}>
              Uploaded Documents ({uploads.length})
            </h4>
            {uploads.map((upload) => (
              <div
                key={upload.id}
                className={cn(
                  "flex items-center justify-between p-3 rounded-lg",
                  theme === 'dark' ? 'bg-gray-800' : 'bg-gray-100'
                )}
              >
                <div className="flex items-center space-x-3">
                  <FiCheck className="h-5 w-5 text-green-500" />
                  <div>
                    <p className={cn("text-sm font-medium", t.text.primary)}>
                      {DOCUMENT_TYPE_CONFIGS[upload.type].label}
                    </p>
                    <p className={cn("text-xs", t.text.secondary)}>
                      {upload.files.primary?.name}
                      {upload.files.secondary && ` + ${upload.files.secondary.name}`}
                    </p>
                  </div>
                </div>
                {allowMultiple && (
                  <button
                    onClick={() => removeUpload(upload.id)}
                    className="text-red-500 hover:text-red-700 transition-colors"
                  >
                    <FiX className="h-5 w-5" />
                  </button>
                )}
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}