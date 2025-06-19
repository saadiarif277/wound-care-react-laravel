import { useState, useRef } from 'react';
import { FiUpload, FiFile, FiX } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

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

  const [uploadedFiles, setUploadedFiles] = useState<{
    face_sheet: { name: string; size: number } | null;
    clinical_notes: { name: string; size: number } | null;
    wound_photo: { name: string; size: number } | null;
  }>({
    face_sheet: null,
    clinical_notes: null,
    wound_photo: null,
  });

  const [isVerbalOrder, setIsVerbalOrder] = useState(false);

  const faceSheetRef = useRef<HTMLInputElement>(null);
  const clinicalNotesRef = useRef<HTMLInputElement>(null);
  const woundPhotoRef = useRef<HTMLInputElement>(null);

  const handleFileUpload = (fileType: 'face_sheet' | 'clinical_notes' | 'wound_photo', file: File) => {
    // Update form data
    updateFormData({ [fileType]: file });

    // Update uploaded files state for display
    setUploadedFiles(prev => ({
      ...prev,
      [fileType]: {
        name: file.name,
        size: file.size,
      },
    }));
  };

  const removeFile = (fileType: 'face_sheet' | 'clinical_notes' | 'wound_photo') => {
    updateFormData({ [fileType]: null });
    setUploadedFiles(prev => ({
      ...prev,
      [fileType]: null,
    }));
  };

  const formatFileSize = (bytes: number) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
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

      {/* Required Attachments */}
      <div className={cn("p-6 rounded-lg", t.glass.card)}>
        <h3 className={cn("text-lg font-medium mb-4", t.text.primary)}>
          📄 Required Attachments
        </h3>
        <p className={cn("text-sm mb-4", t.text.secondary)}>
          Please confirm the following are attached/uploaded:
        </p>

        <div className="space-y-4">
          {/* Face Sheet */}
          <div>
            <div className="flex items-center justify-between mb-2">
              <label className={cn("text-sm font-medium", t.text.secondary)}>
                ☐ Face Sheet / Demographics
              </label>
              {uploadedFiles.face_sheet && (
                <button
                  onClick={() => removeFile('face_sheet')}
                  className={cn("text-sm flex items-center", "text-red-500 hover:text-red-600")}
                >
                  <FiX className="mr-1" /> Remove
                </button>
              )}
            </div>
            <div
              onClick={() => faceSheetRef.current?.click()}
              className={cn(
                "border-2 border-dashed rounded-lg p-4 text-center cursor-pointer hover:border-indigo-500 transition-colors",
                theme === 'dark' ? 'border-gray-600' : 'border-gray-300',
                uploadedFiles.face_sheet ? 'border-green-500 bg-green-50/5' : ''
              )}
            >
              <input
                ref={faceSheetRef}
                type="file"
                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                className="hidden"
                onChange={(e) => {
                  const file = e.target.files?.[0];
                  if (file) handleFileUpload('face_sheet', file);
                }}
              />
              {uploadedFiles.face_sheet ? (
                <div className="flex items-center justify-center">
                  <FiFile className="h-8 w-8 text-green-500 mr-2" />
                  <div className="text-left">
                    <p className={cn("text-sm font-medium", t.text.primary)}>
                      {uploadedFiles.face_sheet.name}
                    </p>
                    <p className={cn("text-xs", t.text.secondary)}>
                      {formatFileSize(uploadedFiles.face_sheet.size)}
                    </p>
                  </div>
                </div>
              ) : (
                <div>
                  <FiUpload className="mx-auto h-8 w-8 text-gray-400 mb-2" />
                  <p className={cn("text-sm", t.text.secondary)}>
                    Click to upload Face Sheet
                  </p>
                </div>
              )}
            </div>
          </div>

          {/* Clinical Notes */}
          <div>
            <div className="flex items-center justify-between mb-2">
              <label className={cn("text-sm font-medium", t.text.secondary)}>
                ☐ Clinical Notes (if prior auth needed)
              </label>
              {uploadedFiles.clinical_notes && (
                <button
                  onClick={() => removeFile('clinical_notes')}
                  className={cn("text-sm flex items-center", "text-red-500 hover:text-red-600")}
                >
                  <FiX className="mr-1" /> Remove
                </button>
              )}
            </div>
            <div
              onClick={() => clinicalNotesRef.current?.click()}
              className={cn(
                "border-2 border-dashed rounded-lg p-4 text-center cursor-pointer hover:border-indigo-500 transition-colors",
                theme === 'dark' ? 'border-gray-600' : 'border-gray-300',
                uploadedFiles.clinical_notes ? 'border-green-500 bg-green-50/5' : ''
              )}
            >
              <input
                ref={clinicalNotesRef}
                type="file"
                accept=".pdf,.doc,.docx"
                className="hidden"
                onChange={(e) => {
                  const file = e.target.files?.[0];
                  if (file) handleFileUpload('clinical_notes', file);
                }}
              />
              {uploadedFiles.clinical_notes ? (
                <div className="flex items-center justify-center">
                  <FiFile className="h-8 w-8 text-green-500 mr-2" />
                  <div className="text-left">
                    <p className={cn("text-sm font-medium", t.text.primary)}>
                      {uploadedFiles.clinical_notes.name}
                    </p>
                    <p className={cn("text-xs", t.text.secondary)}>
                      {formatFileSize(uploadedFiles.clinical_notes.size)}
                    </p>
                  </div>
                </div>
              ) : (
                <div>
                  <FiUpload className="mx-auto h-8 w-8 text-gray-400 mb-2" />
                  <p className={cn("text-sm", t.text.secondary)}>
                    Click to upload Clinical Notes
                  </p>
                </div>
              )}
            </div>
          </div>

          {/* Wound Photo */}
          <div>
            <div className="flex items-center justify-between mb-2">
              <label className={cn("text-sm font-medium", t.text.secondary)}>
                ☐ Wound Photo (if available)
              </label>
              {uploadedFiles.wound_photo && (
                <button
                  onClick={() => removeFile('wound_photo')}
                  className={cn("text-sm flex items-center", "text-red-500 hover:text-red-600")}
                >
                  <FiX className="mr-1" /> Remove
                </button>
              )}
            </div>
            <div
              onClick={() => woundPhotoRef.current?.click()}
              className={cn(
                "border-2 border-dashed rounded-lg p-4 text-center cursor-pointer hover:border-indigo-500 transition-colors",
                theme === 'dark' ? 'border-gray-600' : 'border-gray-300',
                uploadedFiles.wound_photo ? 'border-green-500 bg-green-50/5' : ''
              )}
            >
              <input
                ref={woundPhotoRef}
                type="file"
                accept="image/*"
                className="hidden"
                onChange={(e) => {
                  const file = e.target.files?.[0];
                  if (file) handleFileUpload('wound_photo', file);
                }}
              />
              {uploadedFiles.wound_photo ? (
                <div className="flex items-center justify-center">
                  <FiFile className="h-8 w-8 text-green-500 mr-2" />
                  <div className="text-left">
                    <p className={cn("text-sm font-medium", t.text.primary)}>
                      {uploadedFiles.wound_photo.name}
                    </p>
                    <p className={cn("text-xs", t.text.secondary)}>
                      {formatFileSize(uploadedFiles.wound_photo.size)}
                    </p>
                  </div>
                </div>
              ) : (
                <div>
                  <FiUpload className="mx-auto h-8 w-8 text-gray-400 mb-2" />
                  <p className={cn("text-sm", t.text.secondary)}>
                    Click to upload Wound Photo
                  </p>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

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
              I authorize MSC to initiate and follow up on prior authorization if required
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