// Placeholder types
interface DocuSealSessionData {
  templateId?: string; // Template ID might be determined by backend based on other data
  orderId: string;      // Crucial for backend to know which order this is for
  documentType: string; // e.g., 'InsuranceVerification', 'OrderForm', 'OnboardingForm'
  // Other relevant data to help backend select template and prefill
  [key: string]: any;
}

interface DocuSealSession {
  sessionId: string; // This would be the DocuSeal Submission ID
  signingUrl?: string; // If admin/provider needs to sign directly via a URL
  // other relevant session properties
  [key: string]: any;
}

interface SignedDocument {
  documentUrl: string;
  signedAt: string | null;
  // other relevant document properties
  [key: string]: any;
}

type SignatureStatus = 'pending' | 'viewed' | 'signed' | 'completed' | 'declined' | 'error' | 'admin_approved' | 'pending_provider_signature'; // Expanded based on DocuSeal Integration.md
type StatusChangeCallback = (status: SignatureStatus) => void;

const LARAVEL_API_BASE_URL = '/api/v1'; // Adjust if your API routes have a different prefix

export class DocuSealService {
  constructor() {}

  // Maps to initializeDocumentWorkflow in Create.tsx
  // Corresponds to POST /api/v1/docuseal/generate on the backend (DocuSealController@generateDocument)
  async createSession(sessionData: DocuSealSessionData): Promise<DocuSealSession> {
    console.log('Requesting DocuSeal session from Laravel backend:', sessionData);

    const response = await fetch(`${LARAVEL_API_BASE_URL}/docuseal/generate`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        // Add X-CSRF-TOKEN header for Laravel
      },
      body: JSON.stringify(sessionData), // Send orderId, documentType, and any other necessary data
    });

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({ message: response.statusText }));
      throw new Error(`API error creating DocuSeal session: ${errorData.message || response.statusText}`);
    }

    const result = await response.json();
    // Expecting backend to return the submission_id (as sessionId) and potentially a signing_url
    return {
      sessionId: result.submission_id, // Adjust based on actual backend response key
      signingUrl: result.signing_url,  // If applicable
      ...result,
    };
  }

  // For SignatureWorkflow.tsx: onStatusChange
  // This needs a robust backend implementation. Laravel should have a webhook endpoint
  // to receive updates from DocuSeal. This frontend method would then either poll
  // for status or connect via WebSockets/SSE if implemented.
  onStatusChange(sessionId: string, callback: StatusChangeCallback): () => void {
    console.log(`Setting up status change listener for DocuSeal session ID (submission ID): ${sessionId}`);

    // Polling placeholder - REPLACE with a more robust solution (WebSockets, SSE, or smarter polling)
    let isCancelled = false;
    const pollInterval = 5000; // Poll every 5 seconds

    const poll = async () => {
      if (isCancelled) return;
      try {
        // GET /api/v1/docuseal/status/{submissionId}
        const response = await fetch(`${LARAVEL_API_BASE_URL}/docuseal/status/${sessionId}`, {
          headers: { 'Accept': 'application/json' /* Add CSRF/Auth if needed */ },
        });
        if (isCancelled) return;

        if (response.ok) {
          const statusData = await response.json();
          callback(statusData.status as SignatureStatus); // Assuming backend returns { status: '...' }
          // Stop polling if completed or failed, or let it continue based on app logic
          if (statusData.status === 'completed' || statusData.status === 'declined' || statusData.status === 'error') {
            return;
          }
        } else {
          console.warn(`Failed to poll status for ${sessionId}: ${response.statusText}`);
        }
      } catch (error) {
        console.error(`Error polling status for ${sessionId}:`, error);
      }
      if (!isCancelled) {
        setTimeout(poll, pollInterval);
      }
    };

    setTimeout(poll, 0); // Start polling

    return () => {
      console.log(`Tearing down status change listener for DocuSeal session: ${sessionId}`);
      isCancelled = true;
    };
  }

  // For SignatureWorkflow.tsx: getSignedDocument
  // Corresponds to GET /api/v1/docuseal/download/{submissionId} or similar on the backend
  async getSignedDocument(sessionId: string): Promise<SignedDocument> {
    console.log(`Fetching signed document details for session ID (submission ID): ${sessionId}`);

    // This might fetch metadata about the signed document, including its URL
    // Or directly trigger a download if the backend endpoint is set up for that.
    // For now, let's assume it returns metadata including the document_url.
    const response = await fetch(`${LARAVEL_API_BASE_URL}/docuseal/submissions/${sessionId}/details`, { // Example endpoint
      headers: {
        'Accept': 'application/json',
        // Add X-CSRF-TOKEN header for Laravel
      },
    });

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({ message: response.statusText }));
      throw new Error(`API error fetching signed DocuSeal document: ${errorData.message || response.statusText}`);
    }

    const docDetails = await response.json();
    // Assuming backend returns { document_url: '...', completed_at: '...' }
    return {
      documentUrl: docDetails.document_url, // Adjust key based on actual response
      signedAt: docDetails.completed_at,   // Adjust key based on actual response
      ...docDetails,
    };
  }
}
