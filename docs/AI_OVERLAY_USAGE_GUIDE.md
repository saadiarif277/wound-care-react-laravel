# AI Overlay Usage Guide

## Overview

The MSC Wound Care AI Assistant now features a **hybrid voice/text architecture** powered by Azure OpenAI Services. This provides the best experience for different use cases.

## Two Modes of Interaction

### 🎤 Voice Mode (Hands-free)
- **Powered by**: Azure OpenAI Realtime API (`gpt-4o-mini-realtime-preview`)
- **Best for**: Quick questions, hands-free operation
- **Features**:
  - Ultra-low latency (~300ms)
  - Natural conversation flow
  - Interruption handling
  - Voice activity detection

### 💬 Text Mode (Documents & Forms)
- **Powered by**: Azure AI Foundry (`gpt-4o`)
- **Best for**: Document uploads, detailed forms, complex queries
- **Features**:
  - Document OCR processing
  - Markdown form generation
  - Image analysis
  - Azure Neural TTS voices

## How to Use

### Opening the AI Assistant
Click the AI assistant button or use the keyboard shortcut to open the overlay.

### Switching Modes
Use the mode switcher in the top-left corner:
- **Blue button**: Voice mode
- **Green button**: Text mode

### Voice Mode Usage
1. Click the microphone button to start speaking
2. Speak naturally - the AI will detect when you're done
3. You can also type in voice mode for hybrid interaction
4. The AI responds with natural voice

### Text Mode Usage
1. Type your message in the input field
2. Upload documents using the upload button
3. Fill out generated forms
4. Responses use Azure Neural TTS for natural speech

## Common Tasks

### Product Request
- **Voice**: "I need to submit a product request"
- **Text**: Click "Product Request" button or type your request

### Document Processing
- **Text Mode Only**: Upload insurance cards, clinical notes, or wound photos
- The AI will extract information and generate pre-filled forms

### Clinical Notes
- **Voice**: Start dictating by clicking "Clinical Notes"
- **Text**: Type detailed notes with formatting

## Mode Selection Tips

**Use Voice Mode when**:
- You need hands-free operation
- Making quick queries
- Having a conversation
- Dictating notes

**Use Text Mode when**:
- Uploading documents
- Filling complex forms
- Need to review/edit responses
- Working with detailed data

## Troubleshooting

### Voice Mode Not Available
- Check your microphone permissions
- Ensure you're using a supported browser (Chrome, Edge)
- Voice mode requires Azure Realtime API access

### No Sound in Text Mode
- Azure Speech Services provide natural TTS
- Falls back to browser TTS if unavailable
- Check your volume settings

### Switching Modes
- Current conversation context is maintained
- You can switch modes at any time
- Documents/forms remain available after switching

## Keyboard Shortcuts
- `Esc`: Close the AI overlay
- `Enter`: Send message (in text mode)
- `Shift+Enter`: New line in message

## Privacy & Security
- All interactions are HIPAA compliant
- PHI data is handled securely
- Voice recordings are not stored
- Azure services maintain data residency 