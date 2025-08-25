<?php

namespace App\Mail;

use App\Models\Users\Provider\ProviderInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProviderInvitationEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public ProviderInvitation $invitation
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invitation to Join MSC Wound Care Portal',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.provider-invitation',
            with: [
                'invitationUrl' => route('auth.provider-invitation.show', [
                    'token' => $this->invitation->invitation_token
                ]),
                'inviterName' => $this->invitation->metadata['invited_by_name'] ?? 'MSC Team',
                'expiresAt' => $this->invitation->expires_at->format('F j, Y'),
            ],
        );
    }
}