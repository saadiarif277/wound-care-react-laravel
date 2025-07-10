import React, { useState, useEffect, useRef } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Textarea } from '@/Components/ui/textarea';
import { Mic, MicOff, Send, Bot, User, Brain, Factory, FileText, Volume2 } from 'lucide-react';
import api from '@/lib/api';

interface Message {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    timestamp: Date;
    mlEnhanced?: boolean;
    insuranceDataUsed?: boolean;
    confidence?: number;
    manufacturerInsights?: any[];
}

interface InsuranceAssistantProps {
    templateId?: string;
    manufacturer?: string;
    userId?: number;
    context?: any;
    onFormAssistance?: (assistance: any) => void;
}

export default function InsuranceAssistant({ 
    templateId, 
    manufacturer, 
    userId, 
    context = {},
    onFormAssistance 
}: InsuranceAssistantProps) {
    const [messages, setMessages] = useState<Message[]>([]);
    const [inputMessage, setInputMessage] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [threadId, setThreadId] = useState<string | null>(null);
    const [voiceEnabled, setVoiceEnabled] = useState(false);
    const [isListening, setIsListening] = useState(false);
    const [mlContext, setMlContext] = useState<any>(null);
    const [assistantReady, setAssistantReady] = useState(false);
    
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const recognition = useRef<any>(null);

    // Initialize the Insurance AI Assistant
    useEffect(() => {
        initializeAssistant();
    }, [templateId, manufacturer, userId]);

    // Auto-scroll to bottom when new messages arrive
    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    const initializeAssistant = async () => {
        try {
            setIsLoading(true);
            
            const response = await api.post('/api/v1/insurance-ai/start', {
                template_id: templateId,
                manufacturer: manufacturer,
                user_id: userId,
                context: context
            });

            if (response.data.success) {
                setThreadId(response.data.thread_id);
                setVoiceEnabled(response.data.voice_enabled);
                setMlContext(response.data.context);
                setAssistantReady(true);

                // Add initialization message
                const initMessage: Message = {
                    id: Date.now().toString(),
                    role: 'assistant',
                    content: response.data.initialization_message,
                    timestamp: new Date(),
                    mlEnhanced: true,
                    insuranceDataUsed: true
                };

                setMessages([initMessage]);
            }
        } catch (error) {
            console.error('Failed to initialize Insurance AI Assistant:', error);
        } finally {
            setIsLoading(false);
        }
    };

    const sendMessage = async (message: string = inputMessage) => {
        if (!message.trim() || !threadId || isLoading) return;

        const userMessage: Message = {
            id: Date.now().toString(),
            role: 'user',
            content: message,
            timestamp: new Date()
        };

        setMessages(prev => [...prev, userMessage]);
        setInputMessage('');
        setIsLoading(true);

        try {
            const response = await api.post('/api/v1/insurance-ai/message', {
                thread_id: threadId,
                message: message,
                context: {
                    ...context,
                    template_id: templateId,
                    manufacturer: manufacturer,
                    user_id: userId
                }
            });

            if (response.data.success) {
                const assistantMessage: Message = {
                    id: (Date.now() + 1).toString(),
                    role: 'assistant',
                    content: response.data.response.text || response.data.response.content || 'No response received',
                    timestamp: new Date(),
                    mlEnhanced: response.data.ml_enhanced,
                    insuranceDataUsed: response.data.insurance_data_used,
                    confidence: response.data.response.ml_confidence,
                    manufacturerInsights: response.data.response.manufacturer_insights
                };

                setMessages(prev => [...prev, assistantMessage]);
            }
        } catch (error) {
            console.error('Failed to send message:', error);
            const errorMessage: Message = {
                id: (Date.now() + 1).toString(),
                role: 'assistant',
                content: 'Sorry, I encountered an error. Please try again.',
                timestamp: new Date()
            };
            setMessages(prev => [...prev, errorMessage]);
        } finally {
            setIsLoading(false);
        }
    };

    const getFormAssistance = async () => {
        if (!templateId || !manufacturer || !threadId) return;

        setIsLoading(true);
        try {
            const response = await api.post('/api/v1/insurance-ai/form-assistance', {
                template_id: templateId,
                manufacturer: manufacturer,
                thread_id: threadId
            });

            if (response.data.success) {
                const assistanceMessage: Message = {
                    id: Date.now().toString(),
                    role: 'assistant',
                    content: response.data.assistance.text || response.data.assistance.content || 'Form assistance ready',
                    timestamp: new Date(),
                    mlEnhanced: true,
                    insuranceDataUsed: true,
                    confidence: response.data.assistance.confidence
                };

                setMessages(prev => [...prev, assistanceMessage]);
                
                // Pass assistance data to parent component
                if (onFormAssistance) {
                    onFormAssistance(response.data);
                }
            }
        } catch (error) {
            console.error('Failed to get form assistance:', error);
        } finally {
            setIsLoading(false);
        }
    };

    const getPersonalizedRecommendations = async () => {
        if (!userId || !threadId) return;

        setIsLoading(true);
        try {
            const response = await api.post('/api/v1/insurance-ai/recommendations', {
                user_id: userId,
                thread_id: threadId,
                context: {
                    template_id: templateId,
                    manufacturer: manufacturer
                }
            });

            if (response.data.success) {
                const recommendationsMessage: Message = {
                    id: Date.now().toString(),
                    role: 'assistant',
                    content: response.data.recommendations.text || response.data.recommendations.content || 'Personalized recommendations ready',
                    timestamp: new Date(),
                    mlEnhanced: true,
                    insuranceDataUsed: true,
                    confidence: response.data.recommendations.confidence
                };

                setMessages(prev => [...prev, recommendationsMessage]);
            }
        } catch (error) {
            console.error('Failed to get recommendations:', error);
        } finally {
            setIsLoading(false);
        }
    };

    const toggleVoiceInput = () => {
        if (!voiceEnabled) return;

        if (isListening) {
            recognition.current?.stop();
            setIsListening(false);
        } else {
            startVoiceInput();
        }
    };

    const startVoiceInput = () => {
        if (!('webkitSpeechRecognition' in window)) {
            alert('Voice input is not supported in this browser.');
            return;
        }

        recognition.current = new (window as any).webkitSpeechRecognition();
        recognition.current.continuous = true;
        recognition.current.interimResults = true;
        recognition.current.lang = 'en-US';

        recognition.current.onstart = () => {
            setIsListening(true);
        };

        recognition.current.onresult = (event: any) => {
            const transcript = Array.from(event.results)
                .map((result: any) => result[0])
                .map((result: any) => result.transcript)
                .join('');

            setInputMessage(transcript);
        };

        recognition.current.onerror = (event: any) => {
            console.error('Speech recognition error:', event.error);
            setIsListening(false);
        };

        recognition.current.onend = () => {
            setIsListening(false);
        };

        recognition.current.start();
    };

    const handleKeyPress = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    };

    const speakMessage = async (message: string) => {
        if ('speechSynthesis' in window) {
            const utterance = new SpeechSynthesisUtterance(message);
            utterance.voice = speechSynthesis.getVoices().find(voice => voice.name.includes('Jenny')) || speechSynthesis.getVoices()[0];
            utterance.rate = 0.9;
            utterance.pitch = 1.0;
            speechSynthesis.speak(utterance);
        }
    };

    if (!assistantReady) {
        return (
            <Card className="w-full max-w-4xl mx-auto">
                <CardContent className="p-6">
                    <div className="flex items-center justify-center space-x-3">
                        <Bot className="h-6 w-6 animate-pulse text-blue-500" />
                        <div>Initializing Insurance AI Assistant...</div>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className="w-full max-w-4xl mx-auto">
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center space-x-2">
                    <Bot className="h-6 w-6 text-blue-500" />
                    <span>Insurance AI Assistant</span>
                    {mlContext && (
                        <Badge variant="secondary" className="ml-2">
                            <Brain className="h-3 w-3 mr-1" />
                            ML Enhanced
                        </Badge>
                    )}
                    {manufacturer && (
                        <Badge variant="outline" className="ml-2">
                            <Factory className="h-3 w-3 mr-1" />
                            {manufacturer}
                        </Badge>
                    )}
                </CardTitle>
                
                {/* Quick Actions */}
                <div className="flex flex-wrap gap-2 mt-3">
                    {templateId && manufacturer && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={getFormAssistance}
                            disabled={isLoading}
                            className="flex items-center space-x-1"
                        >
                            <FileText className="h-3 w-3" />
                            <span>Form Assistance</span>
                        </Button>
                    )}
                    
                    {userId && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={getPersonalizedRecommendations}
                            disabled={isLoading}
                            className="flex items-center space-x-1"
                        >
                            <Brain className="h-3 w-3" />
                            <span>Personalized Tips</span>
                        </Button>
                    )}
                </div>
            </CardHeader>

            <CardContent className="p-4">
                {/* Messages */}
                <div className="space-y-4 max-h-96 overflow-y-auto mb-4">
                    {messages.map((message) => (
                        <div
                            key={message.id}
                            className={`flex ${message.role === 'user' ? 'justify-end' : 'justify-start'}`}
                        >
                            <div
                                className={`max-w-xs lg:max-w-md px-4 py-2 rounded-lg ${
                                    message.role === 'user'
                                        ? 'bg-blue-500 text-white'
                                        : 'bg-gray-100 text-gray-800'
                                }`}
                            >
                                <div className="flex items-center space-x-2 mb-1">
                                    {message.role === 'user' ? (
                                        <User className="h-4 w-4" />
                                    ) : (
                                        <Bot className="h-4 w-4" />
                                    )}
                                    <span className="text-sm font-medium">
                                        {message.role === 'user' ? 'You' : 'Assistant'}
                                    </span>
                                    {message.mlEnhanced && (
                                        <Badge variant="secondary" className="text-xs">
                                            ML
                                        </Badge>
                                    )}
                                    {message.insuranceDataUsed && (
                                        <Badge variant="secondary" className="text-xs">
                                            Insurance Data
                                        </Badge>
                                    )}
                                    {message.role === 'assistant' && (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => speakMessage(message.content)}
                                            className="h-4 w-4 p-0"
                                        >
                                            <Volume2 className="h-3 w-3" />
                                        </Button>
                                    )}
                                </div>
                                <p className="text-sm whitespace-pre-wrap">{message.content}</p>
                                {message.confidence && (
                                    <div className="mt-1 text-xs opacity-75">
                                        Confidence: {(message.confidence * 100).toFixed(1)}%
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                    <div ref={messagesEndRef} />
                </div>

                {/* Input */}
                <div className="flex space-x-2">
                    <div className="flex-1 relative">
                        <Textarea
                            value={inputMessage}
                            onChange={(e) => setInputMessage(e.target.value)}
                            onKeyPress={handleKeyPress}
                            placeholder="Ask about insurance forms, field mapping, or get personalized assistance..."
                            className="min-h-[60px] pr-12"
                            disabled={isLoading}
                        />
                        
                        {voiceEnabled && (
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={toggleVoiceInput}
                                className={`absolute right-2 top-2 h-8 w-8 p-0 ${
                                    isListening ? 'text-red-500' : 'text-gray-400'
                                }`}
                            >
                                {isListening ? <MicOff className="h-4 w-4" /> : <Mic className="h-4 w-4" />}
                            </Button>
                        )}
                    </div>
                    
                    <Button
                        onClick={() => sendMessage()}
                        disabled={isLoading || !inputMessage.trim()}
                        className="flex items-center space-x-2"
                    >
                        <Send className="h-4 w-4" />
                        <span>Send</span>
                    </Button>
                </div>

                {/* Status */}
                {mlContext && (
                    <div className="mt-3 text-xs text-gray-500">
                        <div className="flex items-center space-x-4">
                            <div>Thread: {threadId?.slice(-8)}</div>
                            {mlContext.manufacturer_patterns && (
                                <div>Patterns: {Object.keys(mlContext.manufacturer_patterns).length}</div>
                            )}
                            {mlContext.user_patterns && (
                                <div>User Features: {Object.keys(mlContext.user_patterns).length}</div>
                            )}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
} 