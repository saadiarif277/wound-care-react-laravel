import React from 'react';
import { Button } from '@/Components/Button';
import { Badge } from '@/Components/ui/badge';
import { UserPlus, Building2, AlertCircle } from 'lucide-react';
import { parseISO, isBefore, differenceInDays, format } from 'date-fns';
import type { ProviderInvitationData } from '@/types/provider';

interface ReviewStepProps {
  invitation: ProviderInvitationData;
  onAccept: () => void;
  onDecline?: () => void;
}

export default function ReviewStep({ 
  invitation, 
  onAccept,
  onDecline 
}: ReviewStepProps) {
  const isExpired = isBefore(parseISO(invitation.expires_at), new Date());
  const daysUntilExpiry = Math.max(0, differenceInDays(parseISO(invitation.expires_at), new Date()));

  return (
    <div className="space-y-6">
      <div className="text-center">
        <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 mb-4">
          <UserPlus className="h-8 w-8 text-blue-600" />
        </div>
        <h1 className="text-2xl font-bold text-gray-900 mb-2">Welcome to MSC Wound Portal!</h1>
        <p className="text-gray-600">
          You've been invited to join <strong>{invitation.organization_name}</strong> as a {invitation.invited_role}
        </p>
      </div>

      {/* Invitation Details */}
      <div className="bg-gray-50 p-6 rounded-lg">
        <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center gap-2">
          <Building2 className="h-5 w-5" />
          Invitation Details
        </h3>
        <dl className="space-y-3">
          <div className="flex justify-between">
            <dt className="text-sm font-medium text-gray-500">Organization:</dt>
            <dd className="text-sm text-gray-900">{invitation.organization_name}</dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-sm font-medium text-gray-500">Your Role:</dt>
            <dd className="text-sm text-gray-900">
              <Badge variant="default">{invitation.invited_role}</Badge>
            </dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-sm font-medium text-gray-500">Invited by:</dt>
            <dd className="text-sm text-gray-900">{invitation.metadata.invited_by_name}</dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-sm font-medium text-gray-500">Email:</dt>
            <dd className="text-sm text-gray-900">{invitation.invited_email}</dd>
          </div>
        </dl>
      </div>

      {/* What to Expect */}
      <div className="bg-blue-50 p-4 rounded-lg">
        <h4 className="text-sm font-medium text-blue-800 mb-2">What to Expect</h4>
        <ul className="text-sm text-blue-700 space-y-1">
          <li>• Complete comprehensive practice onboarding</li>
          <li>• Set up your organization and facility information</li>
          <li>• Provide professional credentials for verification</li>
          <li>• Enable automatic manufacturer form completion</li>
          <li>• Start accessing wound care products and services</li>
        </ul>
      </div>

      {/* Expiration Warning */}
      {isExpired ? (
        <div className="bg-red-50 p-4 rounded-lg">
          <div className="flex items-center gap-2">
            <AlertCircle className="h-5 w-5 text-red-600" />
            <h4 className="text-sm font-medium text-red-800">Invitation Expired</h4>
          </div>
          <p className="text-sm text-red-700 mt-1">
            This invitation expired on {format(parseISO(invitation.expires_at), 'PPP')}.
            Please contact the organization to request a new invitation.
          </p>
        </div>
      ) : (
        <>
          {daysUntilExpiry <= 7 && (
            <div className="bg-yellow-50 p-4 rounded-lg flex items-start gap-3">
              <AlertCircle className="h-5 w-5 text-yellow-600 mt-0.5 flex-shrink-0" />
              <div>
                <h4 className="text-sm font-medium text-yellow-800">Invitation Expires Soon</h4>
                <p className="text-sm text-yellow-700">
                  This invitation expires in {daysUntilExpiry} day{daysUntilExpiry !== 1 ? 's' : ''}
                  ({format(parseISO(invitation.expires_at), 'PPP')})
                </p>
              </div>
            </div>
          )}

          {/* Action Buttons */}
          <div className="flex gap-3 justify-end">
            {onDecline && (
              <Button variant="secondary" onClick={onDecline}>
                Decline
              </Button>
            )}
            <Button onClick={onAccept}>
              Get Started
            </Button>
          </div>
        </>
      )}
    </div>
  );
}