# Chat-Based IVR Form Filling Design

## Overview
Design a conversational interface that allows users to complete IVR forms through chat, integrating Docuseal API for form generation and Superinterface.ai for the chat UI.

## Current System Analysis

### Existing QuickRequest Flow
1. **Multi-step Form UI** (CreateNew.tsx)
   - Patient & Insurance
   - Clinical Validation
   - Select Products
   - Complete IVR Form (embedded Docuseal)
   - Review & Submit

2. **Backend Orchestration** (QuickRequestOrchestrator.php)
   - Creates FHIR resources
   - Manages data flow
   - Generates Docuseal submissions

3. **Docuseal Integration**
   - Embedded form using `<DocusealForm>` React component
   - Pre-filled with data from backend
   - Webhook handling for completion

## Proposed Chat-Based Architecture

### 1. Conversational Flow Design
```
User: "I need to create an order for wound care products"
AI: "I'll help you with that. Let me gather some information..."

[Progressive data collection through conversation]
- Patient details
- Insurance information
- Clinical assessment
- Product selection

AI: "I've collected all the information. Let me prepare the IVR form for you..."
[Generate and present Docuseal form]
```

### 2. Technical Architecture

#### Frontend Components

##### A. Chat Interface Component
```typescript
// components/ChatIVRInterface.tsx
interface ChatIVRInterfaceProps {
  onComplete: (episodeId: string) => void;
}

const ChatIVRInterface: React.FC<ChatIVRInterfaceProps> = ({ onComplete }) => {
  const [messages, setMessages] = useState<Message[]>([]);
  const [formData, setFormData] = useState<Partial<QuickRequestFormData>>({});
  const [currentStep, setCurrentStep] = useState<FormStep>('initial');
  
  // Integration with Superinterface.ai for chat UI
  // Progressive form filling logic
  // Docuseal API integration
};
```

##### B. Markdown Form Components
Using Superinterface.ai's Markdown capabilities:
```markdown
## Patient Information
Please provide the following details:

- **First Name**: [input:patient_first_name]
- **Last Name**: [input:patient_last_name]
- **Date of Birth**: [date:patient_dob]
- **Gender**: [select:patient_gender|Male|Female|Other]

[button:Continue|next_step]
```

##### C. Progressive Data Collection
```typescript
const formSteps = {
  initial: {
    prompt: "Welcome! I'll help you create a wound care order. First, are you a new or returning patient?",
    options: ['new_request', 'reverification', 'additional_applications']
  },
  patient_info: {
    prompt: "Let's start with patient information.",
    fields: ['first_name', 'last_name', 'dob', 'gender']
  },
  insurance: {
    prompt: "Now I need insurance details. You can upload a card or enter manually.",
    fields: ['insurance_name', 'member_id', 'plan_type']
  },
  clinical: {
    prompt: "Tell me about the wound that needs treatment.",
    fields: ['wound_type', 'location', 'size', 'duration']
  },
  products: {
    prompt: "Based on your clinical information, here are recommended products.",
    component: 'ProductSelector'
  }
};
```

#### Backend API Endpoints

##### A. Chat Session Management
```php
// app/Http/Controllers/Api/ChatIVRController.php
class ChatIVRController extends Controller
{
    public function startSession(Request $request)
    {
        $session = ChatIVRSession::create([
            'user_id' => auth()->id(),
            'status' => 'active',
            'current_step' => 'initial'
        ]);
        
        return response()->json([
            'session_id' => $session->id,
            'initial_prompt' => $this->getStepPrompt('initial')
        ]);
    }
    
    public function processMessage(Request $request, $sessionId)
    {
        $session = ChatIVRSession::findOrFail($sessionId);
        $message = $request->input('message');
        $attachments = $request->file('attachments');
        
        // Process based on current step
        $response = $this->processStep(
            $session->current_step,
            $message,
            $attachments
        );
        
        return response()->json($response);
    }
    
    public function generateIVRForm(Request $request, $sessionId)
    {
        $session = ChatIVRSession::findOrFail($sessionId);
        
        // Use existing orchestrator to create draft episode
        $episode = $this->orchestrator->createDraftEpisode($session->form_data);
        
        // Generate Docuseal submission
        $submission = $this->docusealService->createSubmission(
            $episode,
            $session->form_data
        );
        
        return response()->json([
            'submission_url' => $submission->url,
            'submission_id' => $submission->id
        ]);
    }
}
```

##### B. Enhanced Docuseal Service
```php
// app/Services/DocusealChatService.php
class DocusealChatService extends DocusealService
{
    public function createPrefilledSubmission(array $formData, string $templateId)
    {
        // Map chat-collected data to Docuseal fields
        $mappedData = $this->mapChatDataToDocusealFields($formData);
        
        // Create submission with pre-filled data
        $response = Http::withToken($this->apiKey)
            ->post($this->baseUrl . '/submissions', [
                'template_id' => $templateId,
                'send_email' => false, // Don't send email for chat flow
                'submitters' => [
                    [
                        'email' => $formData['provider_email'],
                        'fields' => $mappedData
                    ]
                ]
            ]);
            
        return $response->json();
    }
    
    private function mapChatDataToDocusealFields(array $formData): array
    {
        // Use AI mapping service to match chat fields to template fields
        return $this->fieldMappingService->mapFields(
            $formData,
            $this->getTemplateFields($templateId)
        );
    }
}
```

### 3. Integration Points

#### A. Superinterface.ai Client Tools
```javascript
// public/js/chat-ivr-tools.js
window.fillPatientInfo = function(data) {
  // Fill patient information fields
  document.getElementById('patient_first_name').value = data.first_name;
  document.getElementById('patient_last_name').value = data.last_name;
  // ... more fields
};

window.uploadInsuranceCard = function() {
  // Trigger file upload dialog
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/*';
  input.onchange = (e) => {
    const file = e.target.files[0];
    // Process with Azure Document Intelligence
    processInsuranceCard(file);
  };
  input.click();
};

window.selectProducts = function(criteria) {
  // Open product selector with pre-filtered options
  const products = filterProducts(criteria);
  displayProductSelector(products);
};
```

#### B. Markdown Components Library
```typescript
// components/ChatMarkdown/index.tsx
export const ChatMarkdownComponents = {
  input: ({ id, placeholder }) => (
    <input
      id={id}
      placeholder={placeholder}
      className="chat-input"
      onChange={(e) => updateFormData(id, e.target.value)}
    />
  ),
  
  select: ({ id, options }) => (
    <select
      id={id}
      className="chat-select"
      onChange={(e) => updateFormData(id, e.target.value)}
    >
      {options.map(opt => <option key={opt} value={opt}>{opt}</option>)}
    </select>
  ),
  
  button: ({ text, action }) => (
    <button
      className="chat-button"
      onClick={() => handleAction(action)}
    >
      {text}
    </button>
  ),
  
  fileUpload: ({ id, accept }) => (
    <FileUploadZone
      id={id}
      accept={accept}
      onUpload={(file) => handleFileUpload(id, file)}
    />
  )
};
```

### 4. User Experience Flow

1. **Initiate Chat**
   - User clicks "Chat with AI Assistant"
   - System creates chat session

2. **Progressive Data Collection**
   - AI asks targeted questions
   - User provides information conversationally
   - System validates and stores data incrementally

3. **Smart Features**
   - Insurance card, clinical notes, or demographics OCR via upload
   - Auto-completion from previous orders

4. **Form Generation**
   - AI summarizes collected information
   - Generates pre-filled Docuseal form
   - User reviews and signs

5. **Completion**
   - Form submission via webhook
   - Order creation in system
   - Confirmation to user

### 5. Implementation Tasks

- [x] Analyze current QuickRequest flow
- [x] Research Superinterface.ai capabilities
- [x] Research Docuseal API
- [x] Design chat-based architecture
- [ ] Create ChatIVRInterface component
- [ ] Implement chat session API endpoints
- [ ] Create Markdown component library
- [ ] Integrate Docuseal API for direct submission
- [ ] Add insurance card OCR in chat flow
- [ ] Implement progressive form validation
- [ ] Create chat-to-Docuseal field mapping
- [ ] Add error handling and recovery
- [ ] Test end-to-end flow
- [ ] Create user documentation

### 6. Benefits

1. **Improved UX**
   - Natural conversation flow
   - No complex multi-step forms
   - Contextual help and guidance

2. **Flexibility**
   - Skip irrelevant questions
   - Dynamic field presentation
   - Easy to add new fields

3. **Integration**
   - Reuses existing backend services
   - Compatible with current Docuseal setup
   - Maintains HIPAA compliance

4. **Efficiency**
   - Faster for experienced users
   - Better for mobile devices
   - Reduces form abandonment

## Review

This design creates a conversational interface for IVR form completion that:
- Integrates with existing backend orchestration
- Uses Docuseal API for direct form creation
- Leverages Superinterface.ai for interactive chat UI
- Maintains all security and compliance requirements
- Provides a modern, user-friendly experience

The chat-based approach can coexist with the traditional form UI, giving users choice in how they complete their orders.