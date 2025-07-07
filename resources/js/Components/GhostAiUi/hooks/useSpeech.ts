
import { useRef, useEffect, useState } from 'react';
import { useToast } from './use-toast';

export const useSpeech = () => {
  const [isListening, setIsListening] = useState(false);
  const [isSpeaking, setIsSpeaking] = useState(false);
  const recognitionRef = useRef<SpeechRecognition | null>(null);
  const synthesisRef = useRef<SpeechSynthesis | null>(null);
  const { toast } = useToast();

  // Initialize speech recognition and synthesis
  useEffect(() => {
    if (typeof window !== 'undefined') {
      const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
      if (SpeechRecognition) {
        recognitionRef.current = new SpeechRecognition();
        recognitionRef.current.continuous = false;
        recognitionRef.current.interimResults = true;
        recognitionRef.current.lang = 'en-US';

        recognitionRef.current.onend = () => {
          setIsListening(false);
        };

        recognitionRef.current.onerror = (event) => {
          console.error('Speech recognition error:', event.error);
          setIsListening(false);
          toast({
            title: "Voice Error",
            description: "Failed to recognize speech. Please try again.",
            variant: "destructive"
          });
        };
      }

      synthesisRef.current = window.speechSynthesis;
    }
  }, [toast]);

  const toggleVoiceRecording = (onResult: (transcript: string) => void) => {
    if (!recognitionRef.current) {
      toast({
        title: "Voice Not Supported",
        description: "Speech recognition is not supported in this browser.",
        variant: "destructive"
      });
      return;
    }

    if (isListening) {
      recognitionRef.current.stop();
      setIsListening(false);
    } else {
      recognitionRef.current.onresult = (event) => {
        const transcript = Array.from(event.results)
          .map(result => result[0]?.transcript || '')
          .join('');
        onResult(transcript);
      };

      recognitionRef.current.start();
      setIsListening(true);
    }
  };

  const speak = async (text: string) => {
    // Stop any current speech
    if (synthesisRef.current) {
      synthesisRef.current.cancel();
    }

    try {
      // Try Azure Speech Services first for natural voice
      const response = await fetch('/api/v1/ai/text-to-speech', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          text,
          voice: 'en-US-JennyNeural', // Natural female voice
          rate: '0%', // Normal speed
          pitch: '0%', // Normal pitch
        }),
      });

      const data = await response.json();

      if (data.success && data.audio && !data.fallback) {
        // Play the audio using base64 data
        const audio = new Audio(`data:audio/mp3;base64,${data.audio}`);
        audio.volume = 0.8;
        
        const playPromise = new Promise<void>((resolve, reject) => {
          audio.onloadeddata = () => {
            setIsSpeaking(true);
            audio.play()
              .then(() => resolve())
              .catch(reject);
          };
          
          audio.onended = () => {
            setIsSpeaking(false);
            resolve();
          };
          
          audio.onerror = (error) => {
            setIsSpeaking(false);
            reject(error);
          };
        });
        
        await playPromise;
      } else {
        // Fallback to browser TTS if Azure fails
        fallbackToWebSpeech(text);
      }
    } catch (error) {
      console.error('Azure TTS failed:', error);
      // Fallback to browser TTS
      fallbackToWebSpeech(text);
    }
  };

  const fallbackToWebSpeech = (text: string) => {
    if (!synthesisRef.current) return;

    synthesisRef.current.cancel();

    const utterance = new SpeechSynthesisUtterance(text);
    utterance.rate = 0.9;
    utterance.pitch = 1;
    utterance.volume = 0.8;

    utterance.onstart = () => setIsSpeaking(true);
    utterance.onend = () => setIsSpeaking(false);
    utterance.onerror = () => setIsSpeaking(false);

    synthesisRef.current.speak(utterance);
  };

  const stopSpeaking = () => {
    if (synthesisRef.current) {
      synthesisRef.current.cancel();
      setIsSpeaking(false);
    }
  };

  return {
    isListening,
    isSpeaking,
    toggleVoiceRecording,
    speak,
    stopSpeaking,
    recognitionRef
  };
};
