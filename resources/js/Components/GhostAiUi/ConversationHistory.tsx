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
              ? 'bg-msc-blue-500 text-white ml-12 shadow-md'
              : 'bg-gray-100 text-gray-800 mr-12'
          }`}
        >
          <p className={`text-sm leading-relaxed ${msg.role === 'user' ? 'text-white font-medium' : 'text-gray-800'}`}>
            {msg.content}
          </p>
        </div>
      ))}
    </div>
  );
};

export default ConversationHistory;
