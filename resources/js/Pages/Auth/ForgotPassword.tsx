import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import Logo from '@/Components/Logo/Logo';
import { FiMail, FiArrowLeft, FiSend } from 'react-icons/fi';

export default function ForgotPassword({ status }: { status?: string }) {
  const { data, setData, errors, post, processing } = useForm({
    email: ''
  });

  const [isSubmitted, setIsSubmitted] = useState(false);

  function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    post(route('password.email'), {
      onSuccess: () => setIsSubmitted(true)
    });
  }

  return (
    <div 
      className="min-h-screen relative overflow-hidden"
      style={{
        backgroundImage: 'url(/login-background.jpg)',
        backgroundSize: 'cover',
        backgroundPosition: 'center',
        backgroundRepeat: 'no-repeat',
        backgroundAttachment: 'fixed'
      }}
    >
      <Head title="MSC Wound Portal - Forgot Password" />

      {/* Dark Overlay for readability */}
      <div className="absolute inset-0 bg-black bg-opacity-50"></div>

      {/* Content */}
      <div className="relative z-10 min-h-screen flex items-center justify-center px-4">
        <div className="w-full max-w-md">
          <div className="bg-white/95 backdrop-blur-sm rounded-3xl shadow-2xl border border-white/20 overflow-hidden">
            {/* Header with Logo */}
            <div className="px-8 pt-8 pb-6 text-center">
              <div className="flex justify-center mb-4">
                <Logo className="h-20 w-auto" />
              </div>
              <h2 className="text-2xl font-bold text-gray-800 mb-2">
                Reset Your Password
              </h2>
              <p className="text-gray-600 text-sm">
                Enter your email address and we'll send you instructions to reset your password.
              </p>
            </div>

            {/* Form */}
            <div className="px-8 pb-8">
              {status && (
                <div className="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                  <p className="text-sm text-green-800">{status}</p>
                </div>
              )}

              {isSubmitted ? (
                <div className="text-center py-8">
                  <div className="mb-4">
                    <div className="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center">
                      <FiSend className="h-8 w-8 text-green-600" />
                    </div>
                  </div>
                  <h3 className="text-lg font-semibold text-gray-800 mb-2">
                    Check Your Email
                  </h3>
                  <p className="text-gray-600 text-sm mb-6">
                    We've sent password reset instructions to {data.email}
                  </p>
                  <Link
                    href={route('login')}
                    className="inline-flex items-center text-sm font-medium hover:underline transition-colors"
                    style={{ color: '#1925c3' }}
                  >
                    <FiArrowLeft className="mr-2 h-4 w-4" />
                    Back to Login
                  </Link>
                </div>
              ) : (
                <form onSubmit={handleSubmit} className="space-y-6">
                  {/* Email Field */}
                  <div>
                    <label htmlFor="email" className="block text-sm font-semibold text-gray-700 mb-2">
                      Email Address
                    </label>
                    <div className="relative">
                      <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <FiMail className="h-5 w-5 text-gray-400" />
                      </div>
                      <input
                        id="email"
                        type="email"
                        className={`w-full pl-12 pr-4 py-4 border rounded-xl focus:outline-none focus:ring-2 transition-all duration-200 shadow-sm ${
                          errors.email
                            ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                            : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400'
                        }`}
                        style={{
                          '--tw-ring-color': errors.email ? '#ef4444' : '#1925c3'
                        } as React.CSSProperties}
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="Enter your email address"
                        disabled={processing}
                        required
                        autoFocus
                      />
                    </div>
                    {errors.email && (
                      <p className="mt-2 text-sm text-red-600 flex items-center">
                        <span className="mr-1">⚠️</span>
                        {errors.email}
                      </p>
                    )}
                  </div>

                  {/* Submit Button */}
                  <button
                    type="submit"
                    disabled={processing}
                    className="w-full py-4 px-4 rounded-xl text-white font-semibold text-base transition-all duration-200 disabled:opacity-70 disabled:cursor-not-allowed flex justify-center items-center focus:outline-none focus:ring-2 focus:ring-offset-2 shadow-lg"
                    style={{
                      backgroundColor: '#1925c3',
                      '--tw-ring-color': '#1925c3'
                    } as React.CSSProperties}
                  >
                    {processing ? (
                      <>
                        <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Sending...
                      </>
                    ) : (
                      <>
                        <FiMail className="mr-2 h-5 w-5" />
                        Send Reset Link
                      </>
                    )}
                  </button>

                  {/* Back to Login Link */}
                  <div className="text-center">
                    <Link
                      href={route('login')}
                      className="inline-flex items-center text-sm font-medium hover:underline transition-colors"
                      style={{ color: '#1925c3' }}
                    >
                      <FiArrowLeft className="mr-2 h-4 w-4" />
                      Back to Login
                    </Link>
                  </div>
                </form>
              )}

              {/* Footer */}
              <div className="mt-8 text-center">
                <p className="text-xs text-gray-500 font-medium">
                  MSC Wound Care © 2025
                </p>
                <p className="text-xs text-gray-400 mt-1">
                  Secure healthcare management platform
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
