import React, { useState, useEffect } from 'react';
import { FiCheck, FiAlertCircle, FiLoader } from 'react-icons/fi';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

/**
 * DocuSeal IVR Form Component
 *
 * This component creates and embeds DocuSeal forms for IVR (Independent Verification Request)
 * signatures using embedded text field tags. The component pre-fills PDF templates that contain
 * {{field_name}} tags with data from the Quick Request form.
 *
 * Key Features:
 * - Pre-fills embedded text field tags ({{patient_first_name}}, {{product_name}}, etc.)
 * - Handles manufacturer-specific fields and requirements
 * - Manages iframe embedding and completion detection
 * - Converts boolean values to Yes/No for checkbox fields
 * - Supports conditional fields and various field types
 *
 * PDF Template Requirements:
 * - Use {{field_name}} syntax for embedded text fields
 * - Include manufacturer-specific fields as needed
 * - Set appropriate field types (text, checkbox, signature, etc.)
 * - Configure required fields and validation rules
 *
 * See docs/docuseal-embedded-fields-guide.md for complete field reference
 */

interface DocuSealIVRFormProps {
  formData: any;
  templateId: string;
  onComplete: (submissionId: string) => void;
  onError: (error: string) => void;
  episodeId?: string; // Optional episode ID to link the submission
}

export default function DocuSealIVRForm({
  formData,
  templateId,
  onComplete,
  onError,
  episodeId
}: DocuSealIVRFormProps) {
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

  const [loading, setLoading] = useState(true);
  const [signingUrl, setSigningUrl] = useState<string | null>(null);
  const [submissionId, setSubmissionId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    createDocuSealSubmission();
  }, []);

  const createDocuSealSubmission = async () => {
    try {
      // Prepare the fields to pre-fill in the IVR form
      const fields = prepareIVRFields(formData);

      // Get CSRF token
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

      const response = await fetch('/quickrequest/docuseal/create-submission', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken || '',
        },
        credentials: 'include',
        body: JSON.stringify({
          template_id: templateId,
          email: formData.provider_email || 'provider@example.com',
          name: formData.provider_name || 'Provider',
          send_email: false,
          fields: fields,
          external_id: episodeId || formData.episode_id || null, // Include episode ID for webhook linking
        })
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Failed to create submission');
      }

      const data = await response.json();

      if (data.signing_url) {
        setSigningUrl(data.signing_url);
        setSubmissionId(data.submission_id);
        setLoading(false);
      } else {
        throw new Error('No signing URL received');
      }
    } catch (err) {
      console.error('Error creating DocuSeal submission:', err);
      setError(err.message || 'Failed to create signature form');
      setLoading(false);
      onError(err.message || 'Failed to create signature form');
    }
  };

  const prepareIVRFields = (data: any) => {
    // Map QuickRequest data to embedded text field tags
    // These field names should match the {{field_name}} tags in your DocuSeal PDF templates
    const fields: Record<string, any> = {
      // Patient Information - matches {{patient_first_name}}, {{patient_last_name}}, etc.
      'patient_first_name': data.patient_first_name || '',
      'patient_last_name': data.patient_last_name || '',
      'patient_dob': data.patient_dob || '',
      'patient_member_id': data.patient_member_id || '',
      'patient_gender': data.patient_gender || '',

      // Patient Address - matches {{patient_address_line1}}, {{patient_city}}, etc.
      'patient_address_line1': data.patient_address_line1 || '',
      'patient_address_line2': data.patient_address_line2 || '',
      'patient_city': data.patient_city || '',
      'patient_state': data.patient_state || '',
      'patient_zip': data.patient_zip || '',
      'patient_phone': data.patient_phone || '',

      // Insurance Information - matches {{payer_name}}, {{payer_id}}, etc.
      'payer_name': data.payer_name || '',
      'payer_id': data.payer_id || '',
      'insurance_type': data.insurance_type || '',

      // Product Information - matches {{product_name}}, {{product_code}}, etc.
      'product_name': data.product_name || '',
      'product_code': data.product_code || '',
      'manufacturer': data.manufacturer || '',
      'size': data.size || '',
      'quantity': String(data.quantity || '1'),

      // Service Information - matches {{expected_service_date}}, {{wound_type}}, etc.
      'expected_service_date': data.expected_service_date || '',
      'wound_type': data.wound_type || '',
      'place_of_service': data.place_of_service || '',

      // Provider Information - matches {{provider_name}}, {{provider_npi}}, etc.
      'provider_name': data.provider_name || '',
      'provider_npi': data.provider_npi || '',
      'signature_date': data.signature_date || new Date().toISOString().split('T')[0],

      // Clinical Attestations - matches {{failed_conservative_treatment}}, etc.
      // For checkbox fields in PDF: use 'Yes'/'No' or 'true'/'false' based on your template
      'failed_conservative_treatment': data.failed_conservative_treatment ? 'Yes' : 'No',
      'information_accurate': data.information_accurate ? 'Yes' : 'No',
      'medical_necessity_established': data.medical_necessity_established ? 'Yes' : 'No',
      'maintain_documentation': data.maintain_documentation ? 'Yes' : 'No',
      'authorize_prior_auth': data.authorize_prior_auth ? 'Yes' : 'No',

      // Common IVR Form Fields
      'todays_date': new Date().toLocaleDateString('en-US'),
      'current_time': new Date().toLocaleTimeString('en-US'),

      // Facility Information
      'facility_name': data.facility_name || '',
      'facility_address': data.facility_address || '',

      // Manufacturer-specific fields
      ...mapManufacturerFields(data.manufacturer_fields || {})
    };

    // Add caregiver information if present - matches {{caregiver_name}}, etc.
    if (data.caregiver_name) {
      fields['caregiver_name'] = data.caregiver_name;
      fields['caregiver_relationship'] = data.caregiver_relationship || '';
      fields['caregiver_phone'] = data.caregiver_phone || '';
    }

    // Add verbal order information if present - matches {{verbal_order_*}} tags
    if (data.verbal_order) {
      fields['verbal_order_received_from'] = data.verbal_order.received_from || '';
      fields['verbal_order_date'] = data.verbal_order.date || '';
      fields['verbal_order_documented_by'] = data.verbal_order.documented_by || '';
    }

    return fields;
  };

  const mapManufacturerFields = (manufacturerFields: Record<string, any>) => {
    // Map manufacturer-specific fields to embedded text field tags
    // These should match {{manufacturer_*}} or specific field tags in your PDF templates
    const mapped: Record<string, any> = {};

    Object.entries(manufacturerFields).forEach(([key, value]) => {
      // For embedded text field tags, use the exact field name that matches your PDF
      // Examples: {{physician_attestation}}, {{amnio_amp_size}}, {{shipping_speed_required}}
      let mappedValue = value;

      // Convert boolean values to Yes/No for checkbox fields
      if (typeof value === 'boolean') {
        mappedValue = value ? 'Yes' : 'No';
      } else if (value === true || value === 'true') {
        mappedValue = 'Yes';
      } else if (value === false || value === 'false') {
        mappedValue = 'No';
      } else {
        mappedValue = String(value || '');
      }

      // Use the original field name to match PDF tags like {{physician_attestation}}
      mapped[key] = mappedValue;

      // Also create a manufacturer-prefixed version for fallback
      mapped[`manufacturer_${key}`] = mappedValue;
    });

    return mapped;
  };

  const handleIframeMessage = (event: MessageEvent) => {
    // Listen for completion messages from DocuSeal iframe
    if (event.origin !== 'https://docuseal.com') return;

    if (event.data.type === 'docuseal:submission_completed') {
      onComplete(submissionId || '');
    }
  };

  useEffect(() => {
    window.addEventListener('message', handleIframeMessage);
    return () => {
      window.removeEventListener('message', handleIframeMessage);
    };
  }, [submissionId]);

  if (loading) {
    return (
      <div className="flex items-center justify-center p-8">
        <div className="text-center">
          <FiLoader className="h-8 w-8 animate-spin mx-auto mb-4 text-indigo-600" />
          <p className={cn("text-sm", t.text.secondary)}>
            Preparing signature form...
          </p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className={cn("p-6 rounded-lg", t.glass.panel)}>
        <div className="flex items-start">
          <FiAlertCircle className="h-5 w-5 text-red-500 mt-0.5 mr-3" />
          <div>
            <h3 className={cn("text-sm font-medium", t.text.primary)}>
              Error Loading Signature Form
            </h3>
            <p className={cn("text-sm mt-1", t.text.secondary)}>
              {error}
            </p>
            <button
              onClick={createDocuSealSubmission}
              className={cn(
                "mt-3 px-4 py-2 rounded text-sm font-medium",
                t.button.secondary
              )}
            >
              Try Again
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="h-full">
      {signingUrl ? (
        <iframe
          src={signingUrl}
          className="w-full h-full border-0 rounded-lg"
          style={{ minHeight: '600px' }}
          allow="camera; microphone"
          title="DocuSeal IVR Signature Form"
        />
      ) : (
        <div className={cn("p-6 text-center", t.glass.panel)}>
          <FiAlertCircle className="h-8 w-8 mx-auto mb-3 text-yellow-500" />
          <p className={cn("text-sm", t.text.secondary)}>
            Unable to load signature form. Please try again.
          </p>
        </div>
      )}
    </div>
  );
}
