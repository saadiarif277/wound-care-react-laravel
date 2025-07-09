import { useState, useRef, useCallback } from 'react';
import { useToast } from './use-toast';
import axios from 'axios';

export const useWhisperTranscription = () => {
  const [isRecording, setIsRecording] = useState(false);
  const [isTranscribing, setIsTranscribing] = useState(false);
  const mediaRecorderRef = useRef<MediaRecorder | null>(null);
  const audioChunksRef = useRef<Blob[]>([]);
  const { toast } = useToast();

  const startRecording = useCallback(async () => {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const mediaRecorder = new MediaRecorder(stream);
      mediaRecorderRef.current = mediaRecorder;
      audioChunksRef.current = [];

      mediaRecorder.ondataavailable = (event) => {
        if (event.data.size > 0) {
          audioChunksRef.current.push(event.data);
        }
      };

      mediaRecorder.start();
      setIsRecording(true);
      
      toast({
        title: "Recording",
        description: "Speak now... Click again to stop and transcribe.",
      });
    } catch (error) {
      console.error('Error accessing microphone:', error);
      toast({
        title: "Microphone Error",
        description: "Could not access microphone. Please check permissions.",
        variant: "destructive"
      });
    }
  }, [toast]);

  const stopRecording = useCallback(async (onTranscription: (text: string) => void) => {
    if (!mediaRecorderRef.current || !isRecording) return;

    return new Promise<void>((resolve) => {
      const mediaRecorder = mediaRecorderRef.current!;
      
      mediaRecorder.onstop = async () => {
        setIsRecording(false);
        setIsTranscribing(true);

        // Create audio blob
        const audioBlob = new Blob(audioChunksRef.current, { type: 'audio/webm' });
        
        // Stop all tracks
        mediaRecorder.stream.getTracks().forEach(track => track.stop());
        
        try {
          // Send to Whisper API
          const formData = new FormData();
          formData.append('audio', audioBlob, 'recording.webm');
          formData.append('model', 'whisper-1');
          formData.append('language', 'en');

          const response = await axios.post('/api/v1/ai/transcribe', formData, {
            headers: {
              'Content-Type': 'multipart/form-data',
            },
          });

          if (response.data.transcription) {
            onTranscription(response.data.transcription);
            toast({
              title: "Transcribed",
              description: "Your speech has been converted to text.",
            });
          }
        } catch (error) {
          console.error('Transcription error:', error);
          toast({
            title: "Transcription Error",
            description: "Failed to transcribe audio. Please try again.",
            variant: "destructive"
          });
        } finally {
          setIsTranscribing(false);
          resolve();
        }
      };

      mediaRecorder.stop();
    });
  }, [isRecording, toast]);

  const toggleRecording = useCallback(async (onTranscription: (text: string) => void) => {
    if (isRecording) {
      await stopRecording(onTranscription);
    } else {
      await startRecording();
    }
  }, [isRecording, startRecording, stopRecording]);

  return {
    isRecording,
    isTranscribing,
    toggleRecording
  };
}; 