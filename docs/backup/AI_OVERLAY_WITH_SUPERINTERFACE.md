# AI Overlay with Superinterface Client Tools

## Overview

The MSC Wound Care Portal features a custom AI overlay that integrates with Superinterface client tools, providing an intelligent assistant that can:

- Process documents (insurance cards, clinical notes, wound photos) with OCR
- Fill form fields automatically with extracted data
- Generate pre-filled Insurance Verification Request (IVR) forms
- Validate form data and identify missing required fields
- Guide users through complex workflows

## Architecture

### Custom UI Layer
- **AIOverlay Component**: Custom React component with glassmorphic design
- **MarkdownFormRenderer**: Dynamic form rendering from markdown syntax
- **Voice Integration**: Speech-to-text and text-to-speech capabilities
- **Document Upload**: Drag-and-drop document processing

### Superinterface Client Tools
- **client-tools.ts**: Defines client-side tools accessible by the AI
- **Tool Handlers**: JavaScript functions that execute on the client
- **Form Integration**: Direct manipulation of form fields

### Backend Integration
- **AiChatController**: Handles AI conversations and tool execution
- **AzureFoundryService**: Azure OpenAI integration
- **Tool Result Processing**: Handles responses from client tool execution

## Available Client Tools

### 1. processDocument
Process uploaded documents with OCR to extract medical data.

```javascript
window.SuperinterfaceClientTools.processDocument.handler({
  file: "base64_encoded_file_data",
  documentType: "insurance_card" // or "clinical_note", "wound_photo"
})
```

### 2. fillQuickRequestField
Fill specific fields in the Quick Request form.

```javascript
window.SuperinterfaceClientTools.fillQuickRequestField.handler({
  fieldName: "patient_first_name",
  value: "John",
  section: "patient" // optional: "patient", "provider", "insurance", "clinical"
})
```

### 3. generateIVRForm
Generate a pre-filled Insurance Verification Request form.

```javascript
window.SuperinterfaceClientTools.generateIVRForm.handler({
  formData: currentFormData,
  templateType: "wound_care" // optional: "standard", "wound_care", "dme"
})
```

### 4. getCurrentFormData
Get the current state of form data.

```javascript
window.SuperinterfaceClientTools.getCurrentFormData.handler({
  section: "all" // optional: "all", "patient", "provider", "insurance", "clinical"
})
```

### 5. validateFormData
Validate form data and identify missing fields.

```javascript
window.SuperinterfaceClientTools.validateFormData.handler({
  section: "all" // optional: specific section to validate
})
```

## AI Assistant Integration

The AI assistant is aware of these tools and can suggest their use. When the AI wants to use a tool, it returns a special response format:

```json
{
  "reply": "I'll help you process that insurance card.",
  "tool_call": {
    "tool": "processDocument",
    "parameters": {
      "file": "...",
      "documentType": "insurance_card"
    }
  }
}
```

The frontend automatically executes the tool and sends the result back to the AI for further processing.

## Usage Examples

### Example 1: Processing an Insurance Card

1. User uploads an insurance card
2. AI suggests using the `processDocument` tool
3. Tool extracts data via OCR
4. AI presents extracted data in a markdown form
5. User confirms or edits the data
6. AI uses `fillQuickRequestField` to populate the form

### Example 2: Creating a Product Request

1. User: "I need to create a product request"
2. AI: "I'll help you with that. Let me check what information we have."
3. AI uses `getCurrentFormData` to check existing data
4. AI uses `validateFormData` to identify missing fields
5. AI guides user through filling missing information
6. AI uses `generateIVRForm` to create the final document

## Configuration

### Environment Variables

```env
# Azure OpenAI Configuration
AZURE_OPENAI_ENDPOINT=https://your-resource.openai.azure.com/
AZURE_OPENAI_API_KEY=your-api-key
AZURE_OPENAI_DEPLOYMENT_NAME=gpt-4o
AZURE_OPENAI_API_VERSION=2024-02-15-preview

# Optional: Superinterface API Key (if using Superinterface cloud features)
SUPERINTERFACE_API_KEY=your-superinterface-key
```

### Laravel Configuration

The configuration is stored in `config/superinterface.php`:

```php
return [
    'azure_openai' => [
        'endpoint' => env('AZURE_OPENAI_ENDPOINT'),
        'api_key' => env('AZURE_OPENAI_API_KEY'),
        'deployment_name' => env('AZURE_OPENAI_DEPLOYMENT_NAME', 'gpt-4o'),
        'api_version' => env('AZURE_OPENAI_API_VERSION', '2024-02-15-preview'),
    ],
];
```

## Extending the System

### Adding New Client Tools

1. Add the tool definition in `client-tools.ts`:

```javascript
window.SuperinterfaceClientTools.myNewTool = {
  description: "Description of what the tool does",
  inputSchema: {
    type: "object",
    properties: {
      param1: { type: "string", description: "Parameter description" }
    },
    required: ["param1"]
  },
  handler: async (params) => {
    // Tool implementation
    return {
      success: true,
      data: result,
      message: "Tool executed successfully"
    };
  }
};
```

2. Update the AI system prompt in `AiChatController.php` to include the new tool.

3. The AI will automatically be able to suggest using the new tool.

### Customizing the AI Behavior

Edit the system prompt in `AiChatController::getSystemPrompt()` to customize:
- Available tools and when to use them
- Response format and tone
- Domain-specific knowledge
- Compliance requirements

## Security Considerations

- All tool executions happen client-side
- PHI data is never sent to external AI services
- Tool results are sanitized before sending to the backend
- CSRF protection on all API endpoints
- Role-based permissions for tool usage

## Troubleshooting

### Tools Not Executing
- Check browser console for errors
- Verify client-tools.ts is loaded
- Ensure tool names match exactly

### AI Not Suggesting Tools
- Review system prompt for tool descriptions
- Check AI response parsing in `parseAIResponse()`
- Verify tool parameters match schema

### Form Fields Not Updating
- Check field selectors in `fillQuickRequestField`
- Verify React event dispatching
- Ensure form component listens for updates 