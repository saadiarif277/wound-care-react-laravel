import React, { useEffect, useState } from 'react';
import { 
  SuperinterfaceProvider, 
  Thread, 
  ThreadDialog,
  MarkdownProvider,
  Suggestions
} from '@superinterface/react';
import { useToast } from './hooks/use-toast';
import type { Message } from '@superinterface/react';
import axios from 'axios';

interface SuperinterfaceWrapperProps {
  isVisible: boolean;
  onClose: () => void;
}

const SuperinterfaceWrapper: React.FC<SuperinterfaceWrapperProps> = ({ isVisible, onClose }) => {
  const [threadId, setThreadId] = useState<string | null>(null);
  const { toast } = useToast();

  // Azure OpenAI configuration from Laravel config
  const azureConfig = {
    endpoint: window.Laravel?.superinterface?.azure_openai?.endpoint || '',
    apiKey: window.Laravel?.superinterface?.azure_openai?.api_key || '',
    deploymentName: window.Laravel?.superinterface?.azure_openai?.deployment_name || 'gpt-4o',
    apiVersion: window.Laravel?.superinterface?.azure_openai?.api_version || '2024-02-15-preview',
  };

  // Function definitions for API calls
  const functions = [
    {
      name: 'create_product_request',
      description: 'Create a new product request for wound care supplies',
      parameters: {
        type: 'object',
        properties: {
          patient_name: { type: 'string', description: 'Patient full name' },
          patient_dob: { type: 'string', description: 'Patient date of birth (YYYY-MM-DD)' },
          primary_diagnosis: { type: 'string', description: 'Primary ICD-10 diagnosis code' },
          wound_location: { type: 'string', description: 'Location of the wound' },
          product_name: { type: 'string', description: 'Name of the requested product' },
          quantity: { type: 'integer', description: 'Quantity needed' },
          clinical_justification: { type: 'string', description: 'Clinical reason for the product' }
        },
        required: ['patient_name', 'patient_dob', 'primary_diagnosis', 'product_name']
      },
      handler: async (args: any) => {
        try {
          const response = await axios.post('/api/v1/quick-request/episodes', args);

          if (response.status !== 200) throw new Error('Failed to create product request');
          
          const result = response.data;
          toast({
            title: "Product Request Created",
            description: `Request created with ID: ${result.episode_id}`,
          });
          
          return {
            success: true,
            episode_id: result.episode_id,
            message: `Product request created successfully for ${args.patient_name}`,
          };
        } catch (error) {
          toast({
            title: "Error",
            description: "Failed to create product request",
            variant: "destructive"
          });
          return {
            success: false,
            error: error.message
          };
        }
      }
    },
    {
      name: 'validate_insurance',
      description: 'Validate patient insurance eligibility',
      parameters: {
        type: 'object',
        properties: {
          payer_name: { type: 'string', description: 'Insurance payer name' },
          member_id: { type: 'string', description: 'Member ID' },
          group_number: { type: 'string', description: 'Group number' },
          patient_dob: { type: 'string', description: 'Patient date of birth' }
        },
        required: ['payer_name', 'member_id']
      },
      handler: async (args: any) => {
        try {
          const response = await axios.post('/api/v1/eligibility/check', args);

          const result = response.data;
          return {
            success: true,
            eligible: result.eligible,
            coverage_details: result.coverage_details
          };
        } catch (error) {
          return {
            success: false,
            error: error.message
          };
        }
      }
    },
    {
      name: 'search_products',
      description: 'Search for wound care products',
      parameters: {
        type: 'object',
        properties: {
          query: { type: 'string', description: 'Search query for products' },
          category: { type: 'string', description: 'Product category filter' },
          manufacturer: { type: 'string', description: 'Manufacturer name filter' }
        },
        required: ['query']
      },
      handler: async (args: any) => {
        try {
          const params = new URLSearchParams(args);
          const response = await axios.get(`/api/v1/products/with-sizes?${params}`);

          const result = response.data;
          return {
            success: true,
            products: result.data || []
          };
        } catch (error) {
          return {
            success: false,
            error: error.message
          };
        }
      }
    },
    {
      name: 'check_medicare_coverage',
      description: 'Check Medicare coverage for a specific product and diagnosis',
      parameters: {
        type: 'object',
        properties: {
          product_code: { type: 'string', description: 'HCPCS product code' },
          diagnosis_code: { type: 'string', description: 'ICD-10 diagnosis code' },
          state: { type: 'string', description: 'Two-letter state code' }
        },
        required: ['product_code', 'diagnosis_code', 'state']
      },
      handler: async (args: any) => {
        try {
          const response = await axios.post('/api/v1/medicare-validation/quick-check', args);

          const result = response.data;
          return {
            success: true,
            covered: result.is_valid,
            details: result.validation_summary
          };
        } catch (error) {
          return {
            success: false,
            error: error.message
          };
        }
      }
    }
  ];

  // Assistant configuration
  const assistant = {
    id: 'msc-wound-care-assistant',
    name: 'MSC Wound Care Assistant',
    instructions: `You are a helpful AI assistant for MSC Wound Care Portal. You help healthcare providers with:
- Creating product requests for wound care supplies
- Processing clinical documentation and insurance information
- Checking Medicare coverage and eligibility
- Answering questions about wound care products and procedures

Always be professional, accurate, and HIPAA-compliant. When handling PHI, ensure privacy and security.

Available functions:
1. create_product_request - Create a new product request
2. validate_insurance - Check insurance eligibility
3. search_products - Search for wound care products
4. check_medicare_coverage - Check Medicare coverage for products

When users want to submit a product request, guide them through the required fields and use the create_product_request function.`,
    model: azureConfig.deploymentName,
    tools: functions.map(fn => ({
      type: 'function',
      function: {
        name: fn.name,
        description: fn.description,
        parameters: fn.parameters
      }
    }))
  };

  // Quick action suggestions
  const suggestions = [
    {
      label: "Create Product Request",
      value: "I need to create a new product request for a patient.",
    },
    {
      label: "Check Insurance",
      value: "Can you help me verify insurance eligibility?",
    },
    {
      label: "Search Products",
      value: "I'm looking for wound care products.",
    },
    {
      label: "Medicare Coverage",
      value: "Check if a product is covered by Medicare.",
    }
  ];

  if (!isVisible) return null;

  return (
    <SuperinterfaceProvider
      apiKey={azureConfig.apiKey}
      baseUrl={azureConfig.endpoint}
      assistantId={assistant.id}
      threadId={threadId}
      onThreadIdChange={setThreadId}
      tools={functions}
    >
      <MarkdownProvider components={{}}>
        <ThreadDialog
          open={isVisible}
          onOpenChange={(open) => !open && onClose()}
          className="superinterface-dialog"
        >
          <Thread
            assistant={assistant}
            className="w-full max-w-2xl mx-auto"
            showWelcomeMessage={true}
            welcomeMessage="Hello! I'm your MSC Wound Care Assistant. I can help you create product requests, check insurance eligibility, search for products, and verify Medicare coverage. How can I assist you today?"
          />
          <Suggestions suggestions={suggestions} />
        </ThreadDialog>
      </MarkdownProvider>
    </SuperinterfaceProvider>
  );
};

// Add type declaration for Laravel config
declare global {
  interface Window {
    Laravel?: {
      superinterface?: {
        azure_openai?: {
          endpoint: string;
          api_key: string;
          deployment_name: string;
          api_version: string;
        };
      };
    };
  }
}

export default SuperinterfaceWrapper; 