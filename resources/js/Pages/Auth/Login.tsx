import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import Logo from '@/Components/Logo/Logo';
import { FiMail, FiLock, FiLogIn } from 'react-icons/fi';

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
      <Head title="MSC Woundcare | Login" />

      {/* Dark Overlay for readability */}
      <div className="absolute inset-0 bg-black bg-opacity-50"></div>

      {/* Login Content */}
      <div className="relative z-10 min-h-screen flex items-center justify-center px-4">
        <div className="w-full max-w-md">
          <div className="bg-white/95 backdrop-blur-sm rounded-3xl shadow-2xl border border-white/20 overflow-hidden">
            {/* Header with Logo */}
            <div className="px-8 pt-8 pb-6 text-center">
              <div className="flex justify-center mb-4">
                <Logo className="h-20 w-auto" />
              </div>
              <p className="text-lg font-semibold text-gray-800 tracking-wide">
                HEALING DESERVES INNOVATION.
              </p>
            </div>

            {/* Login Form */}
            <div className="px-8 pb-8">
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
                  <label htmlFor="password" className="block text-sm font-semibold text-gray-700 mb-2">
                    Password
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
                      style={{ color: '#1925c3' }}
                    />
                    <label htmlFor="remember" className="ml-2 text-sm text-gray-600">
                      Remember me
                    </label>
                  </div>

                  <Link
                    href={route('password.request')}
                    className="text-sm font-medium hover:underline transition-colors"
                    style={{ color: '#1925c3' }}
                  >
                    Forgot password?
                  </Link>
                </div>

                {/* Sign In Button */}
                <button
                  type="submit"
                  disabled={processing}
                  className="w-full py-4 px-4 rounded-xl text-white font-semibold text-base transition-all duration-200 disabled:opacity-70 disabled:cursor-not-allowed flex justify-center items-center focus:outline-none focus:ring-2 focus:ring-offset-2 shadow-lg transform hover:scale-105"
                  style={{
                    backgroundColor: '#1925c3',
                    '--tw-ring-color': '#1925c3'
                  } as React.CSSProperties}
                  onMouseEnter={(e) => {
                    if (!processing) {
                      e.currentTarget.style.backgroundColor = '#141c9a';
                    }
                  }}
                  onMouseLeave={(e) => {
                    if (!processing) {
                      e.currentTarget.style.backgroundColor = '#1925c3';
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

              {/* Footer */}
              <div className="mt-8 text-center">
                <p className="text-xs text-gray-500 font-medium">
                  MSC Wound Care © 2025
                </p>
                <p className="text-xs text-gray-400 mt-1">
                  Secure healthcare management platform
                </p>
                <div className="mt-3">
                  <a 
                    href="#" 
                    onClick={(e) => {
                      e.preventDefault();
                      const supportEmail = import.meta.env.VITE_MAIL_FROM_ADDRESS || 'support@mscwoundcare.com';
                      const currentDate = new Date().toISOString().split('T')[0];
                      const subject = encodeURIComponent(`Support Request: ${currentDate} - Login Page`);
                      const body = encodeURIComponent('Hi MSC Support Team,\n\nI need assistance with the wound care portal.\n\nDetails:\n- Page: Login\n- Issue: \n\nThank you for your help.');
                      window.location.href = `mailto:${supportEmail}?subject=${subject}&body=${body}`;
                    }}
                    className="text-xs hover:underline transition-colors"
                    style={{ color: '#1925c3' }}
                  >
                    Contact Support
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
