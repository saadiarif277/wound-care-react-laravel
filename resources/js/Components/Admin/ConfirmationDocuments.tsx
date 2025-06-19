import React, { useState, useRef } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { 
  DocumentIcon,
  DocumentCheckIcon,
  DocumentTextIcon,
  ArrowDownTrayIcon,
  ArrowUpTrayIcon,
  EyeIcon,
  TrashIcon,
  CloudArrowUpIcon,
  CheckCircleIcon,
  XCircleIcon,
  ShieldCheckIcon,
  SparklesIcon,
  MicrophoneIcon
} from '@heroicons/react/24/outline';
import { themes } from '@/theme/glass-theme';
import { useTheme } from '@/contexts/ThemeContext';
import { useForm } from '@inertiajs/react';

interface Document {
  id: string;
  name?: string;
  file_name?: string;
  url: string;
  type?: string;
  size?: number;
  uploaded_at?: string;
  verified?: boolean;
  ai_insights?: {
    completeness: number;
    issues: string[];
  };
}

interface ConfirmationDocumentsProps {
  documents?: Document[];
  readOnly?: boolean;
  orderId?: string;
}

const documentIcons = {
  pdf: DocumentTextIcon,
  doc: DocumentIcon,
  verified: DocumentCheckIcon,
  default: DocumentIcon
};

const ConfirmationDocuments = ({ documents = [], readOnly = false, orderId }: ConfirmationDocumentsProps) => {
  let theme: 'dark' | 'light' = 'dark';
  let t = themes.dark;

  try {
    const themeContext = useTheme();
    theme = themeContext.theme;
    t = themes[theme];
  } catch (e) {
    // Fallback to dark theme
  }

  const [isUploading, setIsUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [dragActive, setDragActive] = useState(false);
  const [selectedDoc, setSelectedDoc] = useState<Document | null>(null);
  const [voiceEnabled, setVoiceEnabled] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const { post, delete: destroy } = useForm();

  const handleVoiceStatus = () => {
    if ('speechSynthesis' in window) {
      const docCount = documents.length;
      const verifiedCount = documents.filter(d => d.verified).length;
      const utterance = new SpeechSynthesisUtterance(
        `You have ${docCount} document${docCount !== 1 ? 's' : ''} uploaded. ${
          verifiedCount > 0 ? `${verifiedCount} verified.` : ''
        }`
      );
      speechSynthesis.speak(utterance);
    }
  };

  const handleDrag = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    if (e.type === "dragenter" || e.type === "dragover") {
      setDragActive(true);
    } else if (e.type === "dragleave") {
      setDragActive(false);
    }
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setDragActive(false);
    
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      handleFileUpload(e.dataTransfer.files);
    }
  };

  const handleFileUpload = async (files: FileList) => {
    setIsUploading(true);
    setUploadProgress(0);

    // Simulate upload progress
    const interval = setInterval(() => {
      setUploadProgress(prev => {
        if (prev >= 90) {
          clearInterval(interval);
          return 90;
        }
        return prev + 10;
      });
    }, 200);

    // Here you would actually upload the files
    const formData = new FormData();
    Array.from(files).forEach(file => {
      formData.append('documents[]', file);
    });

    if (orderId) {
      post(route('orders.documents.upload', orderId), {
        data: formData,
        onSuccess: () => {
          setUploadProgress(100);
          setTimeout(() => {
            setIsUploading(false);
            setUploadProgress(0);
          }, 1000);
        },
        onError: () => {
          clearInterval(interval);
          setIsUploading(false);
          setUploadProgress(0);
        }
      });
    }
  };

  const handleDelete = (docId: string) => {
    if (orderId) {
      destroy(route('orders.documents.delete', { order: orderId, document: docId }));
    }
  };

  const getDocumentIcon = (doc: Document) => {
    if (doc.verified) return documentIcons.verified;
    if (doc.type?.includes('pdf')) return documentIcons.pdf;
    return documentIcons.default;
  };

  const formatFileSize = (bytes?: number) => {
    if (!bytes) return 'Unknown size';
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
  };

  return (
    <div className={`${t.glass.card} ${t.glass.border} rounded-2xl p-6`}>
      {/* Header with Voice Control */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <DocumentCheckIcon className="w-5 h-5 text-green-500" />
          <h3 className={`${t.text.primary} text-lg font-semibold`}>Confirmation Documents</h3>
        </div>
        <div className="flex items-center gap-2">
          {documents.some(d => d.verified) && (
            <div className="flex items-center gap-2 px-3 py-1 bg-green-500/20 rounded-full">
              <ShieldCheckIcon className="w-4 h-4 text-green-500" />
              <span className="text-xs text-green-600 dark:text-green-400">Verified</span>
            </div>
          )}
          <button
            onClick={() => {
              setVoiceEnabled(!voiceEnabled);
              if (!voiceEnabled) handleVoiceStatus();
            }}
            className={`p-2 rounded-lg transition-all ${
              voiceEnabled 
                ? 'bg-purple-500/20 text-purple-600 dark:text-purple-400' 
                : 'hover:bg-white/5'
            }`}
            aria-label="Toggle voice announcements"
          >
            <MicrophoneIcon className="w-5 h-5" />
          </button>
        </div>
      </div>

      {documents.length === 0 && !isUploading ? (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          className="text-center py-12"
        >
          <DocumentIcon className={`w-16 h-16 mx-auto ${t.text.muted} mb-4`} />
          <p className={`${t.text.muted} mb-4`}>No confirmation documents uploaded yet</p>
          {!readOnly && (
            <button
              onClick={() => fileInputRef.current?.click()}
              className={`px-4 py-2 ${t.button.primary} rounded-lg`}
            >
              Upload Documents
            </button>
          )}
        </motion.div>
      ) : (
        <div className="space-y-3">
          {/* Document List */}
          <AnimatePresence>
            {documents.map((doc, idx) => {
              const Icon = getDocumentIcon(doc);
              return (
                <motion.div
                  key={doc.id || idx}
                  initial={{ opacity: 0, x: -20 }}
                  animate={{ opacity: 1, x: 0 }}
                  exit={{ opacity: 0, x: 20 }}
                  transition={{ delay: idx * 0.1 }}
                  className={`relative group ${t.glass.card} ${t.glass.border} rounded-lg p-4 hover:shadow-lg transition-all`}
                >
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3 flex-1">
                      <div className={`w-10 h-10 rounded-lg ${
                        doc.verified ? 'bg-green-500/20' : 'bg-blue-500/20'
                      } flex items-center justify-center`}>
                        <Icon className={`w-5 h-5 ${
                          doc.verified ? 'text-green-500' : 'text-blue-500'
                        }`} />
                      </div>
                      <div className="flex-1">
                        <p className={`${t.text.primary} font-medium`}>
                          {doc.name || doc.file_name || `Document ${idx + 1}`}
                        </p>
                        <div className="flex items-center gap-4 mt-1">
                          <span className={`${t.text.muted} text-xs`}>
                            {formatFileSize(doc.size)}
                          </span>
                          {doc.uploaded_at && (
                            <span className={`${t.text.muted} text-xs`}>
                              {new Date(doc.uploaded_at).toLocaleDateString()}
                            </span>
                          )}
                          {doc.verified && (
                            <span className="flex items-center gap-1 text-xs text-green-600 dark:text-green-400">
                              <CheckCircleIcon className="w-3 h-3" />
                              Verified
                            </span>
                          )}
                        </div>
                      </div>
                    </div>

                    {/* Actions */}
                    <div className="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                      <motion.button
                        whileHover={{ scale: 1.1 }}
                        whileTap={{ scale: 0.9 }}
                        onClick={() => setSelectedDoc(doc)}
                        className="p-2 hover:bg-white/10 rounded-lg transition-colors"
                        aria-label="Preview document"
                      >
                        <EyeIcon className="w-4 h-4" />
                      </motion.button>
                      <motion.a
                        href={doc.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        download
                        whileHover={{ scale: 1.1 }}
                        whileTap={{ scale: 0.9 }}
                        className="p-2 hover:bg-white/10 rounded-lg transition-colors"
                        aria-label="Download document"
                      >
                        <ArrowDownTrayIcon className="w-4 h-4" />
                      </motion.a>
                      {!readOnly && (
                        <motion.button
                          whileHover={{ scale: 1.1 }}
                          whileTap={{ scale: 0.9 }}
                          onClick={() => handleDelete(doc.id)}
                          className="p-2 hover:bg-red-500/10 rounded-lg transition-colors text-red-500"
                          aria-label="Delete document"
                        >
                          <TrashIcon className="w-4 h-4" />
                        </motion.button>
                      )}
                    </div>
                  </div>

                  {/* AI Insights */}
                  {doc.ai_insights && (
                    <motion.div
                      initial={{ height: 0, opacity: 0 }}
                      animate={{ height: 'auto', opacity: 1 }}
                      className="mt-3 pt-3 border-t border-white/10"
                    >
                      <div className="flex items-center gap-2 mb-2">
                        <SparklesIcon className="w-4 h-4 text-purple-500" />
                        <span className="text-xs text-purple-600 dark:text-purple-400">AI Analysis</span>
                      </div>
                      <div className="flex items-center gap-4">
                        <div>
                          <p className={`${t.text.muted} text-xs`}>Completeness</p>
                          <div className="w-24 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full mt-1">
                            <div
                              className="h-full bg-gradient-to-r from-purple-500 to-blue-500 rounded-full"
                              style={{ width: `${doc.ai_insights.completeness}%` }}
                            />
                          </div>
                        </div>
                        {doc.ai_insights.issues.length > 0 && (
                          <div className="flex-1">
                            <p className="text-xs text-amber-600 dark:text-amber-400">
                              {doc.ai_insights.issues[0]}
                            </p>
                          </div>
                        )}
                      </div>
                    </motion.div>
                  )}
                </motion.div>
              );
            })}
          </AnimatePresence>

          {/* Upload Progress */}
          <AnimatePresence>
            {isUploading && (
              <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -20 }}
                className={`${t.glass.card} ${t.glass.border} rounded-lg p-4`}
              >
                <div className="flex items-center gap-3">
                  <CloudArrowUpIcon className="w-5 h-5 text-blue-500 animate-pulse" />
                  <div className="flex-1">
                    <p className={`${t.text.primary} text-sm`}>Uploading documents...</p>
                    <div className="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-full mt-2">
                      <motion.div
                        initial={{ width: 0 }}
                        animate={{ width: `${uploadProgress}%` }}
                        className="h-full bg-gradient-to-r from-blue-500 to-purple-500 rounded-full"
                      />
                    </div>
                  </div>
                  <span className={`${t.text.muted} text-sm`}>{uploadProgress}%</span>
                </div>
              </motion.div>
            )}
          </AnimatePresence>
        </div>
      )}

      {/* Upload Area */}
      {!readOnly && !isUploading && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          className="mt-4"
        >
          <div
            onDragEnter={handleDrag}
            onDragLeave={handleDrag}
            onDragOver={handleDrag}
            onDrop={handleDrop}
            className={`relative border-2 border-dashed rounded-xl p-8 text-center transition-all ${
              dragActive
                ? 'border-blue-500 bg-blue-500/10'
                : 'border-gray-300 dark:border-gray-700 hover:border-gray-400 dark:hover:border-gray-600'
            }`}
          >
            <input
              ref={fileInputRef}
              type="file"
              multiple
              onChange={(e) => e.target.files && handleFileUpload(e.target.files)}
              className="hidden"
              accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
            />
            
            <CloudArrowUpIcon className={`w-12 h-12 mx-auto mb-4 ${
              dragActive ? 'text-blue-500' : t.text.muted
            }`} />
            
            <p className={`${t.text.primary} font-medium mb-2`}>
              Drop files here or{' '}
              <button
                onClick={() => fileInputRef.current?.click()}
                className="text-blue-500 hover:text-blue-600 underline"
              >
                browse
              </button>
            </p>
            
            <p className={`${t.text.muted} text-sm`}>
              Supports PDF, DOC, DOCX, JPG, PNG (Max 10MB)
            </p>
          </div>
        </motion.div>
      )}

      {/* Document Preview Modal */}
      <AnimatePresence>
        {selectedDoc && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            onClick={() => setSelectedDoc(null)}
          >
            <motion.div
              initial={{ scale: 0.9 }}
              animate={{ scale: 1 }}
              exit={{ scale: 0.9 }}
              className={`${t.glass.card} ${t.glass.border} rounded-2xl p-6 max-w-2xl w-full max-h-[80vh] overflow-auto`}
              onClick={(e) => e.stopPropagation()}
            >
              <div className="flex items-center justify-between mb-4">
                <h4 className={`${t.text.primary} text-lg font-semibold`}>Document Preview</h4>
                <button
                  title="Close document preview"
                  onClick={() => setSelectedDoc(null)}
                  className="p-2 hover:bg-white/10 rounded-lg transition-colors"
                >
                  <XCircleIcon className="w-5 h-5" />
                </button>
              </div>
              <div className="bg-gray-100 dark:bg-gray-800 rounded-lg p-8 text-center">
                <DocumentIcon className="w-24 h-24 mx-auto text-gray-400 mb-4" />
                <p className={t.text.muted}>
                  Preview for {selectedDoc.name || 'this document'} would appear here
                </p>
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
};

export default ConfirmationDocuments;
