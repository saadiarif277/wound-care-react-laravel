import { useState, useCallback, useEffect, useRef } from 'react';
import axios from 'axios';
import {
  DocusealIVRData,
  SignatureData,
  GeneratedDocument,
  PatientInsuranceData,
  ClinicalBillingData,
  ProductSelectionData,
} from '@/types/quickRequest';

interface UseDocusealIVRProps {
  patientData?: PatientInsuranceData;
  clinicalData?: ClinicalBillingData;
  productData?: ProductSelectionData;
  onSave?: (data: DocusealIVRData) => void;
  onNext?: (data: DocusealIVRData) => void;
}

interface DocusealTemplate {
  id: string;
  name: string;
  manufacturer: string;
  type: 'insurance_verification' | 'order_form' | 'consent' | 'other';
  fields: string[];
  requiredSignatures: ('patient' | 'provider' | 'officeManager')[];
}

interface SignatureCapture {
  canvas: HTMLCanvasElement | null;
  isDrawing: boolean;
  context: CanvasRenderingContext2D | null;
}

export function useDocusealIVR({
  patientData,
  clinicalData,
  productData,
  onSave,
  onNext,
}: UseDocusealIVRProps) {
  const [template, setTemplate] = useState<DocusealTemplate | null>(null);
  const [isLoadingTemplate, setIsLoadingTemplate] = useState(false);
  
  const [formFields, setFormFields] = useState<Record<string, any>>({});
  const [isGeneratingDocument, setIsGeneratingDocument] = useState(false);
  
  const [signatures, setSignatures] = useState<{
    patient?: SignatureData;
    provider?: SignatureData;
    officeManager?: SignatureData;
  }>({});
  
  const [generatedDocuments, setGeneratedDocuments] = useState<GeneratedDocument[]>([]);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  
  // Signature canvas refs
  const patientCanvasRef = useRef<HTMLCanvasElement>(null);
  const providerCanvasRef = useRef<HTMLCanvasElement>(null);
  const officeManagerCanvasRef = useRef<HTMLCanvasElement>(null);
  
  const [signatureCaptures, setSignatureCaptures] = useState<{
    patient: SignatureCapture;
    provider: SignatureCapture;
    officeManager: SignatureCapture;
  }>({
    patient: { canvas: null, isDrawing: false, context: null },
    provider: { canvas: null, isDrawing: false, context: null },
    officeManager: { canvas: null, isDrawing: false, context: null },
  });

  // Load template based on manufacturer
  useEffect(() => {
    if (!productData?.manufacturer?.id) return;

    setIsLoadingTemplate(true);
    axios
      .get(`/api/v1/docuseal/templates`, {
        params: {
          manufacturer: productData.manufacturer.code,
          type: 'insurance_verification',
        },
      })
      .then(response => {
        const templates = response.data.data;
        if (templates.length > 0) {
          setTemplate(templates[0]);
          initializeFormFields(templates[0]);
        }
      })
      .catch(error => {
        console.error('Failed to load Docuseal template:', error);
      })
      .finally(() => {
        setIsLoadingTemplate(false);
      });
  }, [productData?.manufacturer?.id]);

  // Initialize form fields with data from previous steps
  const initializeFormFields = useCallback((template: DocusealTemplate) => {
    const fields: Record<string, any> = {};

    // Map patient data
    if (patientData) {
      fields.patientFirstName = patientData.patient.firstName;
      fields.patientLastName = patientData.patient.lastName;
      fields.patientDateOfBirth = patientData.patient.dateOfBirth;
      fields.patientGender = patientData.patient.gender;
      fields.patientPhone = patientData.patient.phone;
      fields.patientEmail = patientData.patient.email;
      fields.patientAddressLine1 = patientData.patient.address.line[0];
      fields.patientAddressLine2 = patientData.patient.address.line[1] || '';
      fields.patientCity = patientData.patient.address.city;
      fields.patientState = patientData.patient.address.state;
      fields.patientZip = patientData.patient.address.postalCode;
      
      // Insurance data
      fields.primaryInsuranceType = patientData.insurance.primary.type;
      fields.primaryPolicyNumber = patientData.insurance.primary.policyNumber;
      fields.primarySubscriberId = patientData.insurance.primary.subscriberId;
      fields.primaryPayorName = patientData.insurance.primary.payorName;
      
      if (patientData.insurance.secondary) {
        fields.hasSecondaryInsurance = true;
        fields.secondaryInsuranceType = patientData.insurance.secondary.type;
        fields.secondaryPolicyNumber = patientData.insurance.secondary.policyNumber;
      }
    }

    // Map clinical data
    if (clinicalData) {
      fields.providerName = clinicalData.provider.name;
      fields.providerNPI = clinicalData.provider.npi;
      fields.facilityName = clinicalData.facility.name;
      fields.facilityNPI = clinicalData.facility.npi;
      fields.primaryDiagnosis = `${clinicalData.diagnosis.primary.code} - ${clinicalData.diagnosis.primary.display}`;
      fields.woundType = clinicalData.woundDetails.woundType;
      fields.woundLocation = clinicalData.woundDetails.woundLocation;
      fields.woundSize = `${clinicalData.woundDetails.woundSize.length} x ${clinicalData.woundDetails.woundSize.width} ${clinicalData.woundDetails.woundSize.unit}`;
    }

    // Map product data
    if (productData) {
      fields.manufacturerName = productData.manufacturer.name;
      fields.products = productData.products.map(p => ({
        name: p.name,
        code: p.code,
        quantity: p.quantity,
        frequency: p.frequency,
        sizes: p.sizes.filter(s => s.quantity > 0).map(s => `${s.size}: ${s.quantity} ${s.unit}`).join(', '),
      }));
      fields.deliveryMethod = productData.deliveryPreferences.method;
    }

    // Add date fields
    fields.dateOfService = new Date().toISOString().split('T')[0];
    fields.formCompletedDate = new Date().toISOString().split('T')[0];

    setFormFields(fields);
  }, [patientData, clinicalData, productData]);

  // Update form field
  const updateFormField = useCallback((field: string, value: any) => {
    setFormFields(prev => ({
      ...prev,
      [field]: value,
    }));
  }, []);

  // Initialize signature canvas
  const initializeSignatureCanvas = useCallback((
    type: 'patient' | 'provider' | 'officeManager',
    canvasRef: React.RefObject<HTMLCanvasElement>
  ) => {
    if (!canvasRef.current) return;

    const canvas = canvasRef.current;
    const context = canvas.getContext('2d');
    if (!context) return;

    // Set canvas size
    canvas.width = canvas.offsetWidth;
    canvas.height = canvas.offsetHeight;

    // Set drawing styles
    context.strokeStyle = '#000000';
    context.lineWidth = 2;
    context.lineCap = 'round';
    context.lineJoin = 'round';

    setSignatureCaptures(prev => ({
      ...prev,
      [type]: {
        canvas,
        context,
        isDrawing: false,
      },
    }));
  }, []);

  // Handle signature drawing
  const startDrawing = useCallback((
    type: 'patient' | 'provider' | 'officeManager',
    event: React.MouseEvent | React.TouchEvent
  ) => {
    const capture = signatureCaptures[type];
    if (!capture.context || !capture.canvas) return;

    capture.context.beginPath();

    const rect = capture.canvas.getBoundingClientRect();
    const x = 'touches' in event
      ? event.touches[0].clientX - rect.left
      : event.clientX - rect.left;
    const y = 'touches' in event
      ? event.touches[0].clientY - rect.top
      : event.clientY - rect.top;

    capture.context.moveTo(x, y);

    setSignatureCaptures(prev => ({
      ...prev,
      [type]: {
        ...prev[type],
        isDrawing: true,
      },
    }));
  }, [signatureCaptures]);

  const draw = useCallback((
    type: 'patient' | 'provider' | 'officeManager',
    event: React.MouseEvent | React.TouchEvent
  ) => {
    const capture = signatureCaptures[type];
    if (!capture.isDrawing || !capture.context || !capture.canvas) return;

    const rect = capture.canvas.getBoundingClientRect();
    const x = 'touches' in event
      ? event.touches[0].clientX - rect.left
      : event.clientX - rect.left;
    const y = 'touches' in event
      ? event.touches[0].clientY - rect.top
      : event.clientY - rect.top;

    capture.context.lineTo(x, y);
    capture.context.stroke();
  }, [signatureCaptures]);

  const stopDrawing = useCallback((
    type: 'patient' | 'provider' | 'officeManager'
  ) => {
    setSignatureCaptures(prev => ({
      ...prev,
      [type]: {
        ...prev[type],
        isDrawing: false,
      },
    }));
  }, []);

  // Clear signature
  const clearSignature = useCallback((
    type: 'patient' | 'provider' | 'officeManager'
  ) => {
    const capture = signatureCaptures[type];
    if (!capture.context || !capture.canvas) return;

    capture.context.clearRect(0, 0, capture.canvas.width, capture.canvas.height);
    
    setSignatures(prev => {
      const updated = { ...prev };
      delete updated[type];
      return updated;
    });
  }, [signatureCaptures]);

  // Capture signature
  const captureSignature = useCallback((
    type: 'patient' | 'provider' | 'officeManager',
    signerName: string
  ) => {
    const capture = signatureCaptures[type];
    if (!capture.canvas) return;

    // Check if signature is empty
    const imageData = capture.context?.getImageData(
      0,
      0,
      capture.canvas.width,
      capture.canvas.height
    );
    
    if (!imageData) return;

    const isEmpty = !imageData.data.some((channel, index) => {
      return index % 4 !== 3 && channel !== 0;
    });

    if (isEmpty) {
      alert('Please provide a signature');
      return;
    }

    // Convert to base64
    const signatureImage = capture.canvas.toDataURL('image/png');

    setSignatures(prev => ({
      ...prev,
      [type]: {
        signedAt: new Date().toISOString(),
        signedBy: signerName,
        signatureImage,
        ipAddress: window.location.hostname, // In production, get from server
        userAgent: navigator.userAgent,
      },
    }));
  }, [signatureCaptures]);

  // Generate preview
  const generatePreview = useCallback(async () => {
    if (!template) return;

    setIsGeneratingDocument(true);
    try {
      const response = await axios.post('/api/v1/docuseal/preview', {
        templateId: template.id,
        fields: formFields,
        signatures,
      });

      setPreviewUrl(response.data.data.url);
    } catch (error) {
      console.error('Failed to generate preview:', error);
      alert('Failed to generate document preview');
    } finally {
      setIsGeneratingDocument(false);
    }
  }, [template, formFields, signatures]);

  // Generate final document
  const generateDocument = useCallback(async () => {
    if (!template) return;

    // Validate required signatures
    const missingSignatures = template.requiredSignatures.filter(
      sig => !signatures[sig]
    );
    
    if (missingSignatures.length > 0) {
      alert(`Missing required signatures: ${missingSignatures.join(', ')}`);
      return;
    }

    setIsGeneratingDocument(true);
    try {
      const response = await axios.post('/api/v1/docuseal/generate', {
        templateId: template.id,
        fields: formFields,
        signatures,
        episodeId: null, // Will be set when episode is created
      });

      const document: GeneratedDocument = response.data.data;
      setGeneratedDocuments(prev => [...prev, document]);

      return document;
    } catch (error) {
      console.error('Failed to generate document:', error);
      throw error;
    } finally {
      setIsGeneratingDocument(false);
    }
  }, [template, formFields, signatures]);

  // Submit and proceed
  const submitAndProceed = useCallback(async () => {
    try {
      // Generate final document
      const document = await generateDocument();
      if (!document) return;

      const ivrData: DocusealIVRData = {
        template: template!,
        fields: formFields,
        signatures,
        documents: [document],
      };

      // Save progress
      if (onSave) {
        await onSave(ivrData);
      }

      // Proceed to next step
      if (onNext) {
        await onNext(ivrData);
      }
    } catch (error) {
      console.error('Failed to submit IVR data:', error);
      alert('Failed to generate insurance verification form. Please try again.');
    }
  }, [template, formFields, signatures, generateDocument, onSave, onNext]);

  // Check if all required fields are filled
  const isFormComplete = useCallback(() => {
    if (!template) return false;

    // Check required fields
    const requiredFields = template.fields.filter(f => !f.includes('optional'));
    const missingFields = requiredFields.filter(f => !formFields[f]);
    
    if (missingFields.length > 0) return false;

    // Check required signatures
    const missingSignatures = template.requiredSignatures.filter(
      sig => !signatures[sig]
    );
    
    return missingSignatures.length === 0;
  }, [template, formFields, signatures]);

  // Initialize canvases on mount
  useEffect(() => {
    if (patientCanvasRef.current) {
      initializeSignatureCanvas('patient', patientCanvasRef);
    }
    if (providerCanvasRef.current) {
      initializeSignatureCanvas('provider', providerCanvasRef);
    }
    if (officeManagerCanvasRef.current) {
      initializeSignatureCanvas('officeManager', officeManagerCanvasRef);
    }
  }, [initializeSignatureCanvas]);

  return {
    // Template
    template,
    isLoadingTemplate,

    // Form fields
    formFields,
    updateFormField,

    // Signatures
    signatures,
    patientCanvasRef,
    providerCanvasRef,
    officeManagerCanvasRef,
    startDrawing,
    draw,
    stopDrawing,
    clearSignature,
    captureSignature,

    // Documents
    generatedDocuments,
    isGeneratingDocument,
    previewUrl,
    generatePreview,
    generateDocument,

    // Submit
    submitAndProceed,
    isFormComplete: isFormComplete(),
    canProceed: isFormComplete() && !isGeneratingDocument,
  };
}