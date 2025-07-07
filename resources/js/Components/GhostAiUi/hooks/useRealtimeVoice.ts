import { useRef, useState, useCallback, useEffect } from 'react';
import { useToast } from './use-toast';

interface RealtimeSession {
  sessionId: string;
  websocket: WebSocket | null;
  isConnected: boolean;
}

interface RealtimeConfig {
  websocket_url: string;
  session_config: any;
  deployment: string;
  model: string;
}

export const useRealtimeVoice = () => {
  const [session, setSession] = useState<RealtimeSession | null>(null);
  const [isConnecting, setIsConnecting] = useState(false);
  const [isProcessing, setIsProcessing] = useState(false);
  const audioContextRef = useRef<AudioContext | null>(null);
  const mediaStreamRef = useRef<MediaStream | null>(null);
  const { toast } = useToast();

  // Initialize audio context
  useEffect(() => {
    if (typeof window !== 'undefined' && !audioContextRef.current) {
      audioContextRef.current = new (window.AudioContext || (window as any).webkitAudioContext)();
    }
  }, []);

  const createSession = useCallback(async () => {
    if (isConnecting || session?.isConnected) return;

    setIsConnecting(true);
    try {
      // Get session configuration from backend
      const response = await fetch('/api/v1/ai/realtime/session', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({ voice: 'alloy' })
      });

      if (!response.ok) {
        throw new Error('Failed to create realtime session');
      }

      const config: RealtimeConfig = await response.json();
      
      // Create WebSocket connection
      const ws = new WebSocket(config.websocket_url);
      
      ws.onopen = () => {
        console.log('Realtime WebSocket connected');
        
        // Send session configuration
        ws.send(JSON.stringify(config.session_config));
        
        setSession({
          sessionId: Date.now().toString(),
          websocket: ws,
          isConnected: true
        });
        
        toast({
          title: "Voice Mode Active",
          description: "You can now speak naturally. I'll respond in real-time.",
        });
      };

      ws.onmessage = (event) => {
        const message = JSON.parse(event.data);
        handleRealtimeMessage(message);
      };

      ws.onerror = (error) => {
        console.error('WebSocket error:', error);
        toast({
          title: "Connection Error",
          description: "Failed to connect to voice service. Switching to text mode.",
          variant: "destructive"
        });
      };

      ws.onclose = () => {
        console.log('WebSocket closed');
        setSession(null);
      };

    } catch (error) {
      console.error('Failed to create realtime session:', error);
      toast({
        title: "Voice Mode Unavailable",
        description: "Voice mode is not available. Using text mode instead.",
        variant: "destructive"
      });
    } finally {
      setIsConnecting(false);
    }
  }, [isConnecting, session, toast]);

  const handleRealtimeMessage = (message: any) => {
    switch (message.type) {
      case 'session.created':
        console.log('Session created:', message);
        break;
        
      case 'conversation.item.created':
        if (message.item.role === 'assistant') {
          setIsProcessing(false);
        }
        break;
        
      case 'response.audio.delta':
        // Handle audio chunk
        if (message.delta) {
          playAudioChunk(message.delta);
        }
        break;
        
      case 'response.audio_transcript.delta':
        // Handle transcript update
        console.log('Transcript:', message.delta);
        break;
        
      case 'error':
        console.error('Realtime error:', message.error);
        toast({
          title: "Voice Error",
          description: message.error.message || "An error occurred",
          variant: "destructive"
        });
        break;
    }
  };

  const playAudioChunk = async (audioData: string) => {
    if (!audioContextRef.current) return;

    try {
      // Decode base64 audio data
      const binaryData = atob(audioData);
      const arrayBuffer = new ArrayBuffer(binaryData.length);
      const view = new Uint8Array(arrayBuffer);
      
      for (let i = 0; i < binaryData.length; i++) {
        view[i] = binaryData.charCodeAt(i);
      }

      // Decode and play audio
      const audioBuffer = await audioContextRef.current.decodeAudioData(arrayBuffer);
      const source = audioContextRef.current.createBufferSource();
      source.buffer = audioBuffer;
      source.connect(audioContextRef.current.destination);
      source.start();
    } catch (error) {
      console.error('Audio playback error:', error);
    }
  };

  const startRecording = useCallback(async () => {
    if (!session?.websocket || !session.isConnected) {
      await createSession();
      return;
    }

    try {
      // Get user media
      mediaStreamRef.current = await navigator.mediaDevices.getUserMedia({ 
        audio: {
          echoCancellation: true,
          noiseSuppression: true,
          sampleRate: 24000
        } 
      });

      // Create audio processing pipeline
      const audioContext = audioContextRef.current;
      if (!audioContext) return;

      const source = audioContext.createMediaStreamSource(mediaStreamRef.current);
      const processor = audioContext.createScriptProcessor(4096, 1, 1);

      processor.onaudioprocess = (e) => {
        if (!session.websocket || session.websocket.readyState !== WebSocket.OPEN) return;

        const inputData = e.inputBuffer.getChannelData(0);
        const pcmData = convertFloat32ToInt16(inputData);
        const base64Audio = btoa(String.fromCharCode(...pcmData));

        // Send audio to realtime API
        session.websocket.send(JSON.stringify({
          type: 'input_audio_buffer.append',
          audio: base64Audio
        }));
      };

      source.connect(processor);
      processor.connect(audioContext.destination);

      setIsProcessing(true);
    } catch (error) {
      console.error('Recording error:', error);
      toast({
        title: "Microphone Error",
        description: "Could not access microphone. Please check permissions.",
        variant: "destructive"
      });
    }
  }, [session, createSession, toast]);

  const stopRecording = useCallback(() => {
    if (mediaStreamRef.current) {
      mediaStreamRef.current.getTracks().forEach(track => track.stop());
      mediaStreamRef.current = null;
    }

    if (session?.websocket && session.websocket.readyState === WebSocket.OPEN) {
      // Commit audio buffer to generate response
      session.websocket.send(JSON.stringify({
        type: 'input_audio_buffer.commit'
      }));
    }
  }, [session]);

  const sendText = useCallback((text: string) => {
    if (!session?.websocket || session.websocket.readyState !== WebSocket.OPEN) {
      toast({
        title: "Not Connected",
        description: "Voice mode is not connected. Please try again.",
        variant: "destructive"
      });
      return;
    }

    setIsProcessing(true);
    
    // Send text message in realtime session
    session.websocket.send(JSON.stringify({
      type: 'conversation.item.create',
      item: {
        type: 'message',
        role: 'user',
        content: [{
          type: 'input_text',
          text: text
        }]
      }
    }));

    // Trigger response
    session.websocket.send(JSON.stringify({
      type: 'response.create'
    }));
  }, [session, toast]);

  const closeSession = useCallback(() => {
    if (session?.websocket) {
      session.websocket.close();
    }
    if (mediaStreamRef.current) {
      mediaStreamRef.current.getTracks().forEach(track => track.stop());
    }
    setSession(null);
  }, [session]);

  // Helper function to convert audio format
  const convertFloat32ToInt16 = (buffer: Float32Array): Uint8Array => {
    const l = buffer.length;
    const buf = new Int16Array(l);
    
    for (let i = 0; i < l; i++) {
      buf[i] = Math.min(1, buffer[i]) * 0x7FFF;
    }
    
    return new Uint8Array(buf.buffer);
  };

  return {
    session,
    isConnecting,
    isProcessing,
    createSession,
    startRecording,
    stopRecording,
    sendText,
    closeSession
  };
}; 