import React, { useState, useCallback, useEffect } from 'react';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import { FiSend, FiPaperclip, FiCheck, FiAlertCircle } from 'react-icons/fi';
import ReactMarkdown from 'react-markdown';
import { fetchWithCSRF } from '@/utils/csrf';

interface Message {
  id: string;
  type: 'user' | 'assistant' | 'system';
  content: string;
  timestamp: Date;
  attachments?: File[];
  metadata?: any;
}

interface ChatIVRInterfaceProps {
  onComplete: (episodeId: string) => void;
  currentUser: any;
  manufacturers: any[];
}

// Custom Markdown components for interactive elements
const MarkdownComponents = {
  // Input field component
  input: ({ id, placeholder, type = 'text' }: any) => {
    const [value, setValue] = useState('');
    
    return (
      <input
        id={id}
        type={type}
        placeholder={placeholder}
        value={value}
        onChange={(e) => {
          setValue(e.target.value);
          // Store in form data
          window.chatFormData = {
            ...window.chatFormData,
            [id]: e.target.value
          };
        }}
        className="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
      />
    );
  },
  
  // Select dropdown component
  select: ({ id, options }: any) => {
    const [value, setValue] = useState('');
    const optionsList = options.split('|');
    
    return (
      <select
        id={id}
        value={value}
        onChange={(e) => {
          setValue(e.target.value);
          window.chatFormData = {
            ...window.chatFormData,
            [id]: e.target.value
          };
        }}
        className="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        <option value="">Select...</option>
        {optionsList.map((opt: string) => (
          <option key={opt} value={opt.toLowerCase()}>{opt}</option>
        ))}
      </select>
    );
  },
  
  // Date picker component
  date: ({ id, label }: any) => {
    const [value, setValue] = useState('');
    
    return (
      <input
        id={id}
        type="date"
        value={value}
        onChange={(e) => {
          setValue(e.target.value);
          window.chatFormData = {
            ...window.chatFormData,
            [id]: e.target.value
          };
        }}
        className="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
      />
    );
  },
  
  // Action button component
  button: ({ text, action }: any) => {
    return (
      <button
        onClick={() => {
          // Trigger action in parent component
          const event = new CustomEvent('chatAction', { 
            detail: { action, data: window.chatFormData } 
          });
          window.dispatchEvent(event);
        }}
        className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
      >
        {text}
      </button>
    );
  }
};

// Define the conversation flow
const conversationFlow = {
  initial: {
    message: `Welcome! I'm here to help you create a wound care order. 

What type of request is this?

[select:request_type|New Request|Reverification|Additional Applications]

[button:Continue|next_patient_info]`,
    nextStep: 'patient_info'
  },
  
  patient_info: {
    message: `Great! Let's start with the patient information.

**Patient Details:**
- First Name: [input:patient_first_name]
- Last Name: [input:patient_last_name]
- Date of Birth: [date:patient_dob]
- Gender: [select:patient_gender|Male|Female|Other]

Would you like to upload an insurance card for automatic data extraction?

[button:Upload Insurance Card|upload_insurance]
[button:Enter Manually|next_insurance]`,
    nextStep: 'insurance'
  },
  
  insurance: {
    message: `Now let's add the insurance information.

**Primary Insurance:**
- Insurance Name: [input:primary_insurance_name]
- Member ID: [input:primary_member_id]
- Plan Type: [select:primary_plan_type|PPO|HMO|Medicare FFS|Medicare Advantage]

Does the patient have secondary insurance?

[button:Yes|add_secondary]
[button:No|next_clinical]`,
    nextStep: 'clinical'
  },
  
  clinical: {
    message: `Let's gather the clinical information for the wound.

**Wound Details:**
- Wound Type: [select:wound_type|Pressure Ulcer|Diabetic Foot Ulcer|Venous Ulcer|Surgical Wound|Other]
- Location: [input:wound_location]
- Length (cm): [input:wound_size_length]
- Width (cm): [input:wound_size_width]
- Depth (cm): [input:wound_size_depth]

**Duration:**
- Weeks: [input:wound_duration_weeks]

[button:Continue|next_products]`,
    nextStep: 'products'
  },
  
  products: {
    message: `Based on the clinical information, I'll help you select the appropriate products.

**Recommended Products:**
- Advanced wound dressings suitable for your wound type
- Products covered by the patient's insurance

[button:Select Products|show_product_selector]
[button:Skip to IVR|generate_ivr]`,
    nextStep: 'ivr'
  },
  
  ivr: {
    message: `Perfect! I've collected all the necessary information. 

**Summary:**
- Patient: {patient_name}
- Insurance: {insurance_summary}
- Wound: {wound_summary}
- Products: {product_summary}

I'll now generate the pre-filled IVR form for you to review and sign.

[button:Generate IVR Form|create_docuseal_form]`,
    nextStep: 'complete'
  },
  
  complete: {
    message: `✅ The IVR form has been generated and is ready for signing!

The form has been pre-filled with all the information we collected. Please review and add your signature where indicated.

[button:Open IVR Form|open_docuseal]
[button:Start New Order|restart]`,
    nextStep: null
  }
};

export default function ChatIVRInterface({ onComplete, currentUser, manufacturers }: ChatIVRInterfaceProps) {
  const { theme } = useTheme();
  const t = themes[theme];
  
  const [messages, setMessages] = useState<Message[]>([]);
  const [inputValue, setInputValue] = useState('');
  const [isProcessing, setIsProcessing] = useState(false);
  const [sessionId, setSessionId] = useState<string | null>(null);
  const [currentStep, setCurrentStep] = useState('initial');
  const [formData, setFormData] = useState<any>({});
  const [docusealUrl, setDocusealUrl] = useState<string | null>(null);
  
  // Initialize chat session
  useEffect(() => {
    initializeChat();
    
    // Listen for custom events from Markdown components
    window.addEventListener('chatAction', handleChatAction as any);
    
    return () => {
      window.removeEventListener('chatAction', handleChatAction as any);
    };
  }, []);
  
  const initializeChat = async () => {
    try {
      const response = await fetchWithCSRF('/api/v1/chat-ivr/start', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: currentUser.id })
      });
      
      const data = await response.json();
      setSessionId(data.session_id);
      
      // Add initial message
      addAssistantMessage(conversationFlow.initial.message);
    } catch (error) {
      console.error('Failed to initialize chat:', error);
      addSystemMessage('Failed to start chat session. Please refresh and try again.');
    }
  };
  
  const handleChatAction = (event: CustomEvent) => {
    const { action, data } = event.detail;
    
    // Merge form data
    setFormData((prev: any) => ({ ...prev, ...data }));
    
    // Handle specific actions
    switch (action) {
      case 'next_patient_info':
        setCurrentStep('patient_info');
        addAssistantMessage(conversationFlow.patient_info.message);
        break;
        
      case 'upload_insurance':
        handleInsuranceUpload();
        break;
        
      case 'next_insurance':
        setCurrentStep('insurance');
        addAssistantMessage(conversationFlow.insurance.message);
        break;
        
      case 'next_clinical':
        setCurrentStep('clinical');
        addAssistantMessage(conversationFlow.clinical.message);
        break;
        
      case 'next_products':
        setCurrentStep('products');
        addAssistantMessage(conversationFlow.products.message);
        break;
        
      case 'generate_ivr':
        generateIVRSummary();
        break;
        
      case 'create_docuseal_form':
        createDocusealForm();
        break;
        
      case 'open_docuseal':
        if (docusealUrl) {
          window.open(docusealUrl, '_blank');
        }
        break;
        
      case 'restart':
        window.location.reload();
        break;
    }
  };
  
  const handleInsuranceUpload = () => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = async (e) => {
      const file = (e.target as HTMLInputElement).files?.[0];
      if (file) {
        await processInsuranceCard(file);
      }
    };
    input.click();
  };
  
  const processInsuranceCard = async (file: File) => {
    setIsProcessing(true);
    addSystemMessage('Processing insurance card...');
    
    try {
      const formData = new FormData();
      formData.append('insurance_card_front', file);
      
      const response = await fetchWithCSRF('/api/insurance-card/analyze', {
        method: 'POST',
        body: formData
      });
      
      const result = await response.json();
      
      if (result.success && result.data) {
        // Update form data with extracted information
        setFormData((prev: any) => ({
          ...prev,
          primary_insurance_name: result.data.payer_name || prev.primary_insurance_name,
          primary_member_id: result.data.member_id || prev.primary_member_id
        }));
        
        addAssistantMessage(`✅ Insurance card processed successfully! I've extracted:
- Insurance: ${result.data.payer_name}
- Member ID: ${result.data.member_id}

Let's continue with the insurance details.`);
        
        // Continue to insurance step
        setCurrentStep('insurance');
        addAssistantMessage(conversationFlow.insurance.message);
      }
    } catch (error) {
      addSystemMessage('Failed to process insurance card. Please enter details manually.');
    } finally {
      setIsProcessing(false);
    }
  };
  
  const generateIVRSummary = () => {
    const summary = conversationFlow.ivr.message
      .replace('{patient_name}', `${formData.patient_first_name} ${formData.patient_last_name}`)
      .replace('{insurance_summary}', formData.primary_insurance_name)
      .replace('{wound_summary}', `${formData.wound_type} at ${formData.wound_location}`)
      .replace('{product_summary}', formData.selected_products?.length || 0);
    
    setCurrentStep('ivr');
    addAssistantMessage(summary);
  };
  
  const createDocusealForm = async () => {
    setIsProcessing(true);
    addSystemMessage('Creating your IVR form...');
    
    try {
      const response = await fetchWithCSRF('/api/v1/chat-ivr/generate-form', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          session_id: sessionId,
          form_data: formData
        })
      });
      
      const result = await response.json();
      
      if (result.success) {
        setDocusealUrl(result.submission_url);
        addAssistantMessage(conversationFlow.complete.message);
        
        if (result.episode_id) {
          // Notify parent component
          onComplete(result.episode_id);
        }
      }
    } catch (error) {
      addSystemMessage('Failed to create IVR form. Please try again.');
    } finally {
      setIsProcessing(false);
    }
  };
  
  const addUserMessage = (content: string, attachments?: File[]) => {
    const message: Message = {
      id: Date.now().toString(),
      type: 'user',
      content,
      timestamp: new Date(),
      attachments
    };
    setMessages(prev => [...prev, message]);
  };
  
  const addAssistantMessage = (content: string) => {
    const message: Message = {
      id: Date.now().toString(),
      type: 'assistant',
      content,
      timestamp: new Date()
    };
    setMessages(prev => [...prev, message]);
  };
  
  const addSystemMessage = (content: string) => {
    const message: Message = {
      id: Date.now().toString(),
      type: 'system',
      content,
      timestamp: new Date()
    };
    setMessages(prev => [...prev, message]);
  };
  
  const handleSendMessage = async () => {
    if (!inputValue.trim() || isProcessing) return;
    
    const userInput = inputValue;
    setInputValue('');
    addUserMessage(userInput);
    
    // For this POC, we're using predefined flows
    // In production, this would call an AI endpoint
    setIsProcessing(true);
    
    try {
      // Simulate processing
      await new Promise(resolve => setTimeout(resolve, 500));
      
      // Continue conversation based on current step
      const nextStepKey = conversationFlow[currentStep]?.nextStep;
      if (nextStepKey && conversationFlow[nextStepKey]) {
        setCurrentStep(nextStepKey);
        addAssistantMessage(conversationFlow[nextStepKey].message);
      }
    } finally {
      setIsProcessing(false);
    }
  };
  
  return (
    <div className={cn("flex flex-col h-full", t.glass.card, "rounded-lg")}>
      {/* Chat Header */}
      <div className={cn("px-6 py-4 border-b", t.glass.border)}>
        <h2 className={cn("text-xl font-semibold", t.text.primary)}>
          IVR Form Assistant
        </h2>
        <p className={cn("text-sm", t.text.secondary)}>
          I'll help you complete your wound care order step by step
        </p>
      </div>
      
      {/* Messages Area */}
      <div className="flex-1 overflow-y-auto p-6 space-y-4">
        {messages.map((message) => (
          <div
            key={message.id}
            className={cn(
              "flex",
              message.type === 'user' ? "justify-end" : "justify-start"
            )}
          >
            <div
              className={cn(
                "max-w-[80%] rounded-lg px-4 py-3",
                message.type === 'user'
                  ? "bg-blue-600 text-white"
                  : message.type === 'system'
                  ? cn(t.glass.card, "border", t.glass.border)
                  : cn(t.glass.card)
              )}
            >
              {message.type === 'assistant' ? (
                <ReactMarkdown
                  components={MarkdownComponents as any}
                  className="prose prose-sm max-w-none"
                >
                  {message.content}
                </ReactMarkdown>
              ) : (
                <p className={cn(
                  "text-sm",
                  message.type === 'user' ? "text-white" : t.text.primary
                )}>
                  {message.content}
                </p>
              )}
              
              {message.attachments && message.attachments.length > 0 && (
                <div className="mt-2">
                  {message.attachments.map((file, idx) => (
                    <div key={idx} className="flex items-center gap-2 text-xs">
                      <FiPaperclip className="h-3 w-3" />
                      <span>{file.name}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        ))}
        
        {isProcessing && (
          <div className="flex justify-start">
            <div className={cn("rounded-lg px-4 py-3", t.glass.card)}>
              <div className="flex items-center gap-2">
                <div className="animate-pulse flex gap-1">
                  <div className="w-2 h-2 bg-gray-400 rounded-full"></div>
                  <div className="w-2 h-2 bg-gray-400 rounded-full"></div>
                  <div className="w-2 h-2 bg-gray-400 rounded-full"></div>
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
      
      {/* Input Area */}
      <div className={cn("px-6 py-4 border-t", t.glass.border)}>
        <div className="flex items-center gap-2">
          <input
            type="text"
            value={inputValue}
            onChange={(e) => setInputValue(e.target.value)}
            onKeyPress={(e) => e.key === 'Enter' && handleSendMessage()}
            placeholder="Type your message..."
            disabled={isProcessing}
            className={cn(
              "flex-1 px-4 py-2 rounded-lg",
              t.glass.card,
              "border",
              t.glass.border,
              "focus:outline-none focus:ring-2 focus:ring-blue-500",
              t.text.primary
            )}
          />
          
          <button
            onClick={() => {
              const input = document.createElement('input');
              input.type = 'file';
              input.accept = 'image/*';
              input.onchange = (e) => {
                const file = (e.target as HTMLInputElement).files?.[0];
                if (file) {
                  addUserMessage('Uploaded: ' + file.name, [file]);
                }
              };
              input.click();
            }}
            disabled={isProcessing}
            className={cn(
              "p-2 rounded-lg",
              t.glass.card,
              "hover:bg-gray-700/50 transition-colors"
            )}
          >
            <FiPaperclip className={cn("h-5 w-5", t.text.secondary)} />
          </button>
          
          <button
            onClick={handleSendMessage}
            disabled={isProcessing || !inputValue.trim()}
            className={cn(
              "p-2 rounded-lg transition-colors",
              "bg-blue-600 hover:bg-blue-700 text-white",
              "disabled:opacity-50 disabled:cursor-not-allowed"
            )}
          >
            <FiSend className="h-5 w-5" />
          </button>
        </div>
      </div>
    </div>
  );
}

// Global form data storage for Markdown components
declare global {
  interface Window {
    chatFormData: any;
  }
}

window.chatFormData = {};