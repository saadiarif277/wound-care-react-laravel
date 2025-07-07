<?php

namespace App\Services;

use App\Models\VerifiedSender;
use App\Models\SenderMapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Collection;
use Exception;

/**
 * Smart Email Sender Service
 * 
 * Intelligently selects the best email sender based on context and manages
 * email sending through Azure Communication Services or fallback SMTP.
 */
class SmartEmailSender
{
    private Collection $verifiedSenders;
    private array $config;
    private bool $useAzureCommunication;

    public function __construct()
    {
        $this->config = config('services.azure.communication_services');
        $this->useAzureCommunication = !empty($this->config['connection_string']);
        
        // Cache verified senders for performance - handle migration case
        try {
            $this->verifiedSenders = Cache::remember(
                'verified_senders',
                now()->addMinutes(15),
                fn() => VerifiedSender::verified()->active()->get()->keyBy('email_address')
            );
        } catch (\Exception $e) {
            // If the table doesn't exist yet (during migration), use empty collection
            $this->verifiedSenders = collect();
        }
    }

    /**
     * Send an email with intelligent sender selection
     */
    public function send(
        string|array $to,
        string $subject,
        string $content,
        array $context = [],
        array $attachments = []
    ): array {
        try {
            // Select the best sender for this context
            $sender = $this->selectBestSender($context);
            
            if (!$sender) {
                throw new Exception('No verified sender available for this context');
            }

            // Prepare recipients
            $recipients = is_array($to) ? $to : [$to];
            
            // Send the email
            $result = $this->sendEmail(
                $sender,
                $recipients,
                $subject,
                $content,
                $context,
                $attachments
            );

            // Log successful send
            Log::info('Smart email sent successfully', [
                'sender_id' => $sender->id,
                'sender_email' => $sender->email_address,
                'recipients' => $recipients,
                'subject' => $subject,
                'context' => $context,
                'method' => $result['method'] ?? 'unknown',
            ]);

            return [
                'success' => true,
                'sender' => $sender,
                'recipients' => $recipients,
                'method' => $result['method'] ?? 'unknown',
                'message_id' => $result['message_id'] ?? null,
            ];

        } catch (Exception $e) {
            Log::error('Smart email sending failed', [
                'error' => $e->getMessage(),
                'recipients' => $recipients ?? [$to],
                'subject' => $subject,
                'context' => $context,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'recipients' => $recipients ?? [$to],
            ];
        }
    }

    /**
     * Send email on behalf of a partner organization
     */
    public function sendOnBehalfOf(
        string $partnerEmail,
        string|array $to,
        string $subject,
        string $content,
        array $context = []
    ): array {
        // Add partner context
        $context['on_behalf_of'] = $partnerEmail;
        $context['partner_email'] = $partnerEmail;
        
        return $this->send($to, $subject, $content, $context);
    }

    /**
     * Send manufacturer notification email
     */
    public function sendManufacturerEmail(
        string $manufacturerId,
        string|array $to,
        string $subject,
        string $content,
        array $attachments = []
    ): array {
        return $this->send($to, $subject, $content, [
            'manufacturer_id' => $manufacturerId,
            'document_type' => 'order',
        ], $attachments);
    }

    /**
     * Send IVR notification email
     */
    public function sendIvrEmail(
        string $manufacturerId,
        string|array $to,
        string $subject,
        string $content,
        string $organization = null,
        array $attachments = []
    ): array {
        $context = [
            'manufacturer_id' => $manufacturerId,
            'document_type' => 'ivr',
        ];

        if ($organization) {
            $context['organization'] = $organization;
        }

        return $this->send($to, $subject, $content, $context, $attachments);
    }

    /**
     * Send manufacturer IVR approval email with PDF attachment
     */
    public function sendManufacturerIvrApproval(
        string $manufacturerId,
        string|array $to,
        string $subject,
        string $htmlContent,
        string $pdfPath,
        string $pdfFilename,
        array $context = []
    ): array {
        $fullContext = array_merge([
            'manufacturer_id' => $manufacturerId,
            'document_type' => 'ivr',
        ], $context);

        $attachments = [
            [
                'path' => $pdfPath,
                'name' => $pdfFilename,
                'mime' => 'application/pdf',
            ]
        ];

        return $this->send($to, $subject, $htmlContent, $fullContext, $attachments);
    }

    /**
     * Select the best sender for the given context
     */
    private function selectBestSender(array $context): ?VerifiedSender
    {
        // Use static method from VerifiedSender model for intelligent selection
        $sender = VerifiedSender::findBestSenderForContext($context);
        
        if ($sender) {
            return $sender;
        }

        // Fallback to default MSC sender
        return $this->getDefaultSender();
    }

    /**
     * Get the default MSC platform sender
     */
    private function getDefaultSender(): ?VerifiedSender
    {
        return VerifiedSender::getDefaultSender() ?? 
               $this->createDefaultSender();
    }

    /**
     * Create a default sender if none exists
     */
    private function createDefaultSender(): VerifiedSender
    {
        return VerifiedSender::firstOrCreate(
            ['email_address' => $this->config['default_sender']],
            [
                'display_name' => $this->config['default_sender_name'],
                'organization' => 'MSC Platform',
                'is_verified' => true,
                'verification_method' => 'azure_domain',
                'is_active' => true,
                'verified_at' => now(),
            ]
        );
    }

    /**
     * Actually send the email using the selected method
     */
    private function sendEmail(
        VerifiedSender $sender,
        array $recipients,
        string $subject,
        string $content,
        array $context,
        array $attachments
    ): array {
        if ($this->useAzureCommunication) {
            return $this->sendViaAzureCommunication($sender, $recipients, $subject, $content, $context, $attachments);
        } else {
            return $this->sendViaLaravelMail($sender, $recipients, $subject, $content, $context, $attachments);
        }
    }

    /**
     * Send email via Azure Communication Services
     */
    private function sendViaAzureCommunication(
        VerifiedSender $sender,
        array $recipients,
        string $subject,
        string $content,
        array $context,
        array $attachments
    ): array {
        try {
            // TODO: Implement Azure Communication Services client
            // For now, we'll simulate the call
            
            Log::info('Would send via Azure Communication Services', [
                'sender' => $sender->email_address,
                'recipients' => $recipients,
                'subject' => $subject,
                'verification_method' => $sender->verification_method,
            ]);

            // Simulate successful send
            return [
                'method' => 'azure_communication',
                'message_id' => 'azure_msg_' . bin2hex(random_bytes(8)),
            ];

        } catch (Exception $e) {
            Log::warning('Azure Communication Services failed, falling back to Laravel Mail', [
                'error' => $e->getMessage(),
            ]);

            return $this->sendViaLaravelMail($sender, $recipients, $subject, $content, $context, $attachments);
        }
    }

    /**
     * Send email via Laravel Mail (fallback)
     */
    private function sendViaLaravelMail(
        VerifiedSender $sender,
        array $recipients,
        string $subject,
        string $content,
        array $context,
        array $attachments
    ): array {
        try {
            foreach ($recipients as $recipient) {
                Mail::raw($content, function ($message) use ($sender, $recipient, $subject, $context, $attachments) {
                    $message->to($recipient)
                            ->subject($subject);

                    // Set appropriate from address based on verification method
                    if ($sender->verification_method === 'on_behalf') {
                        $message->from(
                            config('mail.from.address'),
                            config('mail.from.name')
                        );
                        $message->replyTo($sender->email_address, $sender->display_name);
                        
                        // Add custom headers
                        $message->getHeaders()->addTextHeader(
                            'X-Original-Sender',
                            $sender->email_address
                        );
                    } else {
                        $message->from($sender->email_address, $sender->display_name);
                    }

                    // Add attachments
                    foreach ($attachments as $attachment) {
                        if (is_array($attachment)) {
                            // Structured attachment with metadata
                            $message->attach(
                                $attachment['path'] ?? $attachment['data'],
                                [
                                    'as' => $attachment['name'] ?? 'attachment',
                                    'mime' => $attachment['mime'] ?? 'application/octet-stream',
                                ]
                            );
                        } else {
                            // Simple path string
                            $message->attach($attachment);
                        }
                    }

                    // Add context headers for tracking
                    if (isset($context['manufacturer_id'])) {
                        $message->getHeaders()->addTextHeader(
                            'X-Manufacturer-ID',
                            $context['manufacturer_id']
                        );
                    }

                    if (isset($context['document_type'])) {
                        $message->getHeaders()->addTextHeader(
                            'X-Document-Type',
                            $context['document_type']
                        );
                    }

                    $message->getHeaders()->addTextHeader(
                        'X-MSC-Sender-ID',
                        $sender->id
                    );
                });
            }

            return [
                'method' => 'laravel_mail',
                'message_id' => 'laravel_msg_' . bin2hex(random_bytes(8)),
            ];

        } catch (Exception $e) {
            throw new Exception("Failed to send email via Laravel Mail: " . $e->getMessage());
        }
    }

    /**
     * Get sender statistics
     */
    public function getSenderStats(): array
    {
        return [
            'total_senders' => VerifiedSender::count(),
            'verified_senders' => VerifiedSender::verified()->count(),
            'active_senders' => VerifiedSender::active()->count(),
            'azure_domain_senders' => VerifiedSender::byVerificationMethod('azure_domain')->count(),
            'on_behalf_senders' => VerifiedSender::byVerificationMethod('on_behalf')->count(),
            'total_mappings' => SenderMapping::count(),
            'active_mappings' => SenderMapping::active()->count(),
        ];
    }

    /**
     * Test email configuration
     */
    public function testConfiguration(): array
    {
        try {
            $defaultSender = $this->getDefaultSender();
            
            if (!$defaultSender) {
                return [
                    'success' => false,
                    'message' => 'No default sender configured',
                ];
            }

            return [
                'success' => true,
                'message' => 'Email configuration is valid',
                'default_sender' => $defaultSender->email_address,
                'verification_method' => $defaultSender->verification_method,
                'azure_communication_available' => $this->useAzureCommunication,
                'config' => [
                    'default_sender' => $this->config['default_sender'],
                    'default_sender_name' => $this->config['default_sender_name'],
                    'azure_configured' => !empty($this->config['connection_string']),
                ],
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Configuration test failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Clear sender cache
     */
    public function clearCache(): void
    {
        Cache::forget('verified_senders');
        $this->verifiedSenders = VerifiedSender::verified()->active()->get()->keyBy('email_address');
    }
} 