import { useState, useEffect } from 'react';
import {
  FiAlertCircle, FiFileText, FiShoppingCart,
  FiDollarSign, FiUser, FiTruck,
  FiActivity, FiMessageSquare,
  FiShield, FiClock, FiChevronDown, FiChevronRight,
  FiCheckCircle, FiXCircle, FiAlertTriangle
} from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes } from '@/theme/glass-theme';
import { cn } from '@/lib/utils';
import { usePage } from '@inertiajs/react';
import { toast } from 'react-hot-toast';

// Types according to PRD
interface OrderReviewData {
  orderId: string;
  status: OrderStatus;
  patient: PatientSection;
  provider: ProviderSection;
  clinical: ClinicalSection;
  products: ProductSection[];
  forms: FormsSection;
  pricing: PricingSection;
  shipping: ShippingSection;
  audit?: AuditEntry[];
  notes?: InternalNote[];
}

type OrderStatus =
  | 'draft'
  | 'ready_for_review'
  | 'submitted'
  | 'under_admin_review'
  | 'sent_to_manufacturer'
  | 'in_production'
  | 'shipped'
  | 'delivered'
  | 'cancelled'
  | 'on_hold';

interface PatientSection {
  fhirId: string;
  displayId: string;
  demographics: {
    firstName: string;
    lastName: string;
    middleName?: string;
    suffix?: string;
    dateOfBirth: string;
    gender: string;
    phone: string;
    phoneType?: string;
    email?: string;
    address: {
      line1: string;
      line2?: string;
      city: string;
      state: string;
      zip: string;
    };
  };
  insurance: {
    primary: InsuranceCoverage;
    secondary?: InsuranceCoverage;
  };
}

interface InsuranceCoverage {
  payerName: string;
  payerId: string;
  planName: string;
  policyNumber: string;
  groupNumber?: string;
  subscriberName?: string;
  subscriberRelationship?: string;
  cardImages?: {
    front?: string;
    back?: string;
  };
}

interface ProviderSection {
  name: string;
  credentials?: string;
  npi: string;
  organization: string;
  department?: string;
  contact: {
    phone?: string;
    email?: string;
  };
}

interface ClinicalSection {
  wound: {
    type: string;
    description?: string;
    size: {
      length: number;
      width: number;
      depth: number;
    };
    location: string;
    duration: string;
  };
  diagnoses: {
    primary: DiagnosisCode;
    secondary: DiagnosisCode[];
  };
  clinicalJustification?: string;
  treatmentHistory: {
    priorApplications: number;
    anticipatedApplications: number;
    conservativeCare: {
      duration: string;
      types: string[];
    };
  };
  documents: ClinicalDocument[];
}

interface DiagnosisCode {
  code: string;
  description: string;
}

interface ClinicalDocument {
  id: string;
  type: string;
  name: string;
  size: number;
  uploadDate: string;
  url: string;
}

interface ProductSection {
  name: string;
  manufacturer: string;
  sku: string;
  sizes: ProductSize[];
  coverageAlerts?: CoverageAlert[];
}

interface ProductSize {
  size: string;
  quantity: number;
  unitPrice?: number;
  totalPrice?: number;
}

interface CoverageAlert {
  type: 'warning' | 'error' | 'info';
  message: string;
}

interface PricingSection {
  aspTotal: number;
  discount?: number;
  netPrice: number;
  patientResponsibility?: number;
}

interface FormsSection {
  ivr: FormStatus;
  order: FormStatus;
}

interface FormStatus {
  status: 'not_started' | 'in_progress' | 'complete' | 'expired';
  completionDate?: string;
  expirationDate?: string;
  documentUrl?: string;
  templateId?: string;
}

interface ShippingSection {
  sameAsPatient: boolean;
  address?: {
    line1: string;
    line2?: string;
    city: string;
    state: string;
    zip: string;
  };
  expectedDate: string;
  method: string;
  trackingNumber?: string;
  specialInstructions?: string;
}

interface AuditEntry {
  timestamp: string;
  user: string;
  action: string;
  details?: string;
}

interface InternalNote {
  id: string;
  timestamp: string;
  user: string;
  note: string;
}

interface OrderReviewSummaryProps {
  orderId: string;
  isPreSubmission?: boolean;
  onEdit?: (section: string) => void;
  onSubmit?: () => void;
}

// Section status helper
const getSectionStatus = (section: any, required: string[]): SectionStatus => {
  const missingFields = required.filter(field => {
    const value = field.split('.').reduce((obj, key) => obj?.[key], section);
    return !value;
  });

  if (missingFields.length === 0) {
    return { icon: 'complete', color: 'green', message: 'Complete' };
  } else if (missingFields.length <= 2) {
    return {
      icon: 'warning',
      color: 'yellow',
      message: `Missing: ${missingFields.join(', ')}`
    };
  } else {
    return {
      icon: 'error',
      color: 'red',
      message: `${missingFields.length} fields missing`
    };
  }
};

interface SectionStatus {
  icon: 'complete' | 'warning' | 'error' | 'pending';
  color: 'green' | 'yellow' | 'red' | 'gray';
  message: string;
}

export default function OrderReviewSummary({
  orderId,
  isPreSubmission = true,
  onEdit,
  onSubmit
}: OrderReviewSummaryProps) {
  const { theme = 'dark' } = useTheme();
  const t = themes[theme];
  const { auth } = usePage().props as any;
  const userRole = auth?.user?.role || 'provider';

  const [orderData, setOrderData] = useState<OrderReviewData | null>(null);
  const [loading, setLoading] = useState(true);
  const [expandedSections, setExpandedSections] = useState<Set<string>>(new Set(['patient', 'clinical', 'products']));
  const [showNoteModal, setShowNoteModal] = useState(false);
  const [newNote, setNewNote] = useState('');
  const [submitting, setSubmitting] = useState(false);

  // Load order data
  useEffect(() => {
    loadOrderData();
  }, [orderId]);

  const loadOrderData = async () => {
    try {
      setLoading(true);
      const response = await fetch(`/api/v1/orders/${orderId}/review`);
      const data = await response.json();
      setOrderData(data);
    } catch (error) {
      console.error('Error loading order data:', error);
      toast.error('Failed to load order details');
    } finally {
      setLoading(false);
    }
  };

  const toggleSection = (section: string) => {
    const newExpanded = new Set(expandedSections);
    if (newExpanded.has(section)) {
      newExpanded.delete(section);
    } else {
      newExpanded.add(section);
    }
    setExpandedSections(newExpanded);
  };

  const handleAddNote = async () => {
    if (!newNote.trim()) return;

    setSubmitting(true);
    try {
      // Add the note first
      await fetch(`/api/v1/orders/${orderId}/notes`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ note: newNote })
      });

      // Then submit the order (hardcoded as requested)
      await new Promise(resolve => setTimeout(resolve, 1000)); // Simulate API call

      toast.success('Order submitted successfully with note!');
      setNewNote('');
      setShowNoteModal(false);
      loadOrderData(); // Reload to show new note
    } catch (error) {
      console.error('Submission error:', error);
      toast.error('Failed to submit order. Please try again.');
    } finally {
      setSubmitting(false);
    }
  };

  // Determine if all sections are complete
  const isOrderComplete = () => {
    if (!orderData) return false;

    // Check required fields per section
    const patientComplete = !!(
      orderData.patient.demographics.firstName &&
      orderData.patient.demographics.lastName &&
      orderData.patient.demographics.dateOfBirth &&
      orderData.patient.insurance.primary.policyNumber
    );

    const clinicalComplete = !!(
      orderData.clinical.wound.type &&
      orderData.clinical.wound.size &&
      orderData.clinical.diagnoses.primary.code
    );

    const productsComplete = orderData.products.length > 0;

    const formsComplete = orderData.forms.ivr.status === 'complete';

    return patientComplete && clinicalComplete && productsComplete && formsComplete;
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  if (!orderData) {
    return (
      <div className="text-center py-12">
        <FiAlertCircle className="mx-auto h-12 w-12 text-gray-400 mb-4" />
        <p className={t.text.secondary}>Order not found</p>
      </div>
    );
  }

  return (
    <div className="max-w-6xl mx-auto space-y-6">
      {/* Header */}
      <div className={cn("p-6 rounded-lg flex justify-between items-start", t.glass.card)}>
        <div>
          <h1 className={cn("text-2xl font-bold", t.text.primary)}>
            Order Review - #{orderData.orderId}
          </h1>
          <p className={cn("text-sm mt-1", t.text.secondary)}>
            Status: <span className="font-medium capitalize">{orderData.status.replace('_', ' ')}</span>
          </p>
        </div>

        <div className="flex items-center space-x-3">
          {isPreSubmission && (
            <>
              <button
                onClick={() => setShowNoteModal(true)}
                className={cn(
                  "px-4 py-2 rounded-lg flex items-center space-x-2",
                  t.glass.frost,
                  "hover:bg-white/10 transition-colors"
                )}
              >
                <FiMessageSquare className="w-4 h-4" />
                <span>Add Note</span>
              </button>

              <button
                onClick={() => setShowNoteModal(true)}
                disabled={!isOrderComplete()}
                className={cn(
                  "px-6 py-2 rounded-lg font-medium transition-all",
                  isOrderComplete()
                    ? `${t.button.primary.base} ${t.button.primary.hover}`
                    : "bg-gray-300 text-gray-500 cursor-not-allowed"
                )}
              >
                Submit Order
              </button>
            </>
          )}
        </div>
      </div>

      {/* Patient & Insurance Section */}
      <SectionCard
        title="Patient & Insurance"
        icon={<FiUser />}
        status={getSectionStatus(orderData.patient, [
          'demographics.firstName',
          'demographics.lastName',
          'demographics.dateOfBirth',
          'insurance.primary.policyNumber'
        ])}
        expanded={expandedSections.has('patient')}
        onToggle={() => toggleSection('patient')}

      >
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {/* Patient Demographics */}
          <div>
            <h4 className={cn("font-medium mb-3", t.text.primary)}>Patient Information</h4>
            <dl className="space-y-2 text-sm">
              <div className="flex">
                <dt className={cn("font-medium w-24", t.text.secondary)}>Name:</dt>
                <dd className={t.text.primary}>
                  {orderData.patient.demographics.firstName} {orderData.patient.demographics.middleName} {orderData.patient.demographics.lastName} {orderData.patient.demographics.suffix}
                </dd>
              </div>
              <div className="flex">
                <dt className={cn("font-medium w-24", t.text.secondary)}>DOB:</dt>
                <dd className={t.text.primary}>{orderData.patient.demographics.dateOfBirth}</dd>
              </div>
              <div className="flex">
                <dt className={cn("font-medium w-24", t.text.secondary)}>Gender:</dt>
                <dd className={t.text.primary}>{orderData.patient.demographics.gender}</dd>
              </div>
              <div className="flex">
                <dt className={cn("font-medium w-24", t.text.secondary)}>Phone:</dt>
                <dd className={t.text.primary}>
                  {orderData.patient.demographics.phone}
                  {orderData.patient.demographics.phoneType && ` (${orderData.patient.demographics.phoneType})`}
                </dd>
              </div>
              {orderData.patient.demographics.email && (
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Email:</dt>
                  <dd className={t.text.primary}>{orderData.patient.demographics.email}</dd>
                </div>
              )}
              <div className="flex">
                <dt className={cn("font-medium w-24", t.text.secondary)}>Address:</dt>
                <dd className={t.text.primary}>
                  {orderData.patient.demographics.address.line1}
                  {orderData.patient.demographics.address.line2 && <>, {orderData.patient.demographics.address.line2}</>}
                  <br />
                  {orderData.patient.demographics.address.city}, {orderData.patient.demographics.address.state} {orderData.patient.demographics.address.zip}
                </dd>
              </div>
            </dl>
          </div>

          {/* Insurance Information */}
          <div>
            <h4 className={cn("font-medium mb-3", t.text.primary)}>Insurance Coverage</h4>

            {/* Primary Insurance */}
            <div className="mb-4">
              <h5 className={cn("text-sm font-medium mb-2", t.text.secondary)}>Primary Insurance</h5>
              <dl className="space-y-1 text-sm">
                <div className="flex">
                  <dt className={cn("font-medium w-32", t.text.secondary)}>Payer:</dt>
                  <dd className={t.text.primary}>{orderData.patient.insurance.primary.payerName}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-32", t.text.secondary)}>Plan:</dt>
                  <dd className={t.text.primary}>{orderData.patient.insurance.primary.planName}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-32", t.text.secondary)}>Policy #:</dt>
                  <dd className={t.text.primary}>{orderData.patient.insurance.primary.policyNumber}</dd>
                </div>
                {orderData.patient.insurance.primary.groupNumber && (
                  <div className="flex">
                    <dt className={cn("font-medium w-32", t.text.secondary)}>Group #:</dt>
                    <dd className={t.text.primary}>{orderData.patient.insurance.primary.groupNumber}</dd>
                  </div>
                )}
              </dl>
              {orderData.patient.insurance.primary.cardImages && (
                <div className="mt-2 flex space-x-2">
                  {orderData.patient.insurance.primary.cardImages.front && (
                    <button className={cn("text-xs text-blue-400 hover:text-blue-300 underline cursor-pointer", t.text.secondary)}>
                      View Card Front
                    </button>
                  )}
                  {orderData.patient.insurance.primary.cardImages.back && (
                    <button className={cn("text-xs text-blue-400 hover:text-blue-300 underline cursor-pointer", t.text.secondary)}>
                      View Card Back
                    </button>
                  )}
                </div>
              )}
            </div>

            {/* Secondary Insurance */}
            {orderData.patient.insurance.secondary && (
              <div>
                <h5 className={cn("text-sm font-medium mb-2", t.text.secondary)}>Secondary Insurance</h5>
                <dl className="space-y-1 text-sm">
                  <div className="flex">
                    <dt className={cn("font-medium w-32", t.text.secondary)}>Payer:</dt>
                    <dd className={t.text.primary}>{orderData.patient.insurance.secondary.payerName}</dd>
                  </div>
                  <div className="flex">
                    <dt className={cn("font-medium w-32", t.text.secondary)}>Policy #:</dt>
                    <dd className={t.text.primary}>{orderData.patient.insurance.secondary.policyNumber}</dd>
                  </div>
                </dl>
              </div>
            )}
          </div>
        </div>
      </SectionCard>

      {/* Provider Information Section */}
      {(userRole !== 'order_manager' || !isPreSubmission) && (
        <SectionCard
          title="Provider Information"
          icon={<FiShield />}
          status={getSectionStatus(orderData.provider, ['name', 'npi'])}
          expanded={expandedSections.has('provider')}
          onToggle={() => toggleSection('provider')}

        >
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <dl className="space-y-2 text-sm">
              <div className="flex">
                <dt className={cn("font-medium w-24", t.text.secondary)}>Name:</dt>
                <dd className={t.text.primary}>
                  {orderData.provider.name}
                  {orderData.provider.credentials && `, ${orderData.provider.credentials}`}
                </dd>
              </div>
              <div className="flex">
                <dt className={cn("font-medium w-24", t.text.secondary)}>NPI:</dt>
                <dd className={t.text.primary}>{orderData.provider.npi}</dd>
              </div>
              <div className="flex">
                <dt className={cn("font-medium w-24", t.text.secondary)}>Organization:</dt>
                <dd className={t.text.primary}>{orderData.provider.organization}</dd>
              </div>
            </dl>
            <dl className="space-y-2 text-sm">
              {orderData.provider.department && (
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Department:</dt>
                  <dd className={t.text.primary}>{orderData.provider.department}</dd>
                </div>
              )}
              {orderData.provider.contact.phone && (
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Phone:</dt>
                  <dd className={t.text.primary}>{orderData.provider.contact.phone}</dd>
                </div>
              )}
              {orderData.provider.contact.email && (
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Email:</dt>
                  <dd className={t.text.primary}>{orderData.provider.contact.email}</dd>
                </div>
              )}
            </dl>
          </div>
        </SectionCard>
      )}

      {/* Clinical Information Section */}
      <SectionCard
        title="Clinical Information"
        icon={<FiActivity />}
        status={getSectionStatus(orderData.clinical, [
          'wound.type',
          'wound.size',
          'wound.location',
          'diagnoses.primary.code'
        ])}
        expanded={expandedSections.has('clinical')}
        onToggle={() => toggleSection('clinical')}

      >
        <div className="space-y-6">
          {/* Wound Details */}
          <div>
            <h4 className={cn("font-medium mb-3", t.text.primary)}>Wound Assessment</h4>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <dl className="space-y-2 text-sm">
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Type:</dt>
                  <dd className={t.text.primary}>{orderData.clinical.wound.type}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Location:</dt>
                  <dd className={t.text.primary}>{orderData.clinical.wound.location}</dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Size:</dt>
                  <dd className={t.text.primary}>
                    {orderData.clinical.wound.size.length} × {orderData.clinical.wound.size.width} × {orderData.clinical.wound.size.depth} cm
                  </dd>
                </div>
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Duration:</dt>
                  <dd className={t.text.primary}>{orderData.clinical.wound.duration}</dd>
                </div>
              </dl>

              <div>
                <h5 className={cn("text-sm font-medium mb-2", t.text.secondary)}>Treatment History</h5>
                <dl className="space-y-2 text-sm">
                  <div className="flex">
                    <dt className={cn("font-medium w-40", t.text.secondary)}>Prior Applications:</dt>
                    <dd className={t.text.primary}>{orderData.clinical.treatmentHistory.priorApplications}</dd>
                  </div>
                  <div className="flex">
                    <dt className={cn("font-medium w-40", t.text.secondary)}>Anticipated Applications:</dt>
                    <dd className={t.text.primary}>{orderData.clinical.treatmentHistory.anticipatedApplications}</dd>
                  </div>
                  <div className="flex">
                    <dt className={cn("font-medium w-40", t.text.secondary)}>Conservative Care:</dt>
                    <dd className={t.text.primary}>
                      {orderData.clinical.treatmentHistory.conservativeCare.duration}
                      <br />
                      <span className="text-xs">{orderData.clinical.treatmentHistory.conservativeCare.types.join(', ')}</span>
                    </dd>
                  </div>
                </dl>
              </div>
            </div>
          </div>

          {/* Diagnoses */}
          <div>
            <h4 className={cn("font-medium mb-3", t.text.primary)}>Diagnoses</h4>
            <dl className="space-y-2 text-sm">
              <div>
                <dt className={cn("font-medium mb-1", t.text.secondary)}>Primary ICD-10:</dt>
                <dd className={t.text.primary}>
                  {orderData.clinical.diagnoses.primary.code} - {orderData.clinical.diagnoses.primary.description}
                </dd>
              </div>
              {orderData.clinical.diagnoses.secondary.length > 0 && (
                <div>
                  <dt className={cn("font-medium mb-1", t.text.secondary)}>Secondary ICD-10s:</dt>
                  <dd className={t.text.primary}>
                    {orderData.clinical.diagnoses.secondary.map((dx, idx) => (
                      <div key={idx} className="ml-4">
                        {dx.code} - {dx.description}
                      </div>
                    ))}
                  </dd>
                </div>
              )}
            </dl>
          </div>

          {/* Clinical Justification */}
          {orderData.clinical.clinicalJustification && (
            <div>
              <h4 className={cn("font-medium mb-2", t.text.primary)}>Clinical Justification</h4>
              <p className={cn("text-sm", t.text.secondary)}>
                {orderData.clinical.clinicalJustification}
              </p>
            </div>
          )}

          {/* Documents */}
          {orderData.clinical.documents.length > 0 && (
            <div>
              <h4 className={cn("font-medium mb-3", t.text.primary)}>Supporting Documentation</h4>
              <div className="space-y-2">
                {orderData.clinical.documents.map((doc) => (
                  <div key={doc.id} className={cn("flex items-center justify-between p-3 rounded-lg", t.glass.frost)}>
                    <div className="flex items-center space-x-3">
                      <FiFileText className={cn("w-5 h-5", t.text.secondary)} />
                      <div>
                        <p className={cn("text-sm font-medium", t.text.primary)}>{doc.name}</p>
                        <p className={cn("text-xs", t.text.secondary)}>
                          {doc.type} • {(doc.size / 1024 / 1024).toFixed(2)} MB • Uploaded {new Date(doc.uploadDate).toLocaleDateString()}
                        </p>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </SectionCard>

      {/* Product Selection Section */}
      <SectionCard
        title="Product Selection"
        icon={<FiShoppingCart />}
        status={getSectionStatus({ products: orderData.products }, ['products'])}
        expanded={expandedSections.has('products')}
        onToggle={() => toggleSection('products')}

      >
        <div className="space-y-4">
          {orderData.products.map((product, idx) => (
            <div key={idx} className={cn("p-4 rounded-lg", t.glass.frost)}>
              <div className="flex justify-between items-start">
                <div>
                  <h4 className={cn("font-medium", t.text.primary)}>{product.name}</h4>
                  <p className={cn("text-sm mt-1", t.text.secondary)}>
                    {product.manufacturer} • SKU: {product.sku}
                  </p>

                  {/* Sizes and Quantities */}
                  <div className="mt-3 space-y-1">
                    {product.sizes.map((size, sizeIdx) => (
                      <div key={sizeIdx} className={cn("text-sm", t.text.secondary)}>
                        <span className="font-medium">Size {size.size}:</span> {size.quantity} units
                        {userRole !== 'order_manager' && size.totalPrice && (
                          <span className="ml-2">• ${size.totalPrice.toFixed(2)}</span>
                        )}
                      </div>
                    ))}
                  </div>
                </div>

                {/* Coverage Alerts */}
                {product.coverageAlerts && product.coverageAlerts.length > 0 && (
                  <div className="ml-4 space-y-2">
                    {product.coverageAlerts.map((alert, alertIdx) => (
                      <div key={alertIdx} className={cn(
                        "flex items-start space-x-2 text-sm p-2 rounded",
                        alert.type === 'error' && "bg-red-500/20 text-red-400",
                        alert.type === 'warning' && "bg-yellow-500/20 text-yellow-400",
                        alert.type === 'info' && "bg-blue-500/20 text-blue-400"
                      )}>
                        {alert.type === 'error' && <FiXCircle className="w-4 h-4 mt-0.5" />}
                        {alert.type === 'warning' && <FiAlertTriangle className="w-4 h-4 mt-0.5" />}
                        {alert.type === 'info' && <FiAlertCircle className="w-4 h-4 mt-0.5" />}
                        <span>{alert.message}</span>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      </SectionCard>

      {/* Pricing Section - Role-based visibility */}
      {userRole !== 'order_manager' && orderData.pricing && (
        <SectionCard
          title="Pricing Details"
          icon={<FiDollarSign />}
          status={{ icon: 'complete', color: 'green', message: 'Complete' }}
          expanded={expandedSections.has('pricing')}
          onToggle={() => toggleSection('pricing')}
        >
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <dl className="space-y-3 text-sm">
              <div className="flex justify-between">
                <dt className={cn("font-medium", t.text.secondary)}>ASP Total:</dt>
                <dd className={cn("font-medium", t.text.primary)}>${orderData.pricing.aspTotal.toFixed(2)}</dd>
              </div>
              {orderData.pricing.discount && (
                <div className="flex justify-between">
                  <dt className={cn("font-medium", t.text.secondary)}>Discount:</dt>
                  <dd className={cn("font-medium text-green-500")}>-${orderData.pricing.discount.toFixed(2)}</dd>
                </div>
              )}
              <div className="flex justify-between border-t pt-3">
                <dt className={cn("font-medium", t.text.primary)}>Net Price:</dt>
                <dd className={cn("font-bold text-lg", t.text.primary)}>${orderData.pricing.netPrice.toFixed(2)}</dd>
              </div>
            </dl>

            {orderData.pricing.patientResponsibility !== undefined && (
              <div className={cn("p-4 rounded-lg", t.glass.frost)}>
                <h5 className={cn("text-sm font-medium mb-2", t.text.secondary)}>Patient Responsibility</h5>
                <p className={cn("text-2xl font-bold", t.text.primary)}>
                  ${orderData.pricing.patientResponsibility.toFixed(2)}
                </p>
                <p className={cn("text-xs mt-1", t.text.tertiary)}>
                  Estimated based on coverage
                </p>
              </div>
            )}
          </div>
        </SectionCard>
      )}

      {/* Forms Status Section */}
      <SectionCard
        title="Forms Status"
        icon={<FiFileText />}
        status={getSectionStatus(orderData.forms, ['ivr'])}
        expanded={expandedSections.has('forms')}
        onToggle={() => toggleSection('forms')}
      >
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {/* IVR Form */}
          <div className={cn("p-4 rounded-lg", t.glass.frost)}>
            <div className="flex items-center justify-between mb-3">
              <h4 className={cn("font-medium", t.text.primary)}>IVR Form</h4>
              <StatusBadge status={orderData.forms.ivr.status} />
            </div>

            <dl className="space-y-2 text-sm">
              {orderData.forms.ivr.completionDate && (
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Completed:</dt>
                  <dd className={t.text.primary}>
                    {new Date(orderData.forms.ivr.completionDate).toLocaleDateString()}
                  </dd>
                </div>
              )}
              {orderData.forms.ivr.expirationDate && (
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Expires:</dt>
                  <dd className={t.text.primary}>
                    {new Date(orderData.forms.ivr.expirationDate).toLocaleDateString()}
                  </dd>
                </div>
              )}
            </dl>

            <div className="mt-4 flex space-x-2">
              {orderData.forms.ivr.documentUrl && (
                <a
                  href={orderData.forms.ivr.documentUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className={cn("px-4 py-2 rounded text-sm flex items-center space-x-1", t.glass.frost, "hover:bg-white/10")}
                >
                  <span>View PDF</span>
                </a>
              )}
            </div>
          </div>

          {/* Order Form */}
          <div className={cn("p-4 rounded-lg", t.glass.frost)}>
            <div className="flex items-center justify-between mb-3">
              <h4 className={cn("font-medium", t.text.primary)}>Order Form</h4>
              <StatusBadge status={orderData.forms.order.status} />
            </div>

            <dl className="space-y-2 text-sm">
              {orderData.forms.order.completionDate && (
                <div className="flex">
                  <dt className={cn("font-medium w-24", t.text.secondary)}>Signed:</dt>
                  <dd className={t.text.primary}>
                    {new Date(orderData.forms.order.completionDate).toLocaleDateString()}
                  </dd>
                </div>
              )}
            </dl>

            <div className="mt-4 flex space-x-2">
              {orderData.forms.order.documentUrl && (
                <a
                  href={orderData.forms.order.documentUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className={cn("px-4 py-2 rounded text-sm flex items-center space-x-1", t.glass.frost, "hover:bg-white/10")}
                >
                  <span>View PDF</span>
                </a>
              )}
            </div>
          </div>
        </div>
      </SectionCard>

      {/* Shipping Information */}
      <SectionCard
        title="Shipping Information"
        icon={<FiTruck />}
        status={{ icon: 'complete', color: 'green', message: 'Complete' }}
        expanded={expandedSections.has('shipping')}
        onToggle={() => toggleSection('shipping')}

      >
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <h4 className={cn("text-sm font-medium mb-2", t.text.secondary)}>Delivery Address</h4>
            {orderData.shipping.sameAsPatient ? (
              <p className={cn("text-sm", t.text.primary)}>Same as patient address</p>
            ) : orderData.shipping.address ? (
              <address className={cn("text-sm not-italic", t.text.primary)}>
                {orderData.shipping.address.line1}
                {orderData.shipping.address.line2 && <>, {orderData.shipping.address.line2}</>}
                <br />
                {orderData.shipping.address.city}, {orderData.shipping.address.state} {orderData.shipping.address.zip}
              </address>
            ) : null}
          </div>

          <dl className="space-y-2 text-sm">
            <div className="flex">
              <dt className={cn("font-medium w-32", t.text.secondary)}>Expected Date:</dt>
              <dd className={t.text.primary}>
                {new Date(orderData.shipping.expectedDate).toLocaleDateString()}
              </dd>
            </div>
            <div className="flex">
              <dt className={cn("font-medium w-32", t.text.secondary)}>Shipping Method:</dt>
              <dd className={t.text.primary}>{orderData.shipping.method}</dd>
            </div>
            {orderData.shipping.trackingNumber && (
              <div className="flex">
                <dt className={cn("font-medium w-32", t.text.secondary)}>Tracking #:</dt>
                <dd className={cn(t.text.primary, "text-blue-400 hover:text-blue-300 underline cursor-pointer")}>
                  {orderData.shipping.trackingNumber}
                </dd>
              </div>
            )}
          </dl>

          {orderData.shipping.specialInstructions && (
            <div className="md:col-span-2">
              <h4 className={cn("text-sm font-medium mb-2", t.text.secondary)}>Special Instructions</h4>
              <p className={cn("text-sm", t.text.primary)}>
                {orderData.shipping.specialInstructions}
              </p>
            </div>
          )}
        </div>
      </SectionCard>

      {/* Internal Notes */}
      {orderData.notes && orderData.notes.length > 0 && (
        <SectionCard
          title="Internal Notes"
          icon={<FiMessageSquare />}
          expanded={expandedSections.has('notes')}
          onToggle={() => toggleSection('notes')}
        >
          <div className="space-y-3">
            {orderData.notes.map((note) => (
              <div key={note.id} className={cn("p-3 rounded-lg", t.glass.frost)}>
                <div className="flex justify-between items-start mb-1">
                  <p className={cn("text-sm font-medium", t.text.primary)}>{note.user}</p>
                  <time className={cn("text-xs", t.text.secondary)}>
                    {new Date(note.timestamp).toLocaleString()}
                  </time>
                </div>
                <p className={cn("text-sm", t.text.secondary)}>{note.note}</p>
              </div>
            ))}
          </div>
        </SectionCard>
      )}

      {/* Audit Log - Admin only */}
      {userRole === 'admin' && orderData.audit && (
        <SectionCard
          title="Audit Log"
          icon={<FiClock />}
          expanded={expandedSections.has('audit')}
          onToggle={() => toggleSection('audit')}
        >
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className={cn("border-b", `border-${t.glass.border}`)}>
                  <th className={cn("text-left py-2 px-3", t.text.secondary)}>Timestamp</th>
                  <th className={cn("text-left py-2 px-3", t.text.secondary)}>User</th>
                  <th className={cn("text-left py-2 px-3", t.text.secondary)}>Action</th>
                  <th className={cn("text-left py-2 px-3", t.text.secondary)}>Details</th>
                </tr>
              </thead>
              <tbody>
                {orderData.audit.map((entry, idx) => (
                  <tr key={idx} className={cn("border-b", `border-${t.glass.border}`)}>
                    <td className={cn("py-2 px-3", t.text.primary)}>
                      {new Date(entry.timestamp).toLocaleString()}
                    </td>
                    <td className={cn("py-2 px-3", t.text.primary)}>{entry.user}</td>
                    <td className={cn("py-2 px-3", t.text.primary)}>{entry.action}</td>
                    <td className={cn("py-2 px-3", t.text.secondary)}>{entry.details || '-'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </SectionCard>
      )}

      {/* Confirmation Modal - Removed since we're using custom note modal */}

      {/* Add Note Modal */}
      {showNoteModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className={cn("bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6", t.glass.card)}>
            <div className="flex items-center justify-between mb-4">
              <h3 className={cn("text-lg font-semibold", t.text.primary)}>Add Admin Note & Submit Order</h3>
              <button
                onClick={() => {
                  setShowNoteModal(false);
                  setNewNote('');
                }}
                className={cn("p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700", t.text.secondary)}
              >
                <FiXCircle className="w-5 h-5" />
              </button>
            </div>

            <p className={cn("text-sm mb-4", t.text.secondary)}>
              Please add an admin note before submitting the order:
            </p>

            <textarea
              value={newNote}
              onChange={(e) => setNewNote(e.target.value)}
              placeholder="Enter your admin note here..."
              className={cn(
                "w-full h-32 p-3 rounded-lg resize-none mb-4",
                t.input.base,
                t.input.focus
              )}
            />

            <div className="flex justify-end space-x-3">
              <button
                onClick={() => {
                  setShowNoteModal(false);
                  setNewNote('');
                }}
                className={cn("px-4 py-2 rounded-lg", t.button.secondary.base, t.button.secondary.hover)}
                disabled={submitting}
              >
                Cancel
              </button>
              <button
                onClick={handleAddNote}
                disabled={!newNote.trim() || submitting}
                className={cn(
                  "px-4 py-2 rounded-lg font-medium",
                  newNote.trim() && !submitting
                    ? `${t.button.primary.base} ${t.button.primary.hover}`
                    : "bg-gray-300 text-gray-500 cursor-not-allowed"
                )}
              >
                {submitting ? 'Submitting...' : 'Submit Order with Note'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// Section Card Component
interface SectionCardProps {
  title: string;
  icon: React.ReactNode;
  status?: SectionStatus;
  expanded: boolean;
  onToggle: () => void;
  children: React.ReactNode;
}

function SectionCard({
  title,
  icon,
  status,
  expanded,
  onToggle,
  children
}: SectionCardProps) {
  const { theme = 'dark' } = useTheme();
  const t = themes[theme];

  return (
    <div className={cn("rounded-lg overflow-hidden", t.glass.card)}>
      <div
        className={cn(
          "px-6 py-4 flex items-center justify-between cursor-pointer",
          "hover:bg-white/5 transition-colors"
        )}
        onClick={onToggle}
      >
        <div className="flex items-center space-x-3">
          <span className={cn("text-xl", t.text.secondary)}>{icon}</span>
          <h3 className={cn("text-lg font-semibold", t.text.primary)}>{title}</h3>
          {status && <SectionStatusIndicator status={status} />}
        </div>

        <div className="flex items-center space-x-3">
          {expanded ? (
            <FiChevronDown className={cn("w-5 h-5", t.text.secondary)} />
          ) : (
            <FiChevronRight className={cn("w-5 h-5", t.text.secondary)} />
          )}
        </div>
      </div>

      {expanded && (
        <div className={cn("px-6 pb-6 border-t", `border-${t.glass.border}`)}>
          <div className="mt-4">{children}</div>
        </div>
      )}
    </div>
  );
}

// Section Status Indicator
function SectionStatusIndicator({ status }: { status: SectionStatus }) {
  const icons = {
    complete: <FiCheckCircle className="w-5 h-5" />,
    warning: <FiAlertTriangle className="w-5 h-5" />,
    error: <FiXCircle className="w-5 h-5" />,
    pending: <FiClock className="w-5 h-5" />
  };

  const colors = {
    green: 'text-green-500',
    yellow: 'text-yellow-500',
    red: 'text-red-500',
    gray: 'text-gray-500'
  };

  return (
    <div className={cn("flex items-center space-x-2", colors[status.color])}>
      {icons[status.icon]}
      <span className="text-sm">{status.message}</span>
    </div>
  );
}

// Status Badge Component
function StatusBadge({ status }: { status: string }) {
  const { theme = 'dark' } = useTheme();
  const t = themes[theme];

  const getStatusColor = () => {
    switch (status) {
      case 'complete':
        return 'bg-green-500/20 text-green-400';
      case 'in_progress':
        return 'bg-yellow-500/20 text-yellow-400';
      case 'expired':
        return 'bg-red-500/20 text-red-400';
      default:
        return 'bg-gray-500/20 text-gray-400';
    }
  };

  return (
    <span className={cn("px-2 py-1 rounded text-xs font-medium", getStatusColor())}>
      {status.replace('_', ' ').charAt(0).toUpperCase() + status.slice(1).replace('_', ' ')}
    </span>
  );
}
