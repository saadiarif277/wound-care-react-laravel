import React from 'react';

interface Message {
  role: 'user' | 'assistant';
  content: string;
}

interface ConversationHistoryProps {
  conversation: Message[];
}

const ConversationHistory: React.FC<ConversationHistoryProps> = ({ conversation }) => {
  if (conversation.length === 0) return null;

  return (
    <div className="mb-6 max-h-60 overflow-y-auto space-y-3">
      {conversation.slice(-4).map((msg, index) => (
        <div
          key={index}
          className={`p-4 rounded-2xl ${
            msg.role === 'user'
              ? 'bg-white/90 text-gray-800 ml-12 shadow-md border border-gray-200'
              : 'bg-gray-100/90 text-gray-800 mr-12 shadow-md border border-gray-300'
          }`}
        >
          <p className={`text-sm leading-relaxed ${msg.role === 'user' ? 'font-medium' : ''}`}>
            {msg.content}
          </p>
        </div>
      ))}
    </div>
  );
};

export default ConversationHistory;
