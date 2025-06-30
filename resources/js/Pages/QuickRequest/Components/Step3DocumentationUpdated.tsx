import { useState } from 'react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import DocumentUploadCard from '@/Components/DocumentUploadCard';
import { DocumentUpload } from '@/types/document-upload';

interface Step3Props {
  formData: any;
  updateFormData: (data: any) => void;
  providers: Array<{
    id: number;
    name: string;
    npi?: string;
  }>;
  currentUser: {
    id: number;
    name: string;
    npi?: string;
  };
  errors: Record<string, string>;
}

export default function Step3Documentation({
  formData,
  updateFormData,
  providers,
  currentUser,
  errors
}: Step3Props) {
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

  const [isVerbalOrder, setIsVerbalOrder] = useState(false);
  const [uploadedDocuments, setUploadedDocuments] = useState<DocumentUpload[]>([]);

  const handleDocumentsChange = (documents: DocumentUpload[]) => {
    setUploadedDocuments(documents);
    
    // Map documents to form fields
    documents.forEach(doc => {
      switch (doc.type) {
        case 'demographics':
          updateFormData({ face_sheet: doc.files.primary?.file });
          break;
        case 'chart_notes':
          updateFormData({ clinical_notes: doc.files.primary?.file });
          break;
        case 'insurance_card':
          // This is handled in Step2, but could be included here too
          updateFormData({ 
            wound_photo: doc.files.primary?.file // Using as wound photo alternative
          });
          break;
      }
    });
  };

  const attestations = [
    {
      field: 'failed_conservative_treatment',
      label: 'The wound has failed conservative treatment',
    },
    {
      field: 'information_accurate',
      label: 'The information provided is accurate and complete',
    },
    {
      field: 'medical_necessity_established',
      label: 'Medical necessity has been established',
    },
    {
      field: 'maintain_documentation',
      label: 'I will maintain supporting documentation',
    },
  ];

  return (
    <div className="space-y-6">
      {/* Step Title */}
      <div>
        <h2 className={cn("text-2xl font-bold", t.text.primary)}>
          Step 3: Documentation & Authorization
        </h2>
        <p className={cn("mt-2", t.text.secondary)}>
          Upload required documents and provide authorization
        </p>
      </div>

      {/* Document Upload Section */}
      <DocumentUploadCard
        title="📄 Required Attachments"
        description="Upload face sheet, clinical notes, and other required documentation"
        onDocumentsChange={handleDocumentsChange}
        allowMultiple={true}
        className="mb-6"
      />

      {/* Document Summary */}
      {uploadedDocuments.length > 0 && (
        <div className={cn("p-4 rounded-lg", t.glass.card, "mb-6")}>
          <h4 className={cn("text-sm font-medium mb-2", t.text.primary)}>
            Uploaded Documents Summary
          </h4>
          <ul className="space-y-1">
            <li className={cn("text-sm", t.text.secondary)}>
              ☑️ Face Sheet: {uploadedDocuments.some(d => d.type === 'demographics') ? 'Uploaded' : 'Pending'}
            </li>
            <li className={cn("text-sm", t.text.secondary)}>
              ☑️ Clinical Notes: {uploadedDocuments.some(d => d.type === 'chart_notes') ? 'Uploaded' : 'Pending'}
            </li>
            <li className={cn("text-sm", t.text.secondary)}>
              ☑️ Additional Documents: {uploadedDocuments.filter(d => d.type === 'insurance_card').length}
            </li>
          </ul>
        </div>
      )}

      {/* Clinical Attestation */}
      <div className={cn("p-6 rounded-lg", t.glass.card)}>
        <h3 className={cn("text-lg font-medium mb-4", t.text.primary)}>
          ✅ Clinical Attestation
        </h3>
        <p className={cn("text-sm mb-4", t.text.secondary)}>
          By submitting this order, I attest that:
        </p>

        <div className="space-y-3">
          {attestations.map(({ field, label }) => (
            <div key={field} className="flex items-start">
              <input
                type="checkbox"
                id={field}
                checked={formData[field] || false}
                onChange={(e) => updateFormData({ [field]: e.target.checked })}
                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded mt-0.5"
              />
              <label
                htmlFor={field}
                className={cn("ml-3 text-sm", t.text.secondary)}
              >
                {label}
              </label>
            </div>
          ))}
        </div>

        {errors.attestation && (
          <p className="mt-3 text-sm text-red-500">{errors.attestation}</p>
        )}
      </div>

      {/* Prior Authorization Consent */}
      <div className={cn("p-6 rounded-lg", t.glass.card)}>
        <h3 className={cn("text-lg font-medium mb-4", t.text.primary)}>
          🔐 Prior Authorization Consent
        </h3>

        <div className="space-y-3">
          <div className="flex items-start">
            <input
              type="checkbox"
              id="authorize_prior_auth"
              checked={formData.authorize_prior_auth || false}
              onChange={(e) => updateFormData({ authorize_prior_auth: e.target.checked })}
              className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded mt-0.5"
            />
            <label
              htmlFor="authorize_prior_auth"
              className={cn("ml-3 text-sm", t.text.secondary)}
            >
              I consent to prior authorization processing if required
            </label>
          </div>

          <div className="flex items-start">
            <input
              type="checkbox"
              id="understand_contact"
              checked={formData.understand_contact || false}
              onChange={(e) => updateFormData({ understand_contact: e.target.checked })}
              className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded mt-0.5"
            />
            <label
              htmlFor="understand_contact"
              className={cn("ml-3 text-sm", t.text.secondary)}
            >
              I understand MSC may contact the payer on my behalf
            </label>
          </div>
        </div>
      </div>

      {/* Provider Authorization */}
      <div className={cn("p-6 rounded-lg", t.glass.card)}>
        <h3 className={cn("text-lg font-medium mb-4", t.text.primary)}>
          ✍️ Provider Authorization
        </h3>

        <div className="space-y-4">
          {/* Verbal Order Option */}
          <div className="flex items-center mb-4">
            <input
              type="checkbox"
              id="verbal_order"
              checked={isVerbalOrder}
              onChange={(e) => setIsVerbalOrder(e.target.checked)}
              className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
            />
            <label
              htmlFor="verbal_order"
              className={cn("ml-3 text-sm font-medium", t.text.secondary)}
            >
              This is a verbal order
            </label>
          </div>

          {isVerbalOrder ? (
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                  Verbal order received from Dr.
                </label>
                <input
                  type="text"
                  value={formData.verbal_order?.received_from || ''}
                  onChange={(e) => updateFormData({
                    verbal_order: {
                      ...formData.verbal_order,
                      received_from: e.target.value,
                    },
                  })}
                  className={cn("w-full", t.input.base, t.input.focus)}
                  placeholder="Provider name"
                />
              </div>

              <div>
                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                  Date
                </label>
                <input
                  type="date"
                  value={formData.verbal_order?.date || ''}
                  onChange={(e) => updateFormData({
                    verbal_order: {
                      ...formData.verbal_order,
                      date: e.target.value,
                    },
                  })}
                  className={cn("w-full", t.input.base, t.input.focus)}
                />
              </div>

              <div className="sm:col-span-2">
                <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                  Documented by
                </label>
                <input
                  type="text"
                  value={formData.verbal_order?.documented_by || ''}
                  onChange={(e) => updateFormData({
                    verbal_order: {
                      ...formData.verbal_order,
                      documented_by: e.target.value,
                    },
                  })}
                  className={cn("w-full", t.input.base, t.input.focus)}
                  placeholder="Your name"
                />
              </div>
            </div>
          ) : (
            <div className="space-y-4">
              <div className={cn("p-4 rounded-lg border-2 border-dashed",
                theme === 'dark' ? 'border-gray-600' : 'border-gray-300'
              )}>
                <p className={cn("text-sm font-medium mb-2", t.text.secondary)}>
                  Provider Signature
                </p>
                <div className="h-20 flex items-center justify-center">
                  <p className={cn("text-xs", t.text.tertiary)}>
                    [Signature will be collected in the confirmation step]
                  </p>
                </div>
              </div>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                    Print Name
                  </label>
                  <input
                    type="text"
                    value={formData.provider_name || currentUser.name}
                    onChange={(e) => updateFormData({ provider_name: e.target.value })}
                    className={cn("w-full", t.input.base, t.input.focus)}
                  />
                </div>

                <div>
                  <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                    Date
                  </label>
                  <input
                    type="date"
                    value={formData.signature_date || new Date().toISOString().split('T')[0]}
                    onChange={(e) => updateFormData({ signature_date: e.target.value })}
                    className={cn("w-full", t.input.base, t.input.focus)}
                  />
                </div>

                <div>
                  <label className={cn("block text-sm font-medium mb-1", t.text.secondary)}>
                    NPI
                  </label>
                  <input
                    type="text"
                    value={formData.provider_npi || currentUser.npi || ''}
                    onChange={(e) => updateFormData({ provider_npi: e.target.value })}
                    className={cn("w-full", t.input.base, t.input.focus)}
                    placeholder="1234567890"
                  />
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}