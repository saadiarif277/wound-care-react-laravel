import React, { useState, useRef } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { useTheme } from '@/contexts/ThemeContext';
import { themes } from '@/theme/glass-theme';
import { cn } from '@/lib/utils';
import {
  ArrowLeft,
  FileText,
  Clock,
  Package,
  CheckCircle,
  Heart,
  Send,
  ChevronDown,
  ChevronUp,
  FileCheck,
  Download,
  Info,
  Upload,
  Truck,
  Trash2,
  Plus,
} from 'lucide-react';
import axios from 'axios';
import { toast } from 'react-toastify';

interface Order {
  id: string;
  order_number: string;
  order_status: string;
  provider: {
    id: number;
    name: string;
    email: string;
    npi_number?: string;
  };
  facility: {
    id: number;
    name: string;
    city: string;
    state: string;
  };
  expected_service_date: string;
  submitted_at: string;
  total_order_value: number;
  action_required: boolean;
  products: Array<{
    id: number;
    name: string;
    sku: string;
    quantity: number;
    unit_price: number;
    total_price: number;
  }>;
}

interface Document {
  id: string;
  type?: string;
  name?: string;
  file_name?: string;
  url: string;
  file_size?: number;
  uploaded_at?: string;
  document_type?: string;
  uploaded_by?: number | string;
  docuseal_submission_id?: string;
  mime_type?: string;
}

interface Episode {
  id: string;
  patient_id: string;
  patient_name: string;
  patient_display_id: string;
  status: string;
  ivr_status: string;
  verification_date?: string;
  expiration_date?: string;
  azure_order_checklist_fhir_id?: string;
  docuseal_submission_id?: string;
  manufacturer: {
    id: number;
    name: string;
    contact_email?: string;
    contact_phone?: string;
  };
  orders: Order[];
  documents: Document[];
  total_order_value: number;
  orders_count: number;
  action_required: boolean;
}

interface ShowEpisodeProps {
  episode: Episode;
  can_review_episode: boolean;
  can_manage_episode: boolean;
  can_send_to_manufacturer: boolean;
}

// Status Configuration
const statusConfig = {
  ready_for_review: {
    color: 'bg-blue-100 text-blue-800 border-blue-300',
    icon: Clock,
    label: 'Ready for Review',
    description: 'Provider completed order with IVR - awaiting admin review',
  },
  ivr_verified: {
    color: 'bg-green-100 text-green-800 border-green-300',
    icon: CheckCircle,
    label: 'IVR Verified',
    description: 'Admin reviewed and approved provider IVR - ready for manufacturer',
  },
  sent_to_manufacturer: {
    color: 'bg-purple-100 text-purple-800 border-purple-300',
    icon: Package,
    label: 'Sent to Manufacturer',
    description: 'Episode with IVR sent to manufacturer for processing',
  },
  tracking_added: {
    color: 'bg-indigo-100 text-indigo-800 border-indigo-300',
    icon: Truck,
    label: 'Tracking Added',
    description: 'Tracking information added, shipment in progress',
  },
  completed: {
    color: 'bg-green-100 text-green-800 border-green-300',
    icon: CheckCircle,
    label: 'Completed',
    description: 'Episode fully processed and completed successfully',
  },
};

const ivrStatusConfig = {
  provider_completed: {
    color: 'bg-blue-100 text-blue-800 border-blue-300',
    icon: CheckCircle,
    label: 'Provider Completed',
    description: 'Provider generated and signed IVR during order submission'
  },
  admin_reviewed: {
    color: 'bg-green-100 text-green-800 border-green-300',
    icon: CheckCircle,
    label: 'Admin Reviewed',
    description: 'Admin reviewed and approved provider-generated IVR'
  },
};

const ShowEpisode: React.FC<ShowEpisodeProps> = ({
  episode,
  can_review_episode,
  can_manage_episode,
  can_send_to_manufacturer,
}) => {
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

  const [expandedSections, setExpandedSections] = useState({
    orders: true,
    documents: true,
    ivr: true,
  });

  const [isUploading, setIsUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [loading, setLoading] = useState(false);

  const { post, delete: destroy } = useForm();

  const generateIVR = async () => {
    try {
      // Check if we have FHIR data
      if (!episode.azure_order_checklist_fhir_id) {
        console.warn('No FHIR checklist data for episode');
        alert('No FHIR checklist data for this episode. Please complete the clinical assessment first.');
        return;
      }
      
      const response = await fetch(`/admin/episodes/${episode.id}/generate-ivr`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
        },
      });
      
      if (response.ok) {
        alert('IVR generated successfully. The page will now reload.');
        router.reload({ only: ['episode'] });
      } else {
          const errorText = await response.text();
          alert(`Failed to generate IVR: ${errorText}`);
      }
    } catch (error) {
      console.error('Failed to generate IVR', error);
      alert('Failed to generate IVR. See console for details.');
    }
  };

  const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(amount);
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    });
  };

  const formatFileSize = (bytes?: number) => {
    if (!bytes) return 'Unknown size';
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
  };

  const toggleSection = (section: keyof typeof expandedSections) => {
    setExpandedSections(prev => ({
      ...prev,
      [section]: !prev[section],
    }));
  };

  const handleFileUpload = async (files: FileList) => {
    if (!files.length) return;

    setIsUploading(true);
    setUploadProgress(0);

    const formData = new FormData();
    Array.from(files).forEach(file => {
      formData.append('documents[]', file);
    });

    // Simulate progress
    const interval = setInterval(() => {
      setUploadProgress(prev => {
        if (prev >= 90) {
          clearInterval(interval);
          return 90;
        }
        return prev + 10;
      });
    }, 200);

    post(route('admin.episodes.documents.upload', episode.id), {
      data: formData,
      onSuccess: () => {
        setUploadProgress(100);
        setTimeout(() => {
          setIsUploading(false);
          setUploadProgress(0);
          router.reload({ only: ['episode'] });
        }, 1000);
      },
      onError: (errors) => {
        clearInterval(interval);
        setIsUploading(false);
        setUploadProgress(0);
        console.error('Upload failed:', errors);
      }
    });
  };

  const handleDeleteDocument = (documentId: string) => {
    if (confirm('Are you sure you want to delete this document?')) {
      destroy(route('admin.episodes.documents.delete', { episode: episode.id, document: documentId }), {
        onSuccess: () => {
          router.reload({ only: ['episode'] });
        }
      });
    }
  };

  const handleReviewEpisode = () => {
    post(route('admin.episodes.review', episode.id), {
      onSuccess: () => {
        router.reload({ only: ['episode'] });
      }
    });
  };

  const handleSendToManufacturer = () => {
    post(route('admin.episodes.send-to-manufacturer', episode.id), {
      data: {
        recipients: [episode.manufacturer.contact_email].filter(Boolean),
        include_ivr: true,
        include_clinical_notes: true
      },
      onSuccess: () => {
        router.reload({ only: ['episode'] });
      }
    });
  };

  const handleSendAppLink = async (phoneNumber: string) => {
    try {
      setLoading(true);
      const response = await axios.post(`/api/v1/episodes/${episode.id}/send-app-link`, {
        phone_number: phoneNumber,
      });

      if (response.status === 200) {
        toast({
          title: "Success",
          description: "Application link sent successfully!",
        });
        router.reload({ only: ['episode'] });
      } else {
        toast({
          title: "Error",
          description: "Failed to send application link.",
          variant: "destructive",
        });
      }
    } catch (error) {
      console.error('Failed to send application link', error);
      toast({
        title: "Error",
        description: "An unexpected error occurred. Please try again.",
        variant: "destructive",
      });
    } finally {
      setLoading(false);
    }
  };

  const currentStatusConfig = statusConfig[episode.status as keyof typeof statusConfig];
  const currentIvrStatusConfig = ivrStatusConfig[episode.ivr_status as keyof typeof ivrStatusConfig];

  return (
    <MainLayout>
      <Head title={`Episode ${episode.patient_display_id} - ${episode.manufacturer.name} | MSC Healthcare`} />

      <div className={cn("min-h-screen p-6", theme === 'dark' ? 'bg-gray-900' : 'bg-gray-50')}>
        {/* Header */}
        <div className="mb-6">
          <div className={cn(t.glass.card, t.glass.border, "p-6")}>
            <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between">
              <div className="flex items-center space-x-4 mb-4 lg:mb-0">
                <Link
                  href={route('admin.orders.index')}
                  className={cn("flex items-center", t.text.secondary, "hover:" + t.text.primary, "transition-colors")}
                >
                  <ArrowLeft className="w-5 h-5 mr-2" />
                  Back to Episodes
                </Link>

                <div className="h-6 w-px bg-gray-300"></div>

                <div className="flex items-center space-x-3">
                  <Heart className="w-6 h-6 text-purple-600" />
                  <div>
                    <h1 className={cn(t.text.primary, "text-2xl font-bold")}>
                      {episode.patient_name || episode.patient_display_id}
                    </h1>
                    <p className={cn(t.text.secondary, "text-sm")}>
                      Episode with {episode.manufacturer.name} • {episode.orders_count} orders
                    </p>
                  </div>
                </div>
              </div>

              {/* Status Badges */}
              <div className="flex flex-col sm:flex-row gap-3">
                <div className={cn(
                  "px-3 py-1 rounded-full text-sm font-medium border",
                  currentStatusConfig?.color || 'bg-gray-100 text-gray-800 border-gray-300'
                )}>
                  {currentStatusConfig?.label || episode.status}
                </div>
                {currentIvrStatusConfig && (
                  <div className={cn(
                    "px-3 py-1 rounded-full text-sm font-medium border",
                    currentIvrStatusConfig.color
                  )}>
                    IVR: {currentIvrStatusConfig.label}
                  </div>
                )}
              </div>
            </div>

            {/* Episode Summary Stats */}
            <div className="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
              <div className="text-center p-3 rounded-lg bg-gradient-to-br from-blue-500/10 to-purple-500/10">
                <p className={cn(t.text.primary, "text-xl font-bold")}>{episode.orders_count}</p>
                <p className={cn(t.text.secondary, "text-sm")}>Orders</p>
              </div>
              <div className="text-center p-3 rounded-lg bg-gradient-to-br from-green-500/10 to-blue-500/10">
                <p className="text-xl font-bold text-green-600 dark:text-green-400">{formatCurrency(episode.total_order_value)}</p>
                <p className={cn(t.text.secondary, "text-sm")}>Total Value</p>
              </div>
              <div className="text-center p-3 rounded-lg bg-gradient-to-br from-purple-500/10 to-pink-500/10">
                <p className={cn(t.text.primary, "text-lg font-semibold")}>{episode.manufacturer.name}</p>
                <p className={cn(t.text.secondary, "text-sm")}>Manufacturer</p>
              </div>
              <div className="text-center p-3 rounded-lg bg-gradient-to-br from-amber-500/10 to-orange-500/10">
                <p className={cn(t.text.primary, "text-lg font-semibold")}>{episode.documents.length}</p>
                <p className={cn(t.text.secondary, "text-sm")}>Documents</p>
              </div>
            </div>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Left Column: Orders */}
          <div className="lg:col-span-2 space-y-6">

            {/* Orders Section */}
            <div className={cn(t.glass.card, t.glass.border, "hover:shadow-lg transition-all duration-300")}>
              <div
                className={cn(
                  "flex items-center justify-between p-6 border-b cursor-pointer hover:" + t.glass.hover,
                  theme === 'dark' ? 'border-gray-700' : 'border-gray-200',
                  "transition-colors"
                )}
                onClick={() => toggleSection('orders')}
              >
                <div className="flex items-center">
                  <Package className="w-5 h-5 text-blue-600 mr-3" />
                  <h3 className={cn(t.text.primary, "text-lg font-semibold")}>
                    Orders ({episode.orders_count})
                  </h3>
                </div>
                {expandedSections.orders ?
                  <ChevronUp className="w-5 h-5 text-gray-400" /> :
                  <ChevronDown className="w-5 h-5 text-gray-400" />
                }
              </div>

              {expandedSections.orders && (
                <div className="p-6">
                  <div className="space-y-4">
                    {episode.orders.map((order) => (
                      <div
                        key={order.id}
                        className={cn(
                          "p-4 rounded-lg border",
                          theme === 'dark' ? 'border-gray-700 bg-gray-800/50' : 'border-gray-200 bg-gray-50'
                        )}
                      >
                        <div className="flex items-center justify-between mb-3">
                          <div>
                            <h4 className={cn(t.text.primary, "font-medium")}>
                              Order #{order.order_number}
                            </h4>
                            <p className={cn(t.text.secondary, "text-sm")}>
                              {order.provider.name} • {order.facility.name}
                            </p>
                          </div>
                          <div className="text-right">
                            <p className={cn(t.text.primary, "font-semibold")}>
                              {formatCurrency(order.total_order_value)}
                            </p>
                            <p className={cn(t.text.secondary, "text-sm")}>
                              {formatDate(order.expected_service_date)}
                            </p>
                          </div>
                        </div>

                        {/* Products */}
                        <div className="space-y-2">
                          {order.products.map((product) => (
                            <div key={product.id} className="flex justify-between text-sm">
                              <span className={t.text.secondary}>
                                {product.quantity}x {product.name} ({product.sku})
                              </span>
                              <span className={t.text.primary}>
                                {formatCurrency(product.total_price)}
                              </span>
                            </div>
                          ))}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>

            {/* IVR Documents Section */}
            <div className={cn(t.glass.card, t.glass.border, "hover:shadow-lg transition-all duration-300")}>
              <div
                className={cn(
                  "flex items-center justify-between p-6 border-b cursor-pointer hover:" + t.glass.hover,
                  theme === 'dark' ? 'border-gray-700' : 'border-gray-200',
                  "transition-colors"
                )}
                onClick={() => toggleSection('ivr')}
              >
                <div className="flex items-center">
                  <FileCheck className="w-5 h-5 text-purple-600 mr-3" />
                  <h3 className={cn(t.text.primary, "text-lg font-semibold")}>
                    IVR Documents
                  </h3>
                </div>
                <div className="flex items-center gap-2">
                  {episode.ivr_status === 'provider_completed' && (
                    <span className={cn(
                      "px-2 py-1 text-xs rounded-full",
                      "bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400"
                    )}>
                      Completed
                    </span>
                  )}
                  {expandedSections.ivr ?
                    <ChevronUp className="w-5 h-5 text-gray-400" /> :
                    <ChevronDown className="w-5 h-5 text-gray-400" />
                  }
                </div>
              </div>

              {expandedSections.ivr && (
                <div className="p-6">
                  {(() => {
                    const ivrDocuments = episode.documents.filter(doc => doc.type === 'ivr');

                    if (ivrDocuments.length > 0) {
                      return (
                        <div className="space-y-3">
                          {ivrDocuments.map((doc) => (
                            <div
                              key={doc.id}
                              className={cn(
                                "flex items-center justify-between p-4 rounded-lg border",
                                theme === 'dark' ? 'border-gray-700 bg-gray-800/50' : 'border-gray-200 bg-gray-50'
                              )}
                            >
                              <div className="flex items-center gap-3">
                                <div className="p-2 rounded-lg bg-purple-100 dark:bg-purple-900/20">
                                  <FileCheck className="w-5 h-5 text-purple-600 dark:text-purple-400" />
                                </div>
                                <div>
                                  <p className={cn(t.text.primary, "font-medium")}>
                                    {doc.name || 'IVR Document'}
                                  </p>
                                  <div className="flex items-center gap-2 text-xs">
                                    <span className={t.text.secondary}>
                                      Signed by provider
                                    </span>
                                    {doc.uploaded_at && (
                                      <>
                                        <span className={t.text.secondary}>•</span>
                                        <span className={t.text.secondary}>
                                          {formatDate(doc.uploaded_at)}
                                        </span>
                                      </>
                                    )}
                                  </div>
                                </div>
                              </div>
                              <div className="flex items-center gap-2">
                                <a
                                  href={doc.url}
                                  target="_blank"
                                  rel="noopener noreferrer"
                                  className={cn(t.button.primary, "px-3 py-1 text-sm")}
                                >
                                  <Download className="w-4 h-4 mr-1" />
                                  View IVR
                                </a>
                              </div>
                            </div>
                          ))}

                          {episode.status === 'ready_for_review' && (
                            <div className={cn(
                              "mt-4 p-4 rounded-lg border",
                              "bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700"
                            )}>
                              <div className="flex items-start">
                                <Info className="w-4 h-4 text-blue-600 dark:text-blue-400 mt-0.5 mr-2" />
                                <div>
                                  <p className="text-sm font-medium text-blue-800 dark:text-blue-300">
                                    Ready for Review
                                  </p>
                                  <p className="text-xs text-blue-700 dark:text-blue-400 mt-1">
                                    The provider has completed and signed the IVR. Please review the document above before approving.
                                  </p>
                                </div>
                              </div>
                            </div>
                          )}
                        </div>
                      );
                    } else {
                      return (
                        <div className="text-center py-8">
                          <FileCheck className={cn("w-16 h-16 mx-auto mb-4", t.text.muted)} />
                          <p className={cn(t.text.muted, "mb-2")}>No IVR documents found</p>
                          <p className={cn(t.text.secondary, "text-sm")}>
                            {episode.ivr_status === 'provider_completed'
                              ? 'IVR completed but document not yet synced'
                              : 'IVR has not been completed for this episode'}
                          </p>
                        </div>
                      );
                    }
                  })()}
                </div>
              )}
            </div>

            {/* Documents Section */}
            <div className={cn(t.glass.card, t.glass.border, "hover:shadow-lg transition-all duration-300")}>
              <div
                className={cn(
                  "flex items-center justify-between p-6 border-b cursor-pointer hover:" + t.glass.hover,
                  theme === 'dark' ? 'border-gray-700' : 'border-gray-200',
                  "transition-colors"
                )}
                onClick={() => toggleSection('documents')}
              >
                <div className="flex items-center">
                  <FileText className="w-5 h-5 text-green-600 mr-3" />
                  <h3 className={cn(t.text.primary, "text-lg font-semibold")}>
                    Documents ({episode.documents.length})
                  </h3>
                </div>
                <div className="flex items-center gap-2">
                  {can_manage_episode && (
                    <button
                      onClick={(e) => {
                        e.stopPropagation();
                        fileInputRef.current?.click();
                      }}
                      className={cn(t.button.primary, "px-3 py-1 text-sm")}
                    >
                      <Upload className="w-4 h-4 mr-1" />
                      Upload
                    </button>
                  )}
                  {expandedSections.documents ?
                    <ChevronUp className="w-5 h-5 text-gray-400" /> :
                    <ChevronDown className="w-5 h-5 text-gray-400" />
                  }
                </div>
              </div>

              {/* Hidden file input */}
              <input
                ref={fileInputRef}
                type="file"
                multiple
                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                onChange={(e) => e.target.files && handleFileUpload(e.target.files)}
                className="hidden"
              />

              {expandedSections.documents && (
                <div className="p-6">
                  {/* Upload Progress */}
                  {isUploading && (
                    <div className={cn(t.glass.card, "p-4 mb-4")}>
                      <div className="flex items-center gap-3">
                        <Upload className="w-5 h-5 text-blue-500 animate-pulse" />
                        <div className="flex-1">
                          <p className={cn(t.text.primary, "text-sm")}>Uploading documents...</p>
                          <div className="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-full mt-2">
                            <div
                              className={`h-full bg-gradient-to-r from-blue-500 to-purple-500 rounded-full transition-all duration-300 upload-progress-bar`}
                              data-progress={uploadProgress}
                            />
                          </div>
                        </div>
                        <span className={cn(t.text.secondary, "text-sm")}>{uploadProgress}%</span>
                      </div>
                    </div>
                  )}

                  {/* Documents List (excluding IVR) */}
                  {(() => {
                    const nonIvrDocuments = episode.documents.filter(doc => doc.type !== 'ivr');

                    if (nonIvrDocuments.length > 0) {
                      return (
                        <div className="space-y-3">
                          {nonIvrDocuments.map((doc) => (
                        <div
                          key={doc.id}
                          className={cn(
                            "flex items-center justify-between p-3 rounded-lg border",
                            theme === 'dark' ? 'border-gray-700 bg-gray-800/50' : 'border-gray-200 bg-gray-50'
                          )}
                        >
                          <div className="flex items-center gap-3">
                            <FileText className="w-5 h-5 text-blue-500" />
                            <div>
                              <p className={cn(t.text.primary, "font-medium")}>
                                {doc.name || doc.file_name || 'Document'}
                              </p>
                              <div className="flex items-center gap-2 text-xs">
                                <span className={t.text.secondary}>
                                  {formatFileSize(doc.file_size)}
                                </span>
                                {doc.uploaded_at && (
                                  <>
                                    <span className={t.text.secondary}>•</span>
                                    <span className={t.text.secondary}>
                                      {formatDate(doc.uploaded_at)}
                                    </span>
                                  </>
                                )}
                              </div>
                            </div>
                          </div>
                          <div className="flex items-center gap-2">
                            <a
                              href={doc.url}
                              target="_blank"
                              rel="noopener noreferrer"
                              className={cn(t.button.ghost, "p-2")}
                              title="Download document"
                            >
                              <Download className="w-4 h-4" />
                            </a>
                            {can_manage_episode && (
                              <button
                                onClick={() => handleDeleteDocument(doc.id)}
                                className={cn(t.button.ghost, "p-2 text-red-500 hover:text-red-600")}
                                title="Delete document"
                              >
                                <Trash2 className="w-4 h-4" />
                              </button>
                            )}
                          </div>
                        </div>
                          ))}
                        </div>
                      );
                    } else {
                      return (
                    <div className="text-center py-8">
                      <FileText className={cn("w-16 h-16 mx-auto mb-4", t.text.muted)} />
                      <p className={cn(t.text.muted, "mb-4")}>No documents uploaded yet</p>
                      {can_manage_episode && (
                        <button
                          onClick={() => fileInputRef.current?.click()}
                          className={cn(t.button.primary, "px-4 py-2")}
                        >
                          <Plus className="w-4 h-4 mr-2" />
                          Upload Documents
                        </button>
                      )}
                    </div>
                      );
                    }
                  })()}

                  {/* Drag & Drop Upload Area */}
                  {can_manage_episode && (
                    <div
                      onClick={() => fileInputRef.current?.click()}
                      className={cn(
                        "mt-4 border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-colors",
                        theme === 'dark' ? 'border-gray-600 hover:border-gray-500' : 'border-gray-300 hover:border-gray-400'
                      )}
                    >
                      <Upload className={cn("w-8 h-8 mx-auto mb-2", t.text.muted)} />
                      <p className={cn(t.text.primary, "font-medium")}>
                        Drop files here or click to browse
                      </p>
                      <p className={cn(t.text.secondary, "text-sm mt-1")}>
                        PDF, DOC, DOCX, JPG, PNG (Max 10MB each)
                      </p>
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>

          {/* Right Column: Actions & Info */}
          <div className="space-y-6">

            {/* Episode Actions */}
            <div className={cn(t.glass.card, t.glass.border, "p-6")}>
              <h3 className={cn(t.text.primary, "text-lg font-semibold mb-4")}>Episode Actions</h3>

              <div className="space-y-3">
                {can_review_episode && episode.status === 'ready_for_review' && (
                  <button
                    onClick={handleReviewEpisode}
                    className={cn(t.button.primary, "w-full justify-center")}
                  >
                    <CheckCircle className="w-4 h-4 mr-2" />
                    Review & Approve IVR
                  </button>
                )}

                {can_send_to_manufacturer && episode.status === 'ivr_verified' && (
                  <button
                    onClick={handleSendToManufacturer}
                    className={cn(t.button.primary, "w-full justify-center")}
                  >
                    <Send className="w-4 h-4 mr-2" />
                    Send to Manufacturer
                  </button>
                )}

                <button
                  onClick={generateIVR}
                  className={cn(t.button.secondary, "w-full justify-center mt-2")}
                  title="Generate a new IVR document using the latest FHIR clinical data."
                >
                  <FileCheck className="w-4 h-4 mr-2" />
                  Generate IVR from FHIR
                </button>

                {episode.status === 'sent_to_manufacturer' && (
                  <div className={cn("p-3 rounded-lg border", "bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700")}>
                    <div className="flex items-center">
                      <Info className="w-4 h-4 text-blue-600 mr-2" />
                      <p className="text-sm font-medium text-blue-800 dark:text-blue-300">
                        Awaiting manufacturer response
                      </p>
                    </div>
                  </div>
                )}

                {!can_review_episode && !can_send_to_manufacturer && !can_manage_episode && (
                  <div className="p-3 bg-gray-50 border border-gray-200 rounded-lg text-center">
                    <Info className="w-5 h-5 text-gray-400 mx-auto mb-1" />
                    <p className="text-sm text-gray-600">No actions available</p>
                  </div>
                )}
              </div>
            </div>

            {/* Episode Information */}
            <div className={cn(t.glass.card, t.glass.border, "p-6")}>
              <h3 className={cn(t.text.primary, "text-lg font-semibold mb-4")}>Episode Information</h3>

              <div className="space-y-3">
                <div className="flex justify-between">
                  <span className={cn(t.text.secondary, "text-sm")}>Patient ID:</span>
                  <span className={cn(t.text.primary, "text-sm font-medium")}>
                    {episode.patient_display_id}
                  </span>
                </div>

                <div className="flex justify-between">
                  <span className={cn(t.text.secondary, "text-sm")}>Manufacturer:</span>
                  <span className={cn(t.text.primary, "text-sm font-medium")}>
                    {episode.manufacturer.name}
                  </span>
                </div>

                {episode.verification_date && (
                  <div className="flex justify-between">
                    <span className={cn(t.text.secondary, "text-sm")}>Verified:</span>
                    <span className={cn(t.text.primary, "text-sm")}>
                      {formatDate(episode.verification_date)}
                    </span>
                  </div>
                )}

                {episode.expiration_date && (
                  <div className="flex justify-between">
                    <span className={cn(t.text.secondary, "text-sm")}>Expires:</span>
                    <span className={cn(t.text.primary, "text-sm")}>
                      {formatDate(episode.expiration_date)}
                    </span>
                  </div>
                )}

                {episode.manufacturer.contact_email && (
                  <div className="flex justify-between">
                    <span className={cn(t.text.secondary, "text-sm")}>Contact:</span>
                    <span className={cn(t.text.primary, "text-sm")}>
                      {episode.manufacturer.contact_email}
                    </span>
                  </div>
                )}
              </div>
            </div>

            {/* Provider Information */}
            {episode.orders.length > 0 && episode.orders[0]?.provider && (
              <div className={cn(t.glass.card, t.glass.border, "p-6")}>
                <h3 className={cn(t.text.primary, "text-lg font-semibold mb-4")}>Provider Information</h3>

                <div className="space-y-3">
                  <div className="flex justify-between">
                    <span className={cn(t.text.secondary, "text-sm")}>Name:</span>
                    <span className={cn(t.text.primary, "text-sm font-medium")}>
                      {episode.orders[0].provider.name}
                    </span>
                  </div>

                  <div className="flex justify-between">
                    <span className={cn(t.text.secondary, "text-sm")}>Email:</span>
                    <span className={cn(t.text.primary, "text-sm")}>
                      {episode.orders[0].provider.email}
                    </span>
                  </div>

                  {episode.orders[0].provider.npi_number && (
                    <div className="flex justify-between">
                      <span className={cn(t.text.secondary, "text-sm")}>NPI:</span>
                      <span className={cn(t.text.primary, "text-sm font-mono")}>
                        {episode.orders[0].provider.npi_number}
                      </span>
                    </div>
                  )}

                  {episode.orders[0].facility && (
                    <div className="flex justify-between">
                      <span className={cn(t.text.secondary, "text-sm")}>Facility:</span>
                      <span className={cn(t.text.primary, "text-sm")}>
                        {episode.orders[0].facility.name}
                      </span>
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </MainLayout>
  );
};

export default ShowEpisode;
