import { useRef, useState, useCallback, useEffect } from 'react';
import { useToast } from './use-toast';
import api from '@/lib/api';

interface RealtimeSession {
  sessionId: string;
  websocket: WebSocket | null;
  isConnected: boolean;
}

interface RealtimeConfig {
  success: boolean;
  session_id: string;
  websocket_url: string;
  model: string;
  deployment: string;
  session_config: any;
  warning?: string;
}

export const useRealtimeVoice = () => {
  const [session, setSession] = useState<RealtimeSession | null>(null);
  const [isConnecting, setIsConnecting] = useState(false);
  const [isProcessing, setIsProcessing] = useState(false);
  const [isRecording, setIsRecording] = useState(false);
  const audioContextRef = useRef<AudioContext | null>(null);
  const mediaStreamRef = useRef<MediaStream | null>(null);
  const audioQueueRef = useRef<string[]>([]);
  const isPlayingRef = useRef(false);
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
      const data = await api.post<RealtimeConfig>('/api/v1/ai/realtime/session', {
        voice: 'alloy',
      });

      const websocket = new WebSocket(data.websocket_url);
      setSession({ ...data, websocket });
      
      websocket.onopen = () => {
        console.log('Realtime WebSocket connected');
        
        // Debug: Log the session config being sent
        console.log('Sending session config:', JSON.stringify(data.session_config, null, 2));
        
        try {
          // Send session configuration
          const configString = JSON.stringify(data.session_config);
          console.log('Stringified config length:', configString.length);
          websocket.send(configString);
          
          const newSession = {
            sessionId: data.session_id,
            websocket: websocket,
            isConnected: true
          };
          
          setSession(newSession);
          console.log('Session state updated:', newSession);
          
          toast({
            title: "Voice Mode Active",
            description: "You can now speak naturally. Click the microphone to start.",
          });
        } catch (error) {
          console.error('Error sending session config:', error);
          toast({
            title: "Configuration Error",
            description: "Failed to configure voice session.",
            variant: "destructive"
          });
          websocket.close();
        }
      };

      websocket.onmessage = (event) => {
        try {
          console.log('Raw message received:', event.data);
          const message = JSON.parse(event.data);
          console.log('Parsed message:', message);
          handleRealtimeMessage(message);
        } catch (error) {
          console.error('Error parsing WebSocket message:', error);
          console.error('Raw data:', event.data);
        }
      };

      websocket.onerror = (error) => {
        console.error('WebSocket error:', error);
        console.error('WebSocket readyState:', websocket.readyState);
        console.error('WebSocket URL:', websocket.url);
        toast({
          title: "Connection Error",
          description: "Failed to connect to voice service. Switching to text mode.",
          variant: "destructive"
        });
      };

      websocket.onclose = () => {
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
    console.log('Handling message type:', message.type);
    
    switch (message.type) {
      case 'session.created':
        console.log('Session created:', message);
        break;
        
      case 'session.updated':
        console.log('Session updated successfully');
        break;
        
      case 'conversation.item.created':
        console.log('Conversation item created:', message);
        if (message.item.role === 'assistant') {
          setIsProcessing(false);
        }
        break;
        
      case 'response.audio.delta':
        console.log('Audio delta received, length:', message.delta?.length);
        // Handle audio chunk
        if (message.delta) {
          playAudioChunk(message.delta);
        }
        break;
        
      case 'response.audio_transcript.delta':
        // Handle transcript update
        console.log('Transcript delta:', message.delta);
        break;
        
      case 'error':
        console.error('Realtime error:', message.error);
        toast({
          title: "Voice Error",
          description: message.error.message || "An error occurred",
          variant: "destructive"
        });
        break;
        
      default:
        console.log('Unhandled message type:', message.type);
    }
  };

  const playAudioChunk = useCallback((audioData: string) => {
    console.log('Adding audio chunk to queue, length:', audioData.length);
    audioQueueRef.current.push(audioData);
    
    // If not already playing, start processing the queue
    if (!isPlayingRef.current) {
      processAudioQueue();
    }
  }, []);

  const processAudioQueue = async () => {
    if (!audioContextRef.current || isPlayingRef.current) {
      return;
    }

    isPlayingRef.current = true;

    while (audioQueueRef.current.length > 0) {
      const audioData = audioQueueRef.current.shift()!;
      
      try {
        console.log('Processing audio chunk from queue');
        
        // Decode base64 audio data
        const binaryString = atob(audioData);
        const bytes = new Uint8Array(binaryString.length);
        
        for (let i = 0; i < binaryString.length; i++) {
          bytes[i] = binaryString.charCodeAt(i);
        }
        
        // Convert PCM16 to Float32 for Web Audio API
        const pcm16 = new Int16Array(bytes.buffer);
        const float32 = new Float32Array(pcm16.length);
        
        for (let i = 0; i < pcm16.length; i++) {
          float32[i] = pcm16[i] / 32768.0; // Convert to -1.0 to 1.0 range
        }
        
        // Create audio buffer
        const sampleRate = 24000; // Azure uses 24kHz for output
        const audioBuffer = audioContextRef.current.createBuffer(1, float32.length, sampleRate);
        audioBuffer.copyToChannel(float32, 0);
        
        // Play the audio and wait for it to finish
        await new Promise<void>((resolve) => {
          const source = audioContextRef.current!.createBufferSource();
          source.buffer = audioBuffer;
          source.connect(audioContextRef.current!.destination);
          source.onended = () => resolve();
          source.start();
        });
        
      } catch (error) {
        console.error('Audio playback error:', error);
      }
    }

    isPlayingRef.current = false;
    console.log('Audio queue processing complete');
  };

  const startRecording = useCallback(async () => {
    console.log('startRecording called, session:', session);
    
    if (!session?.websocket || !session.isConnected) {
      console.log('No active session. Please wait for connection...');
      toast({
        title: "Please wait",
        description: "Voice connection is being established...",
      });
      return;
    }

    try {
      console.log('Requesting microphone permission...');
      // Get user media with PCM16 compatible settings
      mediaStreamRef.current = await navigator.mediaDevices.getUserMedia({ 
        audio: {
          echoCancellation: true,
          noiseSuppression: true,
          sampleRate: 16000,  // PCM16 typically uses 16kHz
          channelCount: 1     // Mono audio
        } 
      });
      
      console.log('Microphone permission granted, stream:', mediaStreamRef.current);

      // Create audio context for processing
      console.log('Creating audio context...');
      const audioContext = new AudioContext({ sampleRate: 16000 });
      audioContextRef.current = audioContext;
      console.log('Audio context created:', audioContext);

      console.log('Creating media stream source...');
      const source = audioContext.createMediaStreamSource(mediaStreamRef.current);
      console.log('Media stream source created:', source);
      
      console.log('Creating script processor...');
      const processor = audioContext.createScriptProcessor(2048, 1, 1);
      console.log('Script processor created:', processor);

      let audioChunkCount = 0;
      processor.onaudioprocess = (e) => {
        if (!session.websocket || session.websocket.readyState !== WebSocket.OPEN) {
          console.log('WebSocket not ready, skipping audio chunk');
          return;
        }

        const inputData = e.inputBuffer.getChannelData(0);
        
        // Check if we have audio data (not silence)
        const hasAudio = inputData.some(sample => Math.abs(sample) > 0.01);
        
        if (audioChunkCount < 5 || hasAudio) {  // Log first 5 chunks and any with audio
          audioChunkCount++;
          console.log(`Audio chunk ${audioChunkCount}: hasAudio=${hasAudio}, samples:`, inputData.slice(0, 5));
        }
        
        if (hasAudio) {
          const pcmData = convertFloat32ToInt16(inputData);
          const base64Audio = btoa(String.fromCharCode(...new Uint8Array(pcmData.buffer)));

          // Send audio to realtime API
          const audioMessage = {
            type: 'input_audio_buffer.append',
            audio: base64Audio
          };
          
          if (audioChunkCount <= 5) {
            console.log('Sending audio message:', audioMessage.type, 'audio length:', base64Audio.length);
          }
          
          session.websocket.send(JSON.stringify(audioMessage));
        }
      };

      // Connect audio nodes
      console.log('Connecting audio nodes...');
      source.connect(processor);
      processor.connect(audioContext.destination);
      console.log('Audio nodes connected');

      console.log('Recording started successfully');
      setIsRecording(true);
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
    console.log('Stopping recording...');
    
    setIsRecording(false);
    
    if (mediaStreamRef.current) {
      mediaStreamRef.current.getTracks().forEach(track => track.stop());
      mediaStreamRef.current = null;
    }

    // Clear any pending audio
    audioQueueRef.current = [];
    isPlayingRef.current = false;

    if (session?.websocket && session.websocket.readyState === WebSocket.OPEN) {
      console.log('Committing audio buffer and triggering response...');
      
      // Commit audio buffer to generate response
      session.websocket.send(JSON.stringify({
        type: 'input_audio_buffer.commit'
      }));
      
      // Trigger a response generation
      session.websocket.send(JSON.stringify({
        type: 'response.create'
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

  // Helper function to convert audio format to PCM16
  const convertFloat32ToInt16 = (buffer: Float32Array): Int16Array => {
    const l = buffer.length;
    const buf = new Int16Array(l);
    
    for (let i = 0; i < l; i++) {
      // Clamp the value between -1 and 1, then scale to 16-bit
      const s = Math.max(-1, Math.min(1, buffer[i]));
      buf[i] = s < 0 ? s * 0x8000 : s * 0x7FFF;
    }
    
    return buf;
  };

  const fetchTtsAudio = async (text: string): Promise<string | null> => {
    try {
      const response = await api.post('/api/v1/tts', {
        text,
        language: 'en-US',
        voice: 'en-US-JennyNeural' 
      });

      if (!response.ok) {
        throw new Error('Server responded with an error');
      }

      const data = await response.json();
      return data.audioContent; // Assuming server returns { audioContent: 'base64...' }
    } catch (error) {
      console.error('Error fetching TTS audio:', error);
      return null;
    }
  };

  return {
    session,
    isConnecting,
    isProcessing,
    isRecording,
    createSession,
    startRecording,
    stopRecording,
    sendText,
    closeSession
  };
}; 