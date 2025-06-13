import React from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';

export default function AIOverlayDemo() {
  return (
    <MainLayout title="AI Overlay Demo">
      <Head title="AI Overlay Demo" />

      <div className="space-y-6">
        <div className="text-center">
          <h1 className="text-3xl font-bold text-gray-900 mb-4">
            AI Overlay Demo
          </h1>
          <p className="text-lg text-gray-600 mb-8">
            Test the AI overlay functionality by clicking the floating AI button in the bottom right corner.
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div className="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
            <h2 className="text-xl font-semibold text-gray-900 mb-4">
              AI Overlay Features
            </h2>
            <ul className="space-y-2 text-gray-600">
              <li className="flex items-center">
                <span className="w-2 h-2 bg-blue-500 rounded-full mr-3"></span>
                Voice input and output capabilities
              </li>
              <li className="flex items-center">
                <span className="w-2 h-2 bg-blue-500 rounded-full mr-3"></span>
                Conversation history tracking
              </li>
              <li className="flex items-center">
                <span className="w-2 h-2 bg-blue-500 rounded-full mr-3"></span>
                Escalation to human support
              </li>
              <li className="flex items-center">
                <span className="w-2 h-2 bg-blue-500 rounded-full mr-3"></span>
                Clinical notes recording
              </li>
              <li className="flex items-center">
                <span className="w-2 h-2 bg-blue-500 rounded-full mr-3"></span>
                Product request assistance
              </li>
              <li className="flex items-center">
                <span className="w-2 h-2 bg-blue-500 rounded-full mr-3"></span>
                Transparency controls
              </li>
            </ul>
          </div>

          <div className="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
            <h2 className="text-xl font-semibold text-gray-900 mb-4">
              How to Use
            </h2>
            <ol className="space-y-2 text-gray-600">
              <li className="flex items-start">
                <span className="flex-shrink-0 w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-sm font-medium mr-3 mt-0.5">
                  1
                </span>
                Click the floating AI button in the bottom right corner
              </li>
              <li className="flex items-start">
                <span className="flex-shrink-0 w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-sm font-medium mr-3 mt-0.5">
                  2
                </span>
                Type your message or use voice input
              </li>
              <li className="flex items-start">
                <span className="flex-shrink-0 w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-sm font-medium mr-3 mt-0.5">
                  3
                </span>
                Try the special action buttons for product requests or clinical notes
              </li>
              <li className="flex items-start">
                <span className="flex-shrink-0 w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-sm font-medium mr-3 mt-0.5">
                  4
                </span>
                Use the help button to escalate to human support if needed
              </li>
            </ol>
          </div>
        </div>

        <div className="bg-blue-50 border border-blue-200 rounded-lg p-6">
          <h3 className="text-lg font-semibold text-blue-900 mb-2">
            Note
          </h3>
          <p className="text-blue-800">
            This is a demo implementation. In a production environment, you would integrate with your actual AI service,
            support ticketing system, and clinical documentation workflows.
          </p>
        </div>
      </div>
    </MainLayout>
  );
}
