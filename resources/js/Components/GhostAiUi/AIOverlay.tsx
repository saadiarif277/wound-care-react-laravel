import React, { useState, useEffect, useRef } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { 
  MessageCircle, 
  MapPin, 
  Users, 
  Upload,
  Send,
  X,
  Loader2,
  Bot,
  User,
  Mic,
  MicOff,
  Search,
  FileText,
  Phone,
  Mail,
  ExternalLink,
  CheckCircle,
  AlertCircle,
  Heart,
  Activity,
  Stethoscope,
  Bandage,
  Shield,
  Download,
  Eye,
  Clock,
  DollarSign,
  AlertTriangle,
} from 'lucide-react';
import { useTheme } from '@/contexts/ThemeContext';
import { cn } from '@/theme/glass-theme';
import api from '@/lib/api';

interface AIOverlayProps {
  isOpen: boolean;
  onClose: () => void;
  onAnalysisComplete?: (analysis: any) => void;
  context?: {
    templateId?: string;
    currentPage?: string;
    userRole?: string;
    recentActions?: any[];
  };
}

interface ChatMessage {
  id: string;
  role: 'user' | 'assistant' | 'system';
  content: string;
  timestamp: Date;
  isTyping?: boolean;
  type?: 'text' | 'alert' | 'success' | 'info';
}

interface MacValidation {
  state: string;
  coverage: string;
  requirements: string[];
  status: 'covered' | 'not-covered' | 'prior-auth' | 'documentation-required';
  notes: string;
}

interface Contractor {
  id: string;
  name: string;
  specialty: string;
  state: string;
  phone: string;
  email: string;
  website?: string;
  rating: number;
  notes: string;
}

type TabType = 'chat' | 'mac-validation' | 'contractors' | 'documents';

export default function AIOverlay({ 
  isOpen, 
  onClose, 
  onAnalysisComplete,
  context = {} 
}: AIOverlayProps) {
  const { theme } = useTheme();
  const [activeTab, setActiveTab] = useState<TabType>('chat');
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [inputMessage, setInputMessage] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [isListening, setIsListening] = useState(false);
  const [selectedState, setSelectedState] = useState('');
  const [macResults, setMacResults] = useState<MacValidation[]>([]);
  const [contractorSearch, setContractorSearch] = useState('');
  const [contractors, setContractors] = useState<Contractor[]>([]);
  const [uploadedFiles, setUploadedFiles] = useState<File[]>([]);
  const [dragActive, setDragActive] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLTextAreaElement>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const tabs = [
    { id: 'chat', label: 'AI Assistant', icon: MessageCircle },
    { id: 'mac-validation', label: 'MAC Validation', icon: MapPin },
    { id: 'contractors', label: 'Contractors', icon: Users },
    { id: 'documents', label: 'Documents', icon: Upload },
  ];

  const states = [
    'Alabama', 'Alaska', 'Arizona', 'Arkansas', 'California', 'Colorado', 'Connecticut',
    'Delaware', 'Florida', 'Georgia', 'Hawaii', 'Idaho', 'Illinois', 'Indiana', 'Iowa',
    'Kansas', 'Kentucky', 'Louisiana', 'Maine', 'Maryland', 'Massachusetts', 'Michigan',
    'Minnesota', 'Mississippi', 'Missouri', 'Montana', 'Nebraska', 'Nevada', 'New Hampshire',
    'New Jersey', 'New Mexico', 'New York', 'North Carolina', 'North Dakota', 'Ohio',
    'Oklahoma', 'Oregon', 'Pennsylvania', 'Rhode Island', 'South Carolina', 'South Dakota',
    'Tennessee', 'Texas', 'Utah', 'Vermont', 'Virginia', 'Washington', 'West Virginia',
    'Wisconsin', 'Wyoming'
  ];

  // Sample contractor data
  const sampleContractors: Contractor[] = [
    {
      id: '1',
      name: 'Advanced Wound Care Solutions',
      specialty: 'Diabetic Wound Care',
      state: 'California',
      phone: '(555) 123-4567',
      email: 'info@advancedwoundcare.com',
      website: 'www.advancedwoundcare.com',
      rating: 4.8,
      notes: 'Specializes in diabetic ulcers and pressure injuries'
    },
    {
      id: '2',
      name: 'Healing Touch Medical',
      specialty: 'Surgical Wound Management',
      state: 'Texas',
      phone: '(555) 987-6543',
      email: 'contact@healingtouch.com',
      website: 'www.healingtouch.com',
      rating: 4.5,
      notes: 'Post-surgical wound care and infection prevention'
    },
    {
      id: '3',
      name: 'Wound Care Associates',
      specialty: 'Chronic Wound Treatment',
      state: 'Florida',
      phone: '(555) 456-7890',
      email: 'info@woundcareassoc.com',
      rating: 4.7,
      notes: 'Comprehensive chronic wound management'
    }
  ];

  useEffect(() => {
    setContractors(sampleContractors);
  }, []);

  useEffect(() => {
    if (isOpen && messages.length === 0 && activeTab === 'chat') {
      const greeting: ChatMessage = {
        id: 'welcome',
        role: 'assistant',
        content: `👋 **Welcome to MSC Wound Care AI Assistant**

I'm here to help you with wound care management, Medicare coverage, and supplier coordination. I can assist with:

🩹 **Clinical Support**
• Wound assessment and documentation
• Treatment protocols and best practices
• Product recommendations

📋 **Coverage & Compliance**
• Medicare LCD/NCD validation
• Documentation requirements
• Prior authorization guidance

🔗 **Network Resources**
• Contractor connections
• Supplier information
• Educational materials

What can I help you with today?`,
        timestamp: new Date(),
        type: 'info'
      };
      setMessages([greeting]);
    }
  }, [isOpen, activeTab]);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const sendMessage = async () => {
    if (!inputMessage.trim() || isLoading) return;

    const userMessage: ChatMessage = {
      id: Date.now().toString(),
      role: 'user',
      content: inputMessage,
      timestamp: new Date(),
      type: 'text'
    };

    setMessages(prev => [...prev, userMessage]);
    setInputMessage('');
    setIsLoading(true);

    const thinkingMessage: ChatMessage = {
      id: 'thinking',
      role: 'assistant',
      content: '',
      timestamp: new Date(),
      isTyping: true,
      type: 'text'
    };
    setMessages(prev => [...prev, thinkingMessage]);

    try {
      await new Promise(resolve => setTimeout(resolve, 1000 + Math.random() * 2000));
      
      const response = await generateWoundCareResponse(inputMessage);
      setMessages(prev => prev.filter(msg => msg.id !== 'thinking').concat(response));
      
    } catch (error) {
      const errorMessage: ChatMessage = {
        id: (Date.now() + 1).toString(),
        role: 'assistant',
        content: '⚠️ I encountered an error. Please try again.',
        timestamp: new Date(),
        type: 'alert'
      };
      setMessages(prev => prev.filter(msg => msg.id !== 'thinking').concat(errorMessage));
    } finally {
      setIsLoading(false);
    }
  };

  const generateWoundCareResponse = async (userInput: string): Promise<ChatMessage> => {
    const input = userInput.toLowerCase();
    
    if (input.includes('diabetic') || input.includes('ulcer') || input.includes('diabetes')) {
      return {
        id: Date.now().toString(),
        role: 'assistant',
        content: `🩹 **Diabetic Wound Care Protocol**

**Assessment Guidelines:**
• Check HbA1c levels (target <7%)
• Assess circulation and sensation
• Document wound dimensions and characteristics
• Screen for infection signs

**Treatment Recommendations:**
• Moisture balance with appropriate dressings
• Offloading for plantar ulcers
• Infection control if indicated
• Nutritional support

**Medicare Coverage (LCD L33831):**
✅ Advanced wound dressings covered
✅ Negative pressure therapy eligible
✅ Offloading devices covered
⚠️ Requires proper documentation

**Documentation Requirements:**
• Weekly wound measurements
• Photos when possible
• Treatment response notes
• Comorbidity management

Would you like specific product recommendations or coverage details for a particular treatment?`,
        timestamp: new Date(),
        type: 'info'
      };
    }

    if (input.includes('pressure') || input.includes('bed sore') || input.includes('decubitus')) {
      return {
        id: Date.now().toString(),
        role: 'assistant',
        content: `🛏️ **Pressure Injury Management**

**Classification & Treatment:**
• **Stage 1:** Skin protection, repositioning
• **Stage 2:** Moisture management, barrier protection
• **Stage 3:** Debridement, infection prevention
• **Stage 4:** Surgical consultation, advanced therapies

**Medicare Coverage Criteria:**
✅ Pressure redistribution surfaces
✅ Advanced wound dressings
✅ Negative pressure therapy (Stage 3-4)
⚠️ Requires physician orders

**Prevention Protocol:**
• Turn/reposition every 2 hours
• Use pressure-redistributing surfaces
• Maintain skin integrity
• Nutritional optimization

**Documentation Essentials:**
• Staging with measurements
• Location and characteristics
• Treatment plan and goals
• Progress monitoring

Need help with specific staging or product selection?`,
        timestamp: new Date(),
        type: 'info'
      };
    }

    if (input.includes('medicare') || input.includes('coverage') || input.includes('lcd')) {
      return {
        id: Date.now().toString(),
        role: 'assistant',
        content: `📋 **Medicare Coverage for Wound Care**

**Key LCDs (Local Coverage Determinations):**
• **L33831** - Wound Care
• **L33822** - Negative Pressure Wound Therapy
• **L33787** - Surgical Dressings

**Coverage Requirements:**
✅ Physician documentation of wound
✅ Medical necessity established
✅ Appropriate wound characteristics
✅ Failed conservative treatment

**Documentation Must Include:**
• Wound etiology and duration
• Size, depth, drainage
• Previous treatments tried
• Expected healing timeframe

**Common Denials:**
❌ Insufficient documentation
❌ Inappropriate wound type
❌ Lack of medical necessity
❌ Missing physician oversight

Use the MAC Validation tab to check specific state requirements!`,
        timestamp: new Date(),
        type: 'info'
      };
    }

    if (input.includes('supplier') || input.includes('contractor') || input.includes('contact')) {
      return {
        id: Date.now().toString(),
        role: 'assistant',
        content: `🔗 **Wound Care Network Resources**

**Finding the Right Contractor:**
• Check the Contractors tab for verified partners
• Filter by specialty and location
• Review ratings and specialties

**Supplier Categories:**
• **Dressing Manufacturers** - Advanced wound care
• **DME Suppliers** - Negative pressure devices
• **Specialty Clinics** - Complex wound management
• **Educational Partners** - Staff training

**Vetting Process:**
✅ Medicare enrollment verification
✅ Specialty certifications
✅ Patient outcome data
✅ Compliance history

**Partnership Benefits:**
• Streamlined referrals
• Training opportunities
• Outcome tracking
• Compliance support

Check the Contractors tab to search our verified network!`,
        timestamp: new Date(),
        type: 'info'
      };
    }

    // Default wound care response
    return {
      id: Date.now().toString(),
      role: 'assistant',
      content: `🩹 **Wound Care AI Assistant**

I can help you with specific wound care topics:

**Clinical Questions:**
• Wound assessment and staging
• Treatment protocols
• Product selection
• Infection management

**Coverage & Compliance:**
• Medicare LCD requirements
• Documentation guidelines
• Prior authorization
• Billing guidance

**Network Resources:**
• Contractor connections
• Supplier information
• Educational materials
• Training opportunities

Please ask me about a specific wound type, coverage question, or resource need!`,
      timestamp: new Date(),
      type: 'text'
    };
  };

  const handleMacValidation = async () => {
    if (!selectedState) return;

    setIsLoading(true);
    
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1500));
    
    const mockResults: MacValidation[] = [
      {
        state: selectedState,
        coverage: 'Covered with Prior Authorization',
        requirements: [
          'Physician documentation of wound etiology',
          'Failed conservative treatment for 4 weeks',
          'Wound measurements and photos',
          'Treatment plan with goals'
        ],
        status: 'prior-auth',
        notes: 'Requires prior authorization for advanced therapies. Standard dressings covered without PA.'
      }
    ];
    
    setMacResults(mockResults);
    setIsLoading(false);
  };

  const handleFileUpload = (files: FileList | null) => {
    if (files) {
      const fileArray = Array.from(files);
      setUploadedFiles(prev => [...prev, ...fileArray]);
    }
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    setDragActive(true);
  };

  const handleDragLeave = () => {
    setDragActive(false);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setDragActive(false);
    handleFileUpload(e.dataTransfer.files);
  };

  const filteredContractors = contractors.filter(contractor =>
    contractor.name.toLowerCase().includes(contractorSearch.toLowerCase()) ||
    contractor.specialty.toLowerCase().includes(contractorSearch.toLowerCase()) ||
    contractor.state.toLowerCase().includes(contractorSearch.toLowerCase())
  );

  if (!isOpen) return null;

  return (
    <AnimatePresence>
      <div className="fixed inset-0 z-50 flex items-center justify-center">
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          className="absolute inset-0 bg-black/40 backdrop-blur-sm"
          onClick={onClose}
        />

        <motion.div
          initial={{ opacity: 0, scale: 0.95, y: 20 }}
          animate={{ opacity: 1, scale: 1, y: 0 }}
          exit={{ opacity: 0, scale: 0.95, y: 20 }}
          transition={{ type: "spring", damping: 25, stiffness: 300 }}
          className={cn(
            "relative w-full max-w-5xl h-[85vh] mx-4 overflow-hidden rounded-2xl shadow-2xl",
            "bg-gradient-to-br from-blue-50 to-indigo-100 border border-blue-200",
            theme === 'dark' && "from-gray-900 to-blue-900 border-blue-700",
            "flex flex-col backdrop-blur-xl"
          )}
        >
          {/* Header */}
          <div className={cn(
            "flex items-center justify-between p-6 border-b",
            theme === 'dark' ? 'border-blue-700/50' : 'border-blue-200'
          )}>
            <div className="flex items-center gap-4">
              <div className={cn(
                "w-12 h-12 rounded-full flex items-center justify-center",
                "bg-gradient-to-br from-blue-500 to-indigo-600 shadow-lg"
              )}>
                <Stethoscope className="w-6 h-6 text-white" />
              </div>
              <div>
                <h1 className={cn(
                  "text-xl font-bold",
                  theme === 'dark' ? 'text-white' : 'text-gray-900'
                )}>
                  MSC Wound Care Assistant
                </h1>
                <p className={cn(
                  "text-sm",
                  theme === 'dark' ? 'text-blue-300' : 'text-blue-600'
                )}>
                  Clinical Support & Coverage Validation
                </p>
              </div>
            </div>
            <button
              onClick={onClose}
              className={cn(
                "w-10 h-10 rounded-full flex items-center justify-center transition-colors",
                theme === 'dark' 
                  ? 'hover:bg-gray-800 text-gray-400 hover:text-white'
                  : 'hover:bg-gray-200 text-gray-500 hover:text-gray-700'
              )}
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          {/* Tab Navigation */}
          <div className={cn(
            "flex border-b",
            theme === 'dark' ? 'border-blue-700/50' : 'border-blue-200'
          )}>
            {tabs.map((tab) => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id as TabType)}
                className={cn(
                  "flex items-center gap-2 px-6 py-3 text-sm font-medium transition-colors",
                  activeTab === tab.id
                    ? theme === 'dark'
                      ? 'text-blue-400 border-b-2 border-blue-400 bg-blue-900/20'
                      : 'text-blue-600 border-b-2 border-blue-600 bg-blue-50'
                    : theme === 'dark'
                    ? 'text-gray-400 hover:text-white hover:bg-gray-800/50'
                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                )}
              >
                <tab.icon className="w-4 h-4" />
                {tab.label}
              </button>
            ))}
          </div>

          {/* Tab Content */}
          <div className="flex-1 overflow-hidden">
            {activeTab === 'chat' && (
              <div className="flex flex-col h-full">
                <div className="flex-1 overflow-y-auto p-6 space-y-4">
                  {messages.map((message) => (
                    <motion.div
                      key={message.id}
                      initial={{ opacity: 0, y: 20 }}
                      animate={{ opacity: 1, y: 0 }}
                      className={cn(
                        "flex gap-3",
                        message.role === 'user' ? 'justify-end' : 'justify-start'
                      )}
                    >
                      {message.role !== 'user' && (
                        <div className="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center flex-shrink-0">
                          <Bot className="w-4 h-4 text-white" />
                        </div>
                      )}
                      
                      <div
                        className={cn(
                          "max-w-[80%] rounded-2xl p-4",
                          message.role === 'user'
                            ? "bg-blue-600 text-white"
                            : message.type === 'alert'
                            ? "bg-red-50 border border-red-200 text-red-800"
                            : message.type === 'success'
                            ? "bg-green-50 border border-green-200 text-green-800"
                            : theme === 'dark'
                            ? "bg-gray-800 text-gray-100"
                            : "bg-white border border-gray-200 text-gray-900"
                        )}
                      >
                        {message.isTyping ? (
                          <div className="flex items-center gap-2">
                            <div className="flex gap-1">
                              <div className="w-2 h-2 bg-blue-500 rounded-full animate-bounce" />
                              <div className="w-2 h-2 bg-blue-500 rounded-full animate-bounce" style={{ animationDelay: '0.1s' }} />
                              <div className="w-2 h-2 bg-blue-500 rounded-full animate-bounce" style={{ animationDelay: '0.2s' }} />
                            </div>
                            <span className="text-sm">Analyzing...</span>
                          </div>
                        ) : (
                          <div className="text-sm whitespace-pre-wrap">
                            {message.content}
                          </div>
                        )}
                      </div>
                      
                      {message.role === 'user' && (
                        <div className="w-8 h-8 rounded-full bg-gray-600 flex items-center justify-center flex-shrink-0">
                          <User className="w-4 h-4 text-white" />
                        </div>
                      )}
                    </motion.div>
                  ))}
                  <div ref={messagesEndRef} />
                </div>

                <div className={cn(
                  "p-4 border-t",
                  theme === 'dark' ? 'border-gray-700' : 'border-gray-200'
                )}>
                  <div className="flex items-end gap-3">
                    <div className="flex-1">
                      <textarea
                        ref={inputRef}
                        value={inputMessage}
                        onChange={(e) => setInputMessage(e.target.value)}
                        onKeyPress={(e) => {
                          if (e.key === 'Enter' && !e.shiftKey) {
                            e.preventDefault();
                            sendMessage();
                          }
                        }}
                        placeholder="Ask about wound care, Medicare coverage, or supplier information..."
                        className={cn(
                          "w-full resize-none rounded-lg border p-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500",
                          theme === 'dark'
                            ? 'bg-gray-800 border-gray-700 text-white placeholder-gray-400'
                            : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500'
                        )}
                        rows={1}
                      />
                    </div>
                    <button
                      onClick={sendMessage}
                      disabled={!inputMessage.trim() || isLoading}
                      className="w-10 h-10 rounded-lg bg-blue-600 text-white flex items-center justify-center hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                      {isLoading ? (
                        <Loader2 className="w-4 h-4 animate-spin" />
                      ) : (
                        <Send className="w-4 h-4" />
                      )}
                    </button>
                  </div>
                </div>
              </div>
            )}

            {activeTab === 'mac-validation' && (
              <div className="p-6">
                <div className="mb-6">
                  <h3 className={cn(
                    "text-lg font-semibold mb-2",
                    theme === 'dark' ? 'text-white' : 'text-gray-900'
                  )}>
                    MAC Validation by State
                  </h3>
                  <p className={cn(
                    "text-sm",
                    theme === 'dark' ? 'text-gray-400' : 'text-gray-600'
                  )}>
                    Check Medicare coverage requirements for wound care by state
                  </p>
                </div>

                <div className="flex gap-4 mb-6">
                  <select
                    value={selectedState}
                    onChange={(e) => setSelectedState(e.target.value)}
                    className={cn(
                      "flex-1 rounded-lg border p-3 focus:outline-none focus:ring-2 focus:ring-blue-500",
                      theme === 'dark'
                        ? 'bg-gray-800 border-gray-700 text-white'
                        : 'bg-white border-gray-300 text-gray-900'
                    )}
                  >
                    <option value="">Select a state...</option>
                    {states.map(state => (
                      <option key={state} value={state}>{state}</option>
                    ))}
                  </select>
                  <button
                    onClick={handleMacValidation}
                    disabled={!selectedState || isLoading}
                    className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                  >
                    {isLoading ? <Loader2 className="w-4 h-4 animate-spin" /> : 'Validate'}
                  </button>
                </div>

                {macResults.length > 0 && (
                  <div className="space-y-4">
                    {macResults.map((result, index) => (
                      <div
                        key={index}
                        className={cn(
                          "rounded-lg border p-4",
                          theme === 'dark'
                            ? 'bg-gray-800 border-gray-700'
                            : 'bg-white border-gray-200'
                        )}
                      >
                        <div className="flex items-center justify-between mb-3">
                          <h4 className={cn(
                            "font-semibold",
                            theme === 'dark' ? 'text-white' : 'text-gray-900'
                          )}>
                            {result.state} - MAC Validation
                          </h4>
                          <span className={cn(
                            "px-2 py-1 rounded-full text-xs font-medium",
                            result.status === 'covered' && 'bg-green-100 text-green-800',
                            result.status === 'prior-auth' && 'bg-yellow-100 text-yellow-800',
                            result.status === 'not-covered' && 'bg-red-100 text-red-800'
                          )}>
                            {result.status}
                          </span>
                        </div>
                        
                        <div className="mb-3">
                          <span className={cn(
                            "font-medium",
                            theme === 'dark' ? 'text-gray-300' : 'text-gray-700'
                          )}>
                            Coverage: {result.coverage}
                          </span>
                        </div>
                        
                        <div className="mb-3">
                          <h5 className={cn(
                            "font-medium mb-2",
                            theme === 'dark' ? 'text-gray-300' : 'text-gray-700'
                          )}>
                            Requirements:
                          </h5>
                          <ul className="space-y-1">
                            {result.requirements.map((req, i) => (
                              <li key={i} className={cn(
                                "text-sm flex items-center gap-2",
                                theme === 'dark' ? 'text-gray-400' : 'text-gray-600'
                              )}>
                                <CheckCircle className="w-3 h-3 text-green-500" />
                                {req}
                              </li>
                            ))}
                          </ul>
                        </div>
                        
                        <div className={cn(
                          "text-sm p-3 rounded bg-blue-50 text-blue-800",
                          theme === 'dark' && 'bg-blue-900/20 text-blue-300'
                        )}>
                          <strong>Notes:</strong> {result.notes}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}

            {activeTab === 'contractors' && (
              <div className="p-6">
                <div className="mb-6">
                  <h3 className={cn(
                    "text-lg font-semibold mb-2",
                    theme === 'dark' ? 'text-white' : 'text-gray-900'
                  )}>
                    Verified Contractors
                  </h3>
                  <div className="flex gap-4">
                    <div className="flex-1">
                      <input
                        type="text"
                        placeholder="Search contractors by name, specialty, or state..."
                        value={contractorSearch}
                        onChange={(e) => setContractorSearch(e.target.value)}
                        className={cn(
                          "w-full rounded-lg border p-3 focus:outline-none focus:ring-2 focus:ring-blue-500",
                          theme === 'dark'
                            ? 'bg-gray-800 border-gray-700 text-white placeholder-gray-400'
                            : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500'
                        )}
                      />
                    </div>
                  </div>
                </div>

                <div className="space-y-4">
                  {filteredContractors.map((contractor) => (
                    <div
                      key={contractor.id}
                      className={cn(
                        "rounded-lg border p-4",
                        theme === 'dark'
                          ? 'bg-gray-800 border-gray-700'
                          : 'bg-white border-gray-200'
                      )}
                    >
                      <div className="flex items-start justify-between mb-3">
                        <div>
                          <h4 className={cn(
                            "font-semibold text-lg",
                            theme === 'dark' ? 'text-white' : 'text-gray-900'
                          )}>
                            {contractor.name}
                          </h4>
                          <p className={cn(
                            "text-sm",
                            theme === 'dark' ? 'text-blue-300' : 'text-blue-600'
                          )}>
                            {contractor.specialty}
                          </p>
                        </div>
                        <div className="flex items-center gap-1">
                          <div className="flex">
                            {[...Array(5)].map((_, i) => (
                              <div
                                key={i}
                                className={cn(
                                  "w-4 h-4",
                                  i < Math.floor(contractor.rating) ? 'text-yellow-400' : 'text-gray-300'
                                )}
                              >
                                ★
                              </div>
                            ))}
                          </div>
                          <span className={cn(
                            "text-sm ml-1",
                            theme === 'dark' ? 'text-gray-400' : 'text-gray-600'
                          )}>
                            {contractor.rating}
                          </span>
                        </div>
                      </div>
                      
                      <div className="grid grid-cols-2 gap-4 mb-3">
                        <div className="flex items-center gap-2">
                          <MapPin className="w-4 h-4 text-gray-400" />
                          <span className={cn(
                            "text-sm",
                            theme === 'dark' ? 'text-gray-300' : 'text-gray-700'
                          )}>
                            {contractor.state}
                          </span>
                        </div>
                        <div className="flex items-center gap-2">
                          <Phone className="w-4 h-4 text-gray-400" />
                          <span className={cn(
                            "text-sm",
                            theme === 'dark' ? 'text-gray-300' : 'text-gray-700'
                          )}>
                            {contractor.phone}
                          </span>
                        </div>
                        <div className="flex items-center gap-2">
                          <Mail className="w-4 h-4 text-gray-400" />
                          <span className={cn(
                            "text-sm",
                            theme === 'dark' ? 'text-gray-300' : 'text-gray-700'
                          )}>
                            {contractor.email}
                          </span>
                        </div>
                        {contractor.website && (
                          <div className="flex items-center gap-2">
                            <ExternalLink className="w-4 h-4 text-gray-400" />
                            <a
                              href={`https://${contractor.website}`}
                              target="_blank"
                              rel="noopener noreferrer"
                              className={cn(
                                "text-sm text-blue-600 hover:text-blue-800",
                                theme === 'dark' && 'text-blue-400 hover:text-blue-300'
                              )}
                            >
                              {contractor.website}
                            </a>
                          </div>
                        )}
                      </div>
                      
                      <div className={cn(
                        "text-sm p-3 rounded bg-gray-50",
                        theme === 'dark' ? 'bg-gray-700 text-gray-300' : 'text-gray-700'
                      )}>
                        <strong>Notes:</strong> {contractor.notes}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {activeTab === 'documents' && (
              <div className="p-6">
                <div className="mb-6">
                  <h3 className={cn(
                    "text-lg font-semibold mb-2",
                    theme === 'dark' ? 'text-white' : 'text-gray-900'
                  )}>
                    Document Upload & Analysis
                  </h3>
                  <p className={cn(
                    "text-sm",
                    theme === 'dark' ? 'text-gray-400' : 'text-gray-600'
                  )}>
                    Upload wound care documents for AI analysis and coverage validation
                  </p>
                </div>

                <div
                  className={cn(
                    "border-2 border-dashed rounded-lg p-8 text-center",
                    dragActive
                      ? 'border-blue-500 bg-blue-50'
                      : theme === 'dark'
                      ? 'border-gray-600 bg-gray-800'
                      : 'border-gray-300 bg-gray-50'
                  )}
                  onDragOver={handleDragOver}
                  onDragLeave={handleDragLeave}
                  onDrop={handleDrop}
                >
                  <Upload className={cn(
                    "w-12 h-12 mx-auto mb-4",
                    theme === 'dark' ? 'text-gray-400' : 'text-gray-400'
                  )} />
                  <p className={cn(
                    "text-lg font-medium mb-2",
                    theme === 'dark' ? 'text-gray-300' : 'text-gray-700'
                  )}>
                    Drop files here or click to upload
                  </p>
                  <p className={cn(
                    "text-sm mb-4",
                    theme === 'dark' ? 'text-gray-400' : 'text-gray-500'
                  )}>
                    Supports PDF, JPG, PNG files up to 10MB
                  </p>
                  <button
                    onClick={() => fileInputRef.current?.click()}
                    className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                  >
                    Select Files
                  </button>
                  <input
                    ref={fileInputRef}
                    type="file"
                    multiple
                    accept=".pdf,.jpg,.jpeg,.png"
                    onChange={(e) => handleFileUpload(e.target.files)}
                    className="hidden"
                  />
                </div>

                {uploadedFiles.length > 0 && (
                  <div className="mt-6">
                    <h4 className={cn(
                      "font-medium mb-3",
                      theme === 'dark' ? 'text-white' : 'text-gray-900'
                    )}>
                      Uploaded Files
                    </h4>
                    <div className="space-y-2">
                      {uploadedFiles.map((file, index) => (
                        <div
                          key={index}
                          className={cn(
                            "flex items-center justify-between p-3 rounded border",
                            theme === 'dark'
                              ? 'bg-gray-800 border-gray-700'
                              : 'bg-white border-gray-200'
                          )}
                        >
                          <div className="flex items-center gap-3">
                            <FileText className="w-5 h-5 text-blue-500" />
                            <span className={cn(
                              "text-sm",
                              theme === 'dark' ? 'text-gray-300' : 'text-gray-700'
                            )}>
                              {file.name}
                            </span>
                          </div>
                          <button
                            onClick={() => setUploadedFiles(prev => prev.filter((_, i) => i !== index))}
                            className="text-red-500 hover:text-red-700 transition-colors"
                          >
                            <X className="w-4 h-4" />
                          </button>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            )}
          </div>
        </motion.div>
      </div>
    </AnimatePresence>
  );
} 