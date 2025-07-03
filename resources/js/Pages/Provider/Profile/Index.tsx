import React, { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { useTheme } from '@/contexts/ThemeContext';
import { themes, cn } from '@/theme/glass-theme';
import {
  User,
  Mail,
  Phone,
  MapPin,
  CreditCard,
  Calendar,
  Shield,
  Award,
  Briefcase,
  Globe,
  CheckCircle,
  Clock,
  AlertCircle,
  Camera,
  Languages,
  FileText,
  Key,
  Building2,
} from 'lucide-react';

interface Credential {
  id: number;
  credential_type: string;
  credential_number: string;
  issuing_state?: string;
  issue_date: string;
  expiry_date: string;
  verification_status: 'pending' | 'verified' | 'expired' | 'failed';
  verified_at?: string;
  verified_by?: {
    name: string;
  };
}

interface ProviderProfile {
  id: number;
  provider_id: number;
  professional_bio?: string;
  specializations?: string[];
  languages_spoken?: string[];
  professional_photo_url?: string;
  verification_status: 'unverified' | 'pending' | 'verified' | 'suspended';
  two_factor_enabled: boolean;
  created_at: string;
  updated_at: string;
  provider: {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    npi_number?: string;
    phone?: string;
    organization?: {
      name: string;
      address?: string;
      city?: string;
      state?: string;
      zip?: string;
    };
  };
  credentials?: Credential[];
  profile_completion_percentage: number;
}

interface Props {
  profile: ProviderProfile;
}

export default function ProviderProfile({ profile }: Props) {
  const { theme } = useTheme();
  const t = themes[theme];

  const getCredentialIcon = (type: string) => {
    switch (type) {
      case 'medical_license':
        return <FileText className="w-5 h-5" />;
      case 'npi_number':
        return <CreditCard className="w-5 h-5" />;
      case 'dea_license':
        return <Shield className="w-5 h-5" />;
      case 'board_certification':
        return <Award className="w-5 h-5" />;
      default:
        return <FileText className="w-5 h-5" />;
    }
  };

  const getCredentialStatusColor = (status: string) => {
    switch (status) {
      case 'verified':
        return theme === 'dark' ? 'text-green-400' : 'text-green-600';
      case 'pending':
        return theme === 'dark' ? 'text-yellow-400' : 'text-yellow-600';
      case 'expired':
        return theme === 'dark' ? 'text-red-400' : 'text-red-600';
      default:
        return theme === 'dark' ? 'text-gray-400' : 'text-gray-600';
    }
  };

  const getVerificationStatusBadge = () => {
    const statusConfig = {
      verified: {
        color: theme === 'dark' ? 'bg-green-500/20 text-green-400 border-green-500/30' : 'bg-green-50 text-green-700 border-green-200',
        icon: <CheckCircle className="w-4 h-4" />,
        label: 'Verified'
      },
      pending: {
        color: theme === 'dark' ? 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30' : 'bg-yellow-50 text-yellow-700 border-yellow-200',
        icon: <Clock className="w-4 h-4" />,
        label: 'Pending Verification'
      },
      unverified: {
        color: theme === 'dark' ? 'bg-gray-500/20 text-gray-400 border-gray-500/30' : 'bg-gray-50 text-gray-700 border-gray-200',
        icon: <AlertCircle className="w-4 h-4" />,
        label: 'Not Verified'
      },
      suspended: {
        color: theme === 'dark' ? 'bg-red-500/20 text-red-400 border-red-500/30' : 'bg-red-50 text-red-700 border-red-200',
        icon: <AlertCircle className="w-4 h-4" />,
        label: 'Suspended'
      }
    };

    const config = statusConfig[profile.verification_status] || statusConfig.unverified;

    return (
      <span className={cn(
        "inline-flex items-center space-x-1 px-3 py-1 rounded-full text-sm font-medium border",
        config.color
      )}>
        {config.icon}
        <span>{config.label}</span>
      </span>
    );
  };

  return (
    <MainLayout>
      <Head title="My Profile | MSC Healthcare" />

      <div className="max-w-4xl mx-auto space-y-6">
        {/* Header Section */}
        <div className={cn(
          "p-6 rounded-2xl",
          t.glass.card,
          t.glass.border,
          t.shadows.glass
        )}>
          <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6">
            <h1 className={cn("text-2xl font-bold", t.text.primary)}>My Profile</h1>
            {getVerificationStatusBadge()}
          </div>

          <div className="flex flex-col md:flex-row items-start space-y-6 md:space-y-0 md:space-x-6">
            {/* Profile Photo */}
            <div className="flex-shrink-0">
              <div className={cn(
                "w-32 h-32 rounded-2xl overflow-hidden",
                theme === 'dark' ? 'bg-gray-800' : 'bg-gray-200'
              )}>
                {profile.professional_photo_url ? (
                  <img
                    src={profile.professional_photo_url}
                    alt={`${profile.provider.first_name} ${profile.provider.last_name}`}
                    className="w-full h-full object-cover"
                  />
                ) : (
                  <div className="w-full h-full flex items-center justify-center">
                    <User className={cn("w-16 h-16", t.text.muted)} />
                  </div>
                )}
              </div>
            </div>

            {/* Basic Information */}
            <div className="flex-1 space-y-4">
              <div>
                <h2 className={cn("text-xl font-semibold", t.text.primary)}>
                  {profile.provider.first_name} {profile.provider.last_name}
                </h2>
                <p className={cn("text-sm", t.text.secondary)}>Healthcare Provider</p>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="flex items-center space-x-2">
                  <Mail className={cn("w-4 h-4", t.text.muted)} />
                  <span className={cn("text-sm", t.text.secondary)}>{profile.provider.email}</span>
                </div>
                
                {profile.provider.phone && (
                  <div className="flex items-center space-x-2">
                    <Phone className={cn("w-4 h-4", t.text.muted)} />
                    <span className={cn("text-sm", t.text.secondary)}>{profile.provider.phone}</span>
                  </div>
                )}

                {profile.provider.npi_number && (
                  <div className="flex items-center space-x-2">
                    <CreditCard className={cn("w-4 h-4", t.text.muted)} />
                    <span className={cn("text-sm", t.text.secondary)}>NPI: {profile.provider.npi_number}</span>
                  </div>
                )}

                {profile.provider.organization && (
                  <div className="flex items-center space-x-2">
                    <Building2 className={cn("w-4 h-4", t.text.muted)} />
                    <span className={cn("text-sm", t.text.secondary)}>{profile.provider.organization.name}</span>
                  </div>
                )}
              </div>

              {/* Profile Completion */}
              <div className="mt-4">
                <div className="flex items-center justify-between mb-2">
                  <span className={cn("text-sm", t.text.secondary)}>Profile Completion</span>
                  <span className={cn("text-sm font-medium", t.text.primary)}>
                    {profile.profile_completion_percentage}%
                  </span>
                </div>
                <div className={cn(
                  "w-full h-2 rounded-full overflow-hidden",
                  theme === 'dark' ? 'bg-gray-700' : 'bg-gray-200'
                )}>
                  <div
                    className="h-full bg-gradient-to-r from-blue-500 to-purple-500 transition-all duration-300"
                    style={{ width: `${profile.profile_completion_percentage}%` }}
                  />
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Professional Information */}
        <div className={cn(
          "p-6 rounded-2xl",
          t.glass.card,
          t.glass.border,
          t.shadows.glass
        )}>
          <h3 className={cn("text-lg font-semibold mb-4", t.text.primary)}>Professional Information</h3>

          <div className="space-y-4">
            {/* Bio */}
            {profile.professional_bio && (
              <div>
                <h4 className={cn("text-sm font-medium mb-2", t.text.primary)}>Professional Biography</h4>
                <p className={cn("text-sm", t.text.secondary)}>{profile.professional_bio}</p>
              </div>
            )}

            {/* Specializations */}
            {profile.specializations && profile.specializations.length > 0 && (
              <div>
                <h4 className={cn("text-sm font-medium mb-2 flex items-center space-x-2", t.text.primary)}>
                  <Briefcase className="w-4 h-4" />
                  <span>Specializations</span>
                </h4>
                <div className="flex flex-wrap gap-2">
                  {profile.specializations.map((spec, index) => (
                    <span
                      key={index}
                      className={cn(
                        "px-3 py-1 rounded-full text-sm",
                        theme === 'dark'
                          ? 'bg-blue-500/20 text-blue-400 border border-blue-500/30'
                          : 'bg-blue-50 text-blue-700 border border-blue-200'
                      )}
                    >
                      {spec}
                    </span>
                  ))}
                </div>
              </div>
            )}

            {/* Languages */}
            {profile.languages_spoken && profile.languages_spoken.length > 0 && (
              <div>
                <h4 className={cn("text-sm font-medium mb-2 flex items-center space-x-2", t.text.primary)}>
                  <Languages className="w-4 h-4" />
                  <span>Languages Spoken</span>
                </h4>
                <div className="flex flex-wrap gap-2">
                  {profile.languages_spoken.map((lang, index) => (
                    <span
                      key={index}
                      className={cn(
                        "px-3 py-1 rounded-full text-sm",
                        theme === 'dark'
                          ? 'bg-purple-500/20 text-purple-400 border border-purple-500/30'
                          : 'bg-purple-50 text-purple-700 border border-purple-200'
                      )}
                    >
                      {lang}
                    </span>
                  ))}
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Credentials */}
        {profile.credentials && profile.credentials.length > 0 && (
          <div className={cn(
            "p-6 rounded-2xl",
            t.glass.card,
            t.glass.border,
            t.shadows.glass
          )}>
            <h3 className={cn("text-lg font-semibold mb-4", t.text.primary)}>Credentials & Licenses</h3>

            <div className="space-y-3">
              {profile.credentials.map((credential) => (
                <div
                  key={credential.id}
                  className={cn(
                    "p-4 rounded-xl border",
                    theme === 'dark' 
                      ? 'bg-gray-800/50 border-gray-700'
                      : 'bg-gray-50 border-gray-200'
                  )}
                >
                  <div className="flex items-start justify-between">
                    <div className="flex items-start space-x-3">
                      <div className={cn("p-2 rounded-lg", t.glass.base)}>
                        {getCredentialIcon(credential.credential_type)}
                      </div>
                      <div>
                        <h4 className={cn("font-medium", t.text.primary)}>
                          {credential.credential_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                        </h4>
                        <p className={cn("text-sm", t.text.secondary)}>
                          {credential.credential_number}
                          {credential.issuing_state && ` • ${credential.issuing_state}`}
                        </p>
                        <div className="flex items-center space-x-4 mt-2">
                          <span className={cn("text-xs", t.text.muted)}>
                            Issued: {new Date(credential.issue_date).toLocaleDateString()}
                          </span>
                          <span className={cn("text-xs", t.text.muted)}>
                            Expires: {new Date(credential.expiry_date).toLocaleDateString()}
                          </span>
                        </div>
                      </div>
                    </div>
                    <span className={cn(
                      "text-sm font-medium",
                      getCredentialStatusColor(credential.verification_status)
                    )}>
                      {credential.verification_status.charAt(0).toUpperCase() + credential.verification_status.slice(1)}
                    </span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Security Settings */}
        <div className={cn(
          "p-6 rounded-2xl",
          t.glass.card,
          t.glass.border,
          t.shadows.glass
        )}>
          <h3 className={cn("text-lg font-semibold mb-4", t.text.primary)}>Security Settings</h3>

          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-3">
              <Key className={cn("w-5 h-5", t.text.muted)} />
              <div>
                <p className={cn("font-medium", t.text.primary)}>Two-Factor Authentication</p>
                <p className={cn("text-sm", t.text.secondary)}>
                  Add an extra layer of security to your account
                </p>
              </div>
            </div>
            <span className={cn(
              "px-3 py-1 rounded-full text-sm font-medium",
              profile.two_factor_enabled
                ? theme === 'dark' 
                  ? 'bg-green-500/20 text-green-400 border border-green-500/30'
                  : 'bg-green-50 text-green-700 border border-green-200'
                : theme === 'dark'
                  ? 'bg-gray-500/20 text-gray-400 border border-gray-500/30'
                  : 'bg-gray-50 text-gray-700 border border-gray-200'
            )}>
              {profile.two_factor_enabled ? 'Enabled' : 'Disabled'}
            </span>
          </div>
        </div>
      </div>
    </MainLayout>
  );
}