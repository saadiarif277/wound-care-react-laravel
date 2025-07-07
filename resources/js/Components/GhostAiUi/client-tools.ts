/**
 * Superinterface Client Tools for Document Processing and Form Filling
 * These functions are registered with Superinterface and accessible by the AI assistant
 */

import { fetchWithCSRF } from '@/utils/csrf';

// Register Superinterface client tools
declare global {
  interface Window {
    SuperinterfaceClientTools: any;
    processDocument: (file: File, documentType: string) => Promise<any>;
    fillQuickRequestField: (fieldName: string, value: any) => void;
    attachDocumentToEpisode: (file: File, documentType: string, episodeId?: string) => Promise<any>;
    getCurrentQuickRequestData: () => any;
    validateFormField: (fieldName: string, value: any) => Promise<boolean>;
    generateIVRForm: (formData: any) => Promise<any>;
  }
}

// Define Superinterface client tools following their documentation
window.SuperinterfaceClientTools = {
  processDocument: {
    description: "Process an uploaded document (insurance card, clinical note, wound photo, etc.) and extract relevant medical data using OCR",
    inputSchema: {
      type: "object",
      properties: {
        file: { type: "string", description: "File path or base64 encoded file data" },
        documentType: { 
          type: "string", 
          enum: ["insurance_card", "clinical_note", "wound_photo", "prescription", "other"],
          description: "Type of medical document being processed" 
        }
      },
      required: ["file", "documentType"]
    },
    handler: async (params: { file: string, documentType: string }) => {
      try {
        // Convert file parameter to actual File object or handle base64
        const response = await fetch('/api/document/analyze', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          },
          body: JSON.stringify({
            fileData: params.file,
            type: params.documentType
          })
        });

        if (!response.ok) {
          throw new Error(`Document processing failed: ${response.statusText}`);
        }

        const result = await response.json();
        const markdown = generateMarkdownForm(params.documentType, result.data);
        
        return {
          success: true,
          data: result.data,
          markdown: markdown,
          message: `Successfully extracted data from ${params.documentType.replace('_', ' ')}`
        };
      } catch (error: any) {
        return {
          success: false,
          error: error?.message || 'Unknown error',
          message: "Failed to process document"
        };
      }
    }
  },

  fillQuickRequestField: {
    description: "Fill a specific field in the Quick Request form with extracted data",
    inputSchema: {
      type: "object",
      properties: {
        fieldName: { type: "string", description: "Name of the form field to fill" },
        value: { type: "string", description: "Value to fill in the field" },
        section: { 
          type: "string", 
          enum: ["patient", "provider", "insurance", "clinical"],
          description: "Form section the field belongs to"
        }
      },
      required: ["fieldName", "value"]
    },
    handler: async (params: { fieldName: string, value: string, section?: string }) => {
      try {
        // Dispatch custom event for React components to listen
        const event = new CustomEvent('quickRequestFieldUpdate', {
          detail: {
            field: params.fieldName,
            value: params.value,
            section: params.section,
            source: 'ai-assistant'
          }
        });
        window.dispatchEvent(event);

        // Also try direct DOM manipulation for non-React forms
        const input = document.querySelector(`[name="${params.fieldName}"], [id="${params.fieldName}"]`) as HTMLInputElement;
        if (input) {
          input.value = params.value;
          input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        return {
          success: true,
          message: `Updated ${params.fieldName} with: ${params.value}`
        };
      } catch (error: any) {
        return {
          success: false,
          error: error?.message || 'Unknown error',
          message: "Failed to update form field"
        };
      }
    }
  },

  generateIVRForm: {
    description: "Generate a pre-filled Insurance Verification Request (IVR) form using DocuSeal with the current form data",
    inputSchema: {
      type: "object",
      properties: {
        formData: { 
          type: "object", 
          description: "Complete form data including patient, provider, insurance, and clinical information" 
        },
        templateType: {
          type: "string",
          enum: ["standard", "wound_care", "dme"],
          description: "Type of IVR template to use"
        }
      },
      required: ["formData"]
    },
    handler: async (params: { formData: any, templateType?: string }) => {
      try {
        const response = await fetchWithCSRF('/api/quick-request/generate-ivr', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            formData: params.formData,
            templateType: params.templateType || 'wound_care',
            source: 'ai-assistant'
          }),
        });

        if (!response.ok) {
          throw new Error(`IVR generation failed: ${response.statusText}`);
        }

        const result = await response.json();
        
        return {
          success: true,
          submissionUrl: result.data.url,
          submissionId: result.data.id,
          message: 'IVR form generated successfully. Check your email for the signing link.'
        };
      } catch (error: any) {
        return {
          success: false,
          error: error?.message || 'Unknown error',
          message: "Failed to generate IVR form"
        };
      }
    }
  },

  getCurrentFormData: {
    description: "Get the current state of the Quick Request form data",
    inputSchema: {
      type: "object",
      properties: {
        section: {
          type: "string",
          enum: ["all", "patient", "provider", "insurance", "clinical"],
          description: "Which section of the form to retrieve"
        }
      }
    },
    handler: async (params: { section?: string }) => {
      try {
        const formData = window.getCurrentQuickRequestData();
        
        if (params.section && params.section !== 'all') {
          return {
            success: true,
            data: formData?.[params.section] || {},
            message: `Retrieved ${params.section} section data`
          };
        }

        return {
          success: true,
          data: formData || {},
          message: "Retrieved complete form data"
        };
      } catch (error: any) {
        return {
          success: false,
          error: error?.message || 'Unknown error',
          message: "Failed to retrieve form data"
        };
      }
    }
  },

  validateFormData: {
    description: "Validate form data and suggest missing required fields",
    inputSchema: {
      type: "object",
      properties: {
        section: {
          type: "string",
          enum: ["all", "patient", "provider", "insurance", "clinical"],
          description: "Which section to validate"
        }
      }
    },
    handler: async (params: { section?: string }) => {
      try {
        const formData = window.getCurrentQuickRequestData();
        const response = await fetchWithCSRF('/api/quick-request/validate', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            formData: formData,
            section: params.section || 'all'
          }),
        });

        const result = await response.json();
        
        return {
          success: true,
          validationResults: result.validation,
          errors: result.errors || [],
          warnings: result.warnings || [],
          missingFields: result.missingRequired || [],
          message: `Validation complete. ${result.errors?.length || 0} errors, ${result.warnings?.length || 0} warnings.`
        };
      } catch (error: any) {
        return {
          success: false,
          error: error?.message || 'Unknown error',
          message: "Failed to validate form data"
        };
      }
    }
  }
};

/**
 * Process a document with OCR and return extracted data with markdown form
 */
window.processDocument = async function(file: File, documentType: string) {
  try {
    const formData = new FormData();
    formData.append('document', file);
    formData.append('type', documentType);

    const response = await fetchWithCSRF('/api/document/analyze', {
      method: 'POST',
      body: formData,
    });

    if (!response.ok) {
      throw new Error(`Document processing failed: ${response.statusText}`);
    }

    const result = await response.json();
    
    // Generate markdown form based on document type and extracted data
    const markdown = generateMarkdownForm(documentType, result.data);
    
    return {
      success: true,
      data: result.data,
      markdown: markdown,
      documentType: documentType,
      filename: file.name
    };
  } catch (error) {
    console.error('Document processing error:', error);
    return {
      success: false,
      error: (error && typeof error === 'object' && 'message' in error) ? (error as any).message : String(error),
       documentType: documentType,
      filename: file.name
    };
  }
};

/**
 * Fill a Quick Request form field
 */
window.fillQuickRequestField = function(fieldName: string, value: any) {
  // Dispatch custom event that the Quick Request form can listen to
  const event = new CustomEvent('quickRequestFieldUpdate', {
    detail: {
      field: fieldName,
      value: value,
      source: 'ai-assistant'
    }
  });
  
  window.dispatchEvent(event);
  
  // Also try to directly update common form elements
  const input = document.querySelector(`[name="${fieldName}"], [id="${fieldName}"]`) as HTMLInputElement;
  if (input) {
    input.value = value;
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }
};

/**
 * Attach a document to the current or specified episode
 */
window.attachDocumentToEpisode = async function(file: File, documentType: string, episodeId?: string) {
  try {
    // Get current episode ID if not provided
    const currentEpisodeId = episodeId || getCurrentEpisodeId();
    
    if (!currentEpisodeId) {
      throw new Error('No episode ID available');
    }

    const formData = new FormData();
    formData.append('file', file);
    formData.append('type', documentType);
    formData.append('metadata', JSON.stringify({
      documentType: documentType,
      uploadedAt: new Date().toISOString(),
      source: 'ai-assistant'
    }));

    const response = await fetchWithCSRF(`/api/episodes/${currentEpisodeId}/documents`, {
      method: 'POST',
      body: formData,
    });

    if (!response.ok) {
      throw new Error(`Failed to attach document: ${response.statusText}`);
    }

    const result = await response.json();
    
    return {
      success: true,
      documentId: result.data.id,
      episodeId: currentEpisodeId,
      message: 'Document attached successfully'
    };
  } catch (error: any) {
    console.error('Document attachment error:', error);
    return {
      success: false,
      error: error?.message || 'Unknown error'
    };
  }
};

/**
 * Get current Quick Request form data
 */
window.getCurrentQuickRequestData = function() {
  // Check if we're on a Quick Request page
  const quickRequestForm = document.querySelector('[data-quick-request-form]');
  if (!quickRequestForm) {
    return null;
  }

  // Collect all form data
  const formData: any = {};
  const inputs = quickRequestForm.querySelectorAll('input, select, textarea');
  
  inputs.forEach((input) => {
    const element = input as HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;
    if (element.name) {
      formData[element.name] = element.value;
    }
  });

  // Also get data from React state if available
  const reactData = (window as any).__quickRequestData;
  if (reactData) {
    Object.assign(formData, reactData);
  }

  return formData;
};

/**
 * Validate a form field value
 */
window.validateFormField = async function(fieldName: string, value: any) {
  try {
    const response = await fetchWithCSRF('/api/validation-builder/validate-field', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        field: fieldName,
        value: value,
        context: window.getCurrentQuickRequestData()
      }),
    });

    const result = await response.json();
    return result.valid === true;
  } catch (error: any) {
    console.error('Field validation error:', error);
    return true; // Default to valid if validation fails
  }
};

/**
 * Generate IVR form with pre-filled data
 */
window.generateIVRForm = async function(formData: any) {
  try {
    const response = await fetchWithCSRF('/api/v1/docuseal/generate-prefilled', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        formData: formData,
        source: 'ai-assistant'
      }),
    });

    if (!response.ok) {
      throw new Error(`IVR generation failed: ${response.statusText}`);
    }

    const result = await response.json();
    
    return {
      success: true,
      submissionUrl: result.data.url,
      submissionId: result.data.id,
      message: 'IVR form generated successfully'
    };
  } catch (error: any) {
    console.error('IVR generation error:', error);
    return {
      success: false,
      error: error?.message || 'Unknown error'
    };
  }
};

/**
 * Helper function to get current episode ID
 */
function getCurrentEpisodeId(): string | null {
  // Try to get from URL
  const urlMatch = window.location.pathname.match(/episodes?\/(\d+)/);
  if (urlMatch && urlMatch[1]) {
    return urlMatch[1];
  }

  // Try to get from page data
  const episodeElement = document.querySelector('[data-episode-id]');
  if (episodeElement) {
    return episodeElement.getAttribute('data-episode-id');
  }

  // Try to get from React state
  const reactEpisodeId = (window as any).__currentEpisodeId;
  if (reactEpisodeId) {
    return reactEpisodeId;
  }

  return null;
}

/**
 * Generate markdown form based on document type and extracted data
 */
function generateMarkdownForm(documentType: string, extractedData: any): string {
  switch (documentType) {
    case 'insurance_card':
      return `## ✓ Insurance Card Processed

We found the following information from your insurance card:

**Primary Insurance**
- Payer: [input:primary_insurance_name|${extractedData.payer_name || ''}]
- Member ID: [input:primary_member_id|${extractedData.member_id || ''}]
- Group Number: [input:primary_group_number|${extractedData.group_number || ''}]
- Plan Type: [select:primary_plan_type|FFS|HMO|PPO|Medicare Advantage|${extractedData.plan_type || ''}]

**Patient Information**
- First Name: [input:patient_first_name|${extractedData.patient_first_name || ''}]
- Last Name: [input:patient_last_name|${extractedData.patient_last_name || ''}]
- Date of Birth: [date:patient_dob|${extractedData.patient_dob || ''}]

[button:Confirm Information|confirm_insurance]
[button:Upload Another Card|upload_more]`;

    case 'clinical_note':
      return `## Clinical Information Extracted

We found the following diagnoses:

**Primary Diagnosis**
- ICD-10: [input:primary_diagnosis|${extractedData.primary_diagnosis || ''}]
- Description: [textarea:diagnosis_description|${extractedData.diagnosis_description || ''}]

**Wound Details**
- Location: [select:wound_location|Foot|Leg|Arm|Other|${extractedData.wound_location || ''}]
- Size (cm): [input:wound_size|${extractedData.wound_size || ''}]
- Duration (weeks): [input:duration_weeks|${extractedData.duration_weeks || ''}]

[button:Continue|validate_clinical]`;

    case 'wound_photo':
      return `## Wound Photo Analysis

**Wound Measurements Detected**
- Length: [input:wound_length|${extractedData.length || ''}] cm
- Width: [input:wound_width|${extractedData.width || ''}] cm
- Depth: [input:wound_depth|${extractedData.depth || ''}] cm

**Wound Characteristics**
- Type: [select:wound_type|Pressure Ulcer|Diabetic Foot Ulcer|Venous Ulcer|Arterial Ulcer|${extractedData.wound_type || ''}]
- Stage: [select:wound_stage|Stage 1|Stage 2|Stage 3|Stage 4|${extractedData.stage || ''}]

[button:Confirm Measurements|confirm_wound]`;

    default:
      return `## Document Uploaded

Document processed successfully.

[button:Continue|continue]`;
  }
}

// Export for use in React components
export {
  getCurrentEpisodeId,
  generateMarkdownForm
};