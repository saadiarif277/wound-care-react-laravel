import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import Logo from '@/Components/Logo/Logo';
import { FiLock, FiArrowLeft, FiCheck } from 'react-icons/fi';

interface Props {
  token: string;
  email: string;
}

export default function ResetPassword({ token, email }: Props) {
  const { data, setData, errors, post, processing } = useForm({
    token,
    email,
    password: '',
    password_confirmation: ''
  });

  function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    post(route('password.update'));
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
      <Head title="MSC Wound Portal - Reset Password" />

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
                Set New Password
              </h2>
              <p className="text-gray-600 text-sm">
                Enter your new password below to complete the reset process.
              </p>
            </div>

            {/* Form */}
            <div className="px-8 pb-8">
              <form onSubmit={handleSubmit} className="space-y-6">
                {/* Password Field */}
                <div>
                  <label htmlFor="password" className="block text-sm font-semibold text-gray-700 mb-2">
                    New Password
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                      <FiLock className="h-5 w-5 text-gray-400" />
                    </div>
                    <input
                      id="password"
                      type="password"
                      className={`w-full pl-12 pr-4 py-4 border rounded-xl focus:outline-none focus:ring-2 transition-all duration-200 shadow-sm ${
                        errors.password
                          ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                          : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400'
                      }`}
                      style={{
                        '--tw-ring-color': errors.password ? '#ef4444' : '#1925c3'
                      } as React.CSSProperties}
                      value={data.password}
                      onChange={(e) => setData('password', e.target.value)}
                      placeholder="Enter your new password"
                      disabled={processing}
                      required
                      autoFocus
                    />
                  </div>
                  {errors.password && (
                    <p className="mt-2 text-sm text-red-600 flex items-center">
                      <span className="mr-1">⚠️</span>
                      {errors.password}
                    </p>
                  )}
                </div>

                {/* Confirm Password Field */}
                <div>
                  <label htmlFor="password_confirmation" className="block text-sm font-semibold text-gray-700 mb-2">
                    Confirm New Password
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                      <FiCheck className="h-5 w-5 text-gray-400" />
                    </div>
                    <input
                      id="password_confirmation"
                      type="password"
                      className={`w-full pl-12 pr-4 py-4 border rounded-xl focus:outline-none focus:ring-2 transition-all duration-200 shadow-sm ${
                        errors.password_confirmation
                          ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                          : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400'
                      }`}
                      style={{
                        '--tw-ring-color': errors.password_confirmation ? '#ef4444' : '#1925c3'
                      } as React.CSSProperties}
                      value={data.password_confirmation}
                      onChange={(e) => setData('password_confirmation', e.target.value)}
                      placeholder="Confirm your new password"
                      disabled={processing}
                      required
                    />
                  </div>
                  {errors.password_confirmation && (
                    <p className="mt-2 text-sm text-red-600 flex items-center">
                      <span className="mr-1">⚠️</span>
                      {errors.password_confirmation}
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
                      Updating Password...
                    </>
                  ) : (
                    <>
                      <FiLock className="mr-2 h-5 w-5" />
                      Update Password
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
