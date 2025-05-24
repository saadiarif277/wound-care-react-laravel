import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import Logo from '@/Components/Logo/Logo';
import { FiMail, FiLock, FiLogIn, FiUserPlus } from 'react-icons/fi';

export default function LoginPage() {
  const { data, setData, errors, post, processing } = useForm({
    email: 'johndoe@example.com',
    password: 'secret',
    remember: true
  });

  function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    post(route('login.store'));
  }

  return (
    <div className="min-h-screen flex items-center justify-center px-4" style={{
      background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
    }}>
      <Head title="MSC Wound Portal - Login" />

      {/* Login Card */}
      <div className="w-full max-w-md">
        <div className="bg-white rounded-2xl shadow-2xl overflow-hidden">
          {/* Header with Logo */}
          <div className="px-8 pt-8 pb-6 text-center bg-gradient-to-br from-white to-gray-50">
            <div className="flex justify-center mb-4">
              <Logo className="h-16 w-auto" />
            </div>
            <h1 className="text-2xl font-bold text-gray-900">
              MSC Wound Portal
            </h1>
            <p className="text-sm text-gray-600 mt-1">
              Streamlined wound care management
            </p>
          </div>

          {/* Login Form */}
          <div className="px-8 pb-8">
            <form onSubmit={handleSubmit} className="space-y-5">
              {/* Email Field */}
              <div>
                <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-2">
                  Email Address
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <FiMail className="h-5 w-5 text-gray-400" />
                  </div>
                  <input
                    id="email"
                    type="email"
                    className={`w-full pl-10 pr-4 py-3 border rounded-lg focus:outline-none focus:ring-2 transition-colors ${
                      errors.email
                        ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                        : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
                    }`}
                    style={{
                      '--tw-ring-color': errors.email ? '#ef4444' : '#1822cf'
                    } as React.CSSProperties}
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    placeholder="Enter your email"
                    disabled={processing}
                    required
                  />
                </div>
                {errors.email && (
                  <p className="mt-2 text-sm text-red-600 flex items-center">
                    <span className="mr-1">⚠️</span>
                    {errors.email}
                  </p>
                )}
              </div>

              {/* Password Field */}
              <div>
                <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-2">
                  Password
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <FiLock className="h-5 w-5 text-gray-400" />
                  </div>
                  <input
                    id="password"
                    type="password"
                    className={`w-full pl-10 pr-4 py-3 border rounded-lg focus:outline-none focus:ring-2 transition-colors ${
                      errors.password
                        ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
                        : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
                    }`}
                    style={{
                      '--tw-ring-color': errors.password ? '#ef4444' : '#1822cf'
                    } as React.CSSProperties}
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    placeholder="Enter your password"
                    disabled={processing}
                    required
                  />
                </div>
                {errors.password && (
                  <p className="mt-2 text-sm text-red-600 flex items-center">
                    <span className="mr-1">⚠️</span>
                    {errors.password}
                  </p>
                )}
              </div>

              {/* Remember Me & Forgot Password */}
              <div className="flex items-center justify-between">
                <div className="flex items-center">
                  <input
                    id="remember"
                    type="checkbox"
                    checked={data.remember}
                    onChange={(e) => setData('remember', e.target.checked)}
                    disabled={processing}
                    className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    style={{ color: '#1822cf' }}
                  />
                  <label htmlFor="remember" className="ml-2 text-sm text-gray-600">
                    Remember me
                  </label>
                </div>

                <a
                  href="#reset-password"
                  className="text-sm font-medium hover:underline transition-colors"
                  style={{ color: '#1822cf' }}
                  tabIndex={-1}
                >
                  Forgot password?
                </a>
              </div>

              {/* Sign In Button */}
              <button
                type="submit"
                disabled={processing}
                className="w-full py-3 px-4 rounded-lg text-white font-semibold text-sm transition-all duration-200 disabled:opacity-70 disabled:cursor-not-allowed flex justify-center items-center focus:outline-none focus:ring-2 focus:ring-offset-2"
                style={{
                  backgroundColor: '#1822cf',
                  '--tw-ring-color': '#1822cf'
                } as React.CSSProperties}
                onMouseEnter={(e) => {
                  if (!processing) {
                    e.currentTarget.style.backgroundColor = '#1219b8';
                  }
                }}
                onMouseLeave={(e) => {
                  if (!processing) {
                    e.currentTarget.style.backgroundColor = '#1822cf';
                  }
                }}
              >
                {processing ? (
                  <>
                    <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Signing In...
                  </>
                ) : (
                  <>
                    <FiLogIn className="mr-2 h-5 w-5" />
                    Sign In
                  </>
                )}
              </button>
            </form>

            {/* Divider */}
            <div className="mt-8 mb-6">
              <div className="relative">
                <div className="absolute inset-0 flex items-center">
                  <div className="w-full border-t border-gray-300" />
                </div>
                <div className="relative flex justify-center text-sm">
                  <span className="px-2 bg-white text-gray-500">Need access?</span>
                </div>
              </div>
            </div>

            {/* Request Access Button */}
            <button
              type="button"
              className="w-full py-3 px-4 rounded-lg font-semibold text-sm transition-all duration-200 flex justify-center items-center border-2 focus:outline-none focus:ring-2 focus:ring-offset-2"
              style={{
                borderColor: '#1822cf',
                color: '#1822cf',
                '--tw-ring-color': '#1822cf'
              } as React.CSSProperties}
              onMouseEnter={(e) => {
                e.currentTarget.style.backgroundColor = '#1822cf';
                e.currentTarget.style.color = 'white';
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.backgroundColor = 'transparent';
                e.currentTarget.style.color = '#1822cf';
              }}
              onClick={() => {
                window.location.href = route('access-requests.create');
              }}
            >
              <FiUserPlus className="mr-2 h-5 w-5" />
              Request Access
            </button>

            {/* Footer */}
            <div className="mt-6 text-center">
              <p className="text-xs text-gray-500">
                Medical Supply Company © 2024
              </p>
              <p className="text-xs text-gray-400 mt-1">
                Secure healthcare management platform
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
