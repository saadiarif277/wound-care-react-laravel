import api from '@/lib/api';

// Helper function to generate markdown from extracted data
function generateMarkdownForm(documentType: string, extractedData: any): string {
  // ... (implementation of generateMarkdownForm from previous versions)
}

// Main tool definitions
const tools = {
  processDocument: {
    // ...
    handler: async (params: { file: string, documentType: string }) => {
      try {
        const response = await api.post('/document/analyze', {
          fileData: params.file,
          type: params.documentType
        });
        const markdown = generateMarkdownForm(params.documentType, response.data);
        return {
          success: true,
          data: response.data,
          markdown: markdown,
          message: `Successfully extracted data from ${params.documentType.replace('_', ' ')}`
        };
      } catch (error: any) {
        // ...
      }
    }
  },
  // ... other tools like fillQuickRequestField
  generateIVRForm: {
    // ...
    handler: async (params: { formData: any, templateType?: string }) => {
      try {
        const response = await api.post('/quick-request/generate-ivr', {
          formData: params.formData,
          templateType: params.templateType || 'wound_care',
          source: 'ai-assistant'
        });
        return {
          success: true,
          submissionUrl: response.data.url,
          submissionId: response.data.id,
          message: 'IVR form generated successfully. Check your email for the signing link.'
        };
      } catch (error: any) {
        // ...
      }
    }
  },
  // ... other tools
};

// Assign to window object
if (typeof window !== 'undefined') {
  (window as any).SuperinterfaceClientTools = tools;
}

export default tools;