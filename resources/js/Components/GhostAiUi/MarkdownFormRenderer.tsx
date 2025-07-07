import React, { useMemo, useCallback } from 'react';
import { Input } from './ui/input';
import { Button } from './ui/button';
import { Textarea } from './ui/textarea';
import { fetchWithCSRF } from '@/utils/csrf';

interface MarkdownFormRendererProps {
  content: string;
  onFieldChange: (fieldId: string, value: any) => void;
  onAction: (action: string) => void;
  values?: Record<string, any>;
}

const MarkdownFormRenderer: React.FC<MarkdownFormRendererProps> = ({
  content,
  onFieldChange,
  onAction,
  values = {}
}) => {
  const handleFormAction = useCallback(async (action: string) => {
    // Check if this is a form submission action that needs backend processing
    const formSubmissionActions = ['submit_product_request', 'save_clinical_notes', 'save_draft'];
    
    if (formSubmissionActions.includes(action)) {
      try {
        const response = await fetchWithCSRF('/api/ai/form-action', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            action,
            form_data: values
          }),
        });

        if (response.ok) {
          const result = await response.json();
          // Call the original onAction with success info
          onAction(action + '_success');
          
          // You could also trigger a conversation update here if needed
          console.log('Form submitted successfully:', result.reply);
        } else {
          throw new Error('Form submission failed');
        }
      } catch (error) {
        console.error('Form submission error:', error);
        onAction(action + '_error');
      }
    } else {
      // For non-form actions, just call the original handler
      onAction(action);
    }
  }, [values, onAction]);

  const renderedContent = useMemo(() => {
    // Parse markdown and replace custom components
    const lines = content.split('\n');
    const elements: JSX.Element[] = [];
    let key = 0;

    lines.forEach((line) => {
      key++;
      
      // Headers
      if (line.startsWith('## ')) {
        elements.push(
          <h2 key={key} className="text-xl font-semibold text-gray-800 mb-4 mt-6">
            {line.substring(3)}
          </h2>
        );
        return;
      }

      // Bold text
      line = line.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
      
      // Input fields: [input:field_id|default_value]
      const inputMatch = line.match(/\[input:([^|]+)\|?([^\]]*)\]/);
      if (inputMatch) {
        const [fullMatch, fieldId, defaultValue] = inputMatch;
        if (!fieldId) return;
        
        const beforeText = line.substring(0, line.indexOf(fullMatch));
        const afterText = line.substring(line.indexOf(fullMatch) + fullMatch.length);
        
        elements.push(
          <div key={key} className="mb-3">
            {beforeText && (
              <span dangerouslySetInnerHTML={{ __html: beforeText }} className="text-gray-700 mr-2" />
            )}
            <Input
              id={fieldId}
              value={values[fieldId] || defaultValue || ''}
              onChange={(e) => onFieldChange(fieldId, e.target.value)}
              className="inline-block w-64 mx-1"
              placeholder={fieldId.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
            />
            {afterText && (
              <span dangerouslySetInnerHTML={{ __html: afterText }} className="text-gray-700 ml-2" />
            )}
          </div>
        );
        return;
      }

      // Date fields: [date:field_id|default_value]
      const dateMatch = line.match(/\[date:([^|]+)\|?([^\]]*)\]/);
      if (dateMatch) {
        const [fullMatch, fieldId, defaultValue] = dateMatch;
        if (!fieldId) return;
        
        const beforeText = line.substring(0, line.indexOf(fullMatch));
        const afterText = line.substring(line.indexOf(fullMatch) + fullMatch.length);
        
        elements.push(
          <div key={key} className="mb-3">
            {beforeText && (
              <span dangerouslySetInnerHTML={{ __html: beforeText }} className="text-gray-700 mr-2" />
            )}
            <Input
              type="date"
              id={fieldId}
              value={values[fieldId] || defaultValue || ''}
              onChange={(e) => onFieldChange(fieldId, e.target.value)}
              className="inline-block w-48 mx-1"
            />
            {afterText && (
              <span dangerouslySetInnerHTML={{ __html: afterText }} className="text-gray-700 ml-2" />
            )}
          </div>
        );
        return;
      }

      // Select fields: [select:field_id|option1|option2|...|default]
      const selectMatch = line.match(/\[select:([^|]+)\|([^\]]+)\]/);
      if (selectMatch) {
        const [fullMatch, fieldId, optionsStr] = selectMatch;
        if (!fieldId || !optionsStr) return;
        
        const options = optionsStr.split('|');
        const defaultValue = options[options.length - 1];
        const beforeText = line.substring(0, line.indexOf(fullMatch));
        const afterText = line.substring(line.indexOf(fullMatch) + fullMatch.length);
        
        elements.push(
          <div key={key} className="mb-3">
            {beforeText && (
              <span dangerouslySetInnerHTML={{ __html: beforeText }} className="text-gray-700 mr-2" />
            )}
            <select
              id={fieldId}
              value={values[fieldId] || defaultValue || ''}
              onChange={(e) => onFieldChange(fieldId, e.target.value)}
              className="inline-block w-48 mx-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-msc-blue-500"
            >
              <option value="">Select...</option>
              {options.slice(0, -1).map((opt) => (
                <option key={opt} value={opt}>{opt}</option>
              ))}
            </select>
            {afterText && (
              <span dangerouslySetInnerHTML={{ __html: afterText }} className="text-gray-700 ml-2" />
            )}
          </div>
        );
        return;
      }

      // Textarea fields: [textarea:field_id|default_value]
      const textareaMatch = line.match(/\[textarea:([^|]+)\|?([^\]]*)\]/);
      if (textareaMatch) {
        const [fullMatch, fieldId, defaultValue] = textareaMatch;
        if (!fieldId) return;
        
        const beforeText = line.substring(0, line.indexOf(fullMatch));
        
        elements.push(
          <div key={key} className="mb-3">
            {beforeText && (
              <span dangerouslySetInnerHTML={{ __html: beforeText }} className="text-gray-700 block mb-1" />
            )}
            <Textarea
              id={fieldId}
              value={values[fieldId] || defaultValue || ''}
              onChange={(e) => onFieldChange(fieldId, e.target.value)}
              className="w-full"
              rows={3}
              placeholder={fieldId.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
            />
          </div>
        );
        return;
      }

      // Buttons: [button:text|action]
      const buttonMatch = line.match(/\[button:([^|]+)\|([^\]]+)\]/);
      if (buttonMatch) {
        const [_, text, action] = buttonMatch;
        elements.push(
          <Button
            key={key}
            onClick={() => handleFormAction(action || '')}
            className="mb-3"
          >
            {text}
          </Button>
        );
        return;
      }

      // File preview: [file-preview:src]
      const filePreviewMatch = line.match(/\[file-preview:([^\]]+)\]/);
      if (filePreviewMatch) {
        const [_, src] = filePreviewMatch;
        elements.push(
          <div key={key} className="mb-4">
            <img 
              src={src} 
              alt="Document preview" 
              className="max-w-md rounded-lg shadow-md border border-gray-200"
            />
          </div>
        );
        return;
      }

      // Document preview: [document-preview:src]
      const docPreviewMatch = line.match(/\[document-preview:([^\]]+)\]/);
      if (docPreviewMatch) {
        const [_, src] = docPreviewMatch;
        elements.push(
          <div key={key} className="mb-4">
            <iframe 
              src={src} 
              className="w-full h-96 rounded-lg shadow-md border border-gray-200"
              title="Document preview"
            />
          </div>
        );
        return;
      }

      // Horizontal rule
      if (line === '---') {
        elements.push(<hr key={key} className="my-6 border-gray-300" />);
        return;
      }

      // List items
      if (line.startsWith('- ')) {
        elements.push(
          <li key={key} className="ml-4 text-gray-700 mb-1">
            <span dangerouslySetInnerHTML={{ __html: line.substring(2) }} />
          </li>
        );
        return;
      }

      // Regular paragraph
      if (line.trim()) {
        elements.push(
          <p key={key} className="text-gray-700 mb-3">
            <span dangerouslySetInnerHTML={{ __html: line }} />
          </p>
        );
      }
    });

    return elements;
  }, [content, values, onFieldChange, handleFormAction]);

  return (
    <div className="markdown-form-container">
      {renderedContent}
    </div>
  );
};

export default MarkdownFormRenderer;