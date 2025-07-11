import React, { useState, useEffect, useRef } from 'react';
import { Send, Sparkles, AlertCircle, TrendingUp, Package, Shield, DollarSign, X, Loader2 } from 'lucide-react';
import api from '@/lib/api';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';

interface Message {
    id: string;
    role: 'user' | 'assistant' | 'system';
    content: string;
    timestamp: Date;
    metadata?: {
        type?: 'alert' | 'insight' | 'recommendation';
        confidence?: number;
        sources?: string[];
    };
}

interface InsuranceAssistantProps {
    userRole?: string;
    onClose?: () => void;
}

// Predefined quick actions for healthcare distributors
const quickActions = [
    { icon: AlertCircle, label: 'Check Drug Shortages', query: 'What are the current drug shortages and alternatives?' },
    { icon: DollarSign, label: 'Contract Exceptions', query: 'Show me recent contract pricing exceptions' },
    { icon: Package, label: 'Inventory Alerts', query: 'Any critical inventory issues or expiring products?' },
    { icon: TrendingUp, label: 'Demand Forecast', query: 'What products are trending up in demand?' },
    { icon: Shield, label: 'Compliance Status', query: 'Show DSCSA compliance status and upcoming deadlines' },
];

export default function InsuranceAssistant({ userRole, onClose }: InsuranceAssistantProps) {
    const { theme } = useTheme();
    const t = themes[theme];
    const [messages, setMessages] = useState<Message[]>([]);
    const [inputMessage, setInputMessage] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [isTyping, setIsTyping] = useState(false);
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLTextAreaElement>(null);

    useEffect(() => {
        // Initial greeting
        const greeting: Message = {
            id: Date.now().toString(),
            role: 'assistant',
            content: `Hello! I'm your Healthcare Supply Chain AI Assistant. I can help you with:

• Real-time drug shortage alerts and mitigation strategies
• Contract pricing exceptions and GPO compliance
• Inventory optimization and expiry management
• Supplier performance analytics
• Regulatory compliance tracking
• Financial impact analysis

What can I help you with today?`,
            timestamp: new Date(),
        };
        setMessages([greeting]);
    }, []);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    const sendMessage = async (message: string = inputMessage) => {
        if (!message.trim() || isLoading) return;

        const userMessage: Message = {
            id: Date.now().toString(),
            role: 'user',
            content: message,
            timestamp: new Date()
        };

        setMessages(prev => [...prev, userMessage]);
        setInputMessage('');
        setIsLoading(true);
        setIsTyping(true);

        try {
            // Simulate API call - replace with actual endpoint
            const response = await api.post('/api/v1/healthcare-ai/message', {
                message: message,
                context: { userRole }
            });

            const assistantMessage: Message = {
                id: (Date.now() + 1).toString(),
                role: 'assistant',
                content: response.data.content || 'I understand your concern. Let me help you with that...',
                timestamp: new Date(),
                metadata: response.data.metadata
            };

            // Simulate typing effect
            setTimeout(() => {
                setIsTyping(false);
                setMessages(prev => [...prev, assistantMessage]);
            }, 1000);

        } catch (error) {
            setIsTyping(false);
            const errorMessage: Message = {
                id: (Date.now() + 1).toString(),
                role: 'assistant',
                content: 'I apologize, but I encountered an error. Please try again.',
                timestamp: new Date()
            };
            setMessages(prev => [...prev, errorMessage]);
        } finally {
            setIsLoading(false);
        }
    };

    const handleKeyPress = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    };

    return (
        <div className={cn(
            "flex flex-col h-full",
            t.glass.card,
            "relative"
        )}>
            {/* Header */}
            <div className={cn(
                "flex items-center justify-between p-4 border-b",
                t.glass.border
            )}>
                <div className="flex items-center gap-3">
                    <div className={cn(
                        "w-10 h-10 rounded-full flex items-center justify-center",
                        "bg-gradient-to-br from-blue-500 to-purple-600",
                        "shadow-lg"
                    )}>
                        <Sparkles className="w-5 h-5 text-white" />
                    </div>
                    <div>
                        <h3 className={cn("text-lg font-semibold", t.text.primary)}>
                            Healthcare AI Assistant
                        </h3>
                        <p className={cn("text-xs", t.text.secondary)}>
                            Powered by advanced analytics
                        </p>
                    </div>
                </div>
                {onClose && (
                    <button
                        onClick={onClose}
                        className={cn(
                            "p-2 rounded-lg transition-colors",
                            t.glass.hover,
                            t.text.secondary
                        )}
                    >
                        <X className="w-5 h-5" />
                    </button>
                )}
            </div>

            {/* Messages Area */}
            <div className="flex-1 overflow-y-auto p-4 space-y-4">
                {messages.map((message) => (
                    <div
                        key={message.id}
                        className={cn(
                            "flex",
                            message.role === 'user' ? 'justify-end' : 'justify-start'
                        )}
                    >
                        <div
                            className={cn(
                                "max-w-[80%] rounded-2xl p-4",
                                message.role === 'user'
                                    ? "bg-gradient-to-r from-blue-600 to-blue-700 text-white ml-12"
                                    : cn(t.glass.base, t.glass.border, "mr-12")
                            )}
                        >
                            {message.role === 'assistant' && message.metadata?.type && (
                                <div className={cn(
                                    "flex items-center gap-2 mb-2 text-xs font-medium",
                                    message.metadata.type === 'alert' && "text-red-500",
                                    message.metadata.type === 'insight' && "text-blue-500",
                                    message.metadata.type === 'recommendation' && "text-green-500"
                                )}>
                                    {message.metadata.type === 'alert' && <AlertCircle className="w-3 h-3" />}
                                    {message.metadata.type === 'insight' && <TrendingUp className="w-3 h-3" />}
                                    {message.metadata.type === 'recommendation' && <Sparkles className="w-3 h-3" />}
                                    {message.metadata.type.charAt(0).toUpperCase() + message.metadata.type.slice(1)}
                                </div>
                            )}
                            <p className={cn(
                                "text-sm whitespace-pre-wrap",
                                message.role === 'assistant' && t.text.primary
                            )}>
                                {message.content}
                            </p>
                            <p className={cn(
                                "text-xs mt-2 opacity-70",
                                message.role === 'user' ? "text-white" : t.text.secondary
                            )}>
                                {message.timestamp.toLocaleTimeString()}
                            </p>
                        </div>
                    </div>
                ))}
                {isTyping && (
                    <div className="flex justify-start">
                        <div className={cn(
                            "rounded-2xl p-4 mr-12",
                            t.glass.base,
                            t.glass.border
                        )}>
                            <div className="flex items-center gap-2">
                                <Loader2 className="w-4 h-4 animate-spin text-blue-500" />
                                <span className={cn("text-sm", t.text.secondary)}>
                                    AI is thinking...
                                </span>
                            </div>
                        </div>
                    </div>
                )}
                <div ref={messagesEndRef} />
            </div>

            {/* Quick Actions */}
            {messages.length === 1 && (
                <div className="px-4 pb-2">
                    <p className={cn("text-xs mb-2", t.text.secondary)}>Quick actions:</p>
                    <div className="flex flex-wrap gap-2">
                        {quickActions.map((action, index) => (
                            <button
                                key={index}
                                onClick={() => sendMessage(action.query)}
                                className={cn(
                                    "flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium",
                                    "transition-all duration-200",
                                    t.glass.base,
                                    t.glass.border,
                                    t.glass.hover,
                                    t.text.secondary,
                                    "hover:scale-105"
                                )}
                            >
                                <action.icon className="w-3 h-3" />
                                {action.label}
                            </button>
                        ))}
                    </div>
                </div>
            )}

            {/* Input Area */}
            <div className={cn(
                "border-t p-4",
                t.glass.border
            )}>
                <div className="flex items-end gap-3">
                    <textarea
                        ref={inputRef}
                        value={inputMessage}
                        onChange={(e) => setInputMessage(e.target.value)}
                        onKeyPress={handleKeyPress}
                        placeholder="Ask me anything about your supply chain..."
                        rows={1}
                        className={cn(
                            "flex-1 resize-none rounded-lg px-4 py-3",
                            "bg-transparent border focus:outline-none focus:ring-2",
                            t.glass.border,
                            t.text.primary,
                            "focus:ring-blue-500/50",
                            "placeholder:text-gray-500"
                        )}
                        style={{
                            minHeight: '48px',
                            maxHeight: '120px'
                        }}
                        onInput={(e) => {
                            const target = e.target as HTMLTextAreaElement;
                            target.style.height = 'auto';
                            target.style.height = `${target.scrollHeight}px`;
                        }}
                    />
                    <button
                        onClick={() => sendMessage()}
                        disabled={!inputMessage.trim() || isLoading}
                        className={cn(
                            "p-3 rounded-lg transition-all duration-200",
                            "disabled:opacity-50 disabled:cursor-not-allowed",
                            inputMessage.trim() && !isLoading
                                ? "bg-gradient-to-r from-blue-600 to-blue-700 text-white hover:from-blue-700 hover:to-blue-800 shadow-lg hover:shadow-xl transform hover:scale-105"
                                : cn(t.glass.base, t.glass.border, t.text.secondary)
                        )}
                    >
                        <Send className="w-5 h-5" />
                    </button>
                </div>
                <p className={cn("text-xs mt-2", t.text.secondary, "opacity-70")}>
                    Press Enter to send, Shift+Enter for new line
                </p>
            </div>
        </div>
    );
} 