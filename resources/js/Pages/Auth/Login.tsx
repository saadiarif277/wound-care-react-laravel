import React from 'react';
import { Head } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';

export default function LoginPage() {
  const { data, setData, errors, post, processing } = useForm({
    email: 'johndoe@example.com',
    password: 'secret',
    remember: true
  });

  const [activeTab, setActiveTab] = React.useState('signin');

  function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    post(route('login.store'));
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-tr from-indigo-200 via-purple-200 to-pink-200 px-4">
      <Head title="Login" />

      <div className="w-full max-w-md bg-white rounded-2xl shadow-2xl p-8 space-y-6 transition-all duration-300 ease-in-out">
        <div className="text-center">
          <h1 className="text-4xl font-extrabold text-indigo-700">DistroExchange</h1>
          <p className="text-sm text-gray-500 mt-1">Streamlined product distribution</p>
        </div>

        {/* Custom Tabs Implementation */}
        <div className="w-full">
          <div className="grid grid-cols-2 bg-indigo-100 rounded-lg overflow-hidden">
            <button
              onClick={() => setActiveTab('signin')}
              className={`py-2 font-medium text-indigo-600 transition-colors duration-300 ${
                activeTab === 'signin' ? 'bg-white shadow' : 'hover:bg-indigo-200'
              }`}
            >
              Sign In
            </button>
            <button
              onClick={() => setActiveTab('signup')}
              className={`py-2 font-medium text-indigo-600 transition-colors duration-300 ${
                activeTab === 'signup' ? 'bg-white shadow' : 'hover:bg-indigo-200'
              }`}
              disabled
            >
              Sign Up
            </button>
          </div>

          {/* Sign In Tab Content */}
          {activeTab === 'signin' && (
            <div className="mt-4">
              <form onSubmit={handleSubmit} className="space-y-4">
                <div>
                  <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">
                    Email
                  </label>
                  <input
                    id="email"
                    type="email"
                    className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-400 ${
                      errors.email ? 'border-red-500' : 'border-gray-300'
                    }`}
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    placeholder="you@example.com"
                    disabled={processing}
                    required
                  />
                  {errors.email && (
                    <p className="mt-1 text-sm text-red-600">{errors.email}</p>
                  )}
                </div>

                <div>
                  <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-1">
                    Password
                  </label>
                  <input
                    id="password"
                    type="password"
                    className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-400 ${
                      errors.password ? 'border-red-500' : 'border-gray-300'
                    }`}
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    placeholder="••••••••"
                    disabled={processing}
                    required
                  />
                  {errors.password && (
                    <p className="mt-1 text-sm text-red-600">{errors.password}</p>
                  )}
                </div>

                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <input
                      id="remember"
                      type="checkbox"
                      checked={data.remember}
                      onChange={(e) => setData('remember', e.target.checked)}
                      disabled={processing}
                      className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                    />
                    <label htmlFor="remember" className="text-sm text-gray-600">
                      Remember me
                    </label>
                  </div>

                  <a
                    href="#reset-password"
                    className="text-sm text-indigo-600 hover:underline"
                    tabIndex={-1}
                  >
                    Forgot password?
                  </a>
                </div>

                <button
                  type="submit"
                  disabled={processing}
                  className="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-75 flex justify-center items-center"
                >
                  {processing ? (
                    <>
                      <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      Signing In...
                    </>
                  ) : (
                    "Sign In"
                  )}
                </button>
              </form>
            </div>
          )}

          {/* Sign Up Tab Content */}
          {activeTab === 'signup' && (
            <div className="mt-4">
              <div className="text-center py-8 text-gray-500">
                Sign up functionality is currently disabled
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
