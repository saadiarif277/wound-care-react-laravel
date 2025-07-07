<?php

namespace App\Services;

// Azure Communications SDK would be used here if available
// For now, we'll use HTTP API calls directly
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class AzureCommunicationsService
{
    protected array $config;
    protected bool $isConfigured = false;

    public function __construct()
    {
        $this->config = config('services.azure.communication_services', []);
        
        // Check if Azure Communications is configured
        $this->isConfigured = !empty($this->config['connection_string']) || !empty($this->config['endpoint']);
    }

    /**
     * Send an email using Azure Communication Services
     */
    public function sendEmail(
        array $recipients,
        string $subject,
        string $htmlContent,
        ?string $plainTextContent = null,
        array $attachments = [],
        array $options = []
    ): array {
        // For now, always use Laravel Mail as Azure SDK is not available
        // This can be updated when Azure Communications SDK for PHP is available
        return $this->fallbackEmailSend($recipients, $subject, $htmlContent, $attachments, $options);
    }

    /**
     * Send an SMS using Azure Communication Services
     */
    public function sendSms(
        string $to,
        string $message,
        array $options = []
    ): array {
        // SMS functionality would be implemented here when Azure SDK is available
        // For now, log the intention
        Log::info('SMS notification requested', [
            'to' => $to,
            'message' => substr($message, 0, 50) . '...',
        ]);

        return [
            'success' => true,
            'provider' => 'logged_only',
            'message' => 'SMS logged (service not configured)',
        ];
    }

    /**
     * Send notification using template
     */
    public function sendNotificationFromTemplate(
        string $templateName,
        array $recipients,
        array $variables,
        array $options = []
    ): array {
        // Load template from database or config
        $template = $this->loadNotificationTemplate($templateName);
        
        if (!$template) {
            Log::error('Notification template not found', ['template' => $templateName]);
            return [
                'success' => false,
                'error' => 'Template not found',
            ];
        }

        // Replace variables in template
        $subject = $this->replaceVariables($template['subject'], $variables);
        $htmlContent = $this->replaceVariables($template['html_content'], $variables);
        $plainTextContent = isset($template['plain_content']) 
            ? $this->replaceVariables($template['plain_content'], $variables) 
            : null;

        // Send email
        return $this->sendEmail(
            $recipients,
            $subject,
            $htmlContent,
            $plainTextContent,
            $options['attachments'] ?? [],
            $options
        );
    }

    /**
     * Send manufacturer notification with PDF attachment
     */
    public function sendManufacturerNotification(
        string $manufacturerName,
        array $recipients,
        array $orderData,
        string $pdfPath
    ): array {
        // Get manufacturer-specific template or use default
        $templateName = "manufacturer_order_{$manufacturerName}";
        $template = $this->loadNotificationTemplate($templateName);
        
        if (!$template) {
            $templateName = 'manufacturer_order_default';
            $template = $this->loadNotificationTemplate($templateName);
        }

        if (!$template) {
            // Use hardcoded default template
            $template = [
                'subject' => 'New Order Request - {{order_number}} - {{patient_name}}',
                'html_content' => $this->getDefaultManufacturerEmailTemplate(),
            ];
        }

        // Prepare variables
        $variables = [
            'order_number' => $orderData['order_number'],
            'patient_name' => $orderData['patient_name'],
            'provider_name' => $orderData['provider_name'],
            'facility_name' => $orderData['facility_name'],
            'product_name' => $orderData['product_name'],
            'product_code' => $orderData['product_code'],
            'quantity' => $orderData['quantity'],
            'expected_service_date' => $orderData['expected_service_date'],
            'manufacturer_name' => $manufacturerName,
        ];

        // Send notification with PDF attachment
        return $this->sendNotificationFromTemplate(
            $templateName,
            $recipients,
            $variables,
            [
                'attachments' => [
                    [
                        'path' => $pdfPath,
                        'name' => "IVR-{$orderData['order_number']}.pdf",
                    ]
                ],
            ]
        );
    }

    /**
     * Send order status update SMS
     */
    public function sendOrderStatusSms(
        string $phoneNumber,
        string $orderNumber,
        string $status,
        array $additionalInfo = []
    ): array {
        $messages = [
            'submitted' => "Your order #{$orderNumber} has been submitted to the manufacturer.",
            'approved' => "Good news! Your order #{$orderNumber} has been approved.",
            'shipped' => "Your order #{$orderNumber} has shipped! Tracking: {$additionalInfo['tracking_number']}",
            'delivered' => "Your order #{$orderNumber} has been delivered.",
            'cancelled' => "Your order #{$orderNumber} has been cancelled. Please contact support for details.",
        ];

        $message = $messages[$status] ?? "Update on order #{$orderNumber}: {$status}";
        
        // Replace any additional variables
        foreach ($additionalInfo as $key => $value) {
            $message = str_replace("{{$key}}", $value, $message);
        }

        return $this->sendSms($phoneNumber, $message);
    }

    /**
     * Load notification template from database or config
     */
    protected function loadNotificationTemplate(string $templateName): ?array
    {
        // Check cache first
        $cacheKey = "notification_template:{$templateName}";
        $cached = Cache::get($cacheKey);
        
        if ($cached) {
            return $cached;
        }

        // TODO: Load from database table notification_templates
        // For now, return null to use defaults
        return null;
    }

    /**
     * Replace variables in template content
     */
    protected function replaceVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace("{{" . $key . "}}", $value, $content);
        }
        
        return $content;
    }

    /**
     * Format phone number for SMS
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add country code if not present
        if (strlen($phone) === 10) {
            $phone = '1' . $phone; // US/Canada
        }
        
        // Add + prefix
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }
        
        return $phone;
    }

    /**
     * Fallback email send using Laravel Mail
     */
    protected function fallbackEmailSend(
        array $recipients,
        string $subject,
        string $htmlContent,
        array $attachments,
        array $options = []
    ): array {
        try {
            // Use Laravel's mail system as fallback
            \Mail::html($htmlContent, function ($message) use ($recipients, $subject, $attachments) {
                foreach ($recipients as $recipient) {
                    $email = is_string($recipient) ? $recipient : $recipient['email'];
                    $name = is_array($recipient) ? ($recipient['name'] ?? null) : null;
                    $message->to($email, $name);
                }
                
                $message->subject($subject);
                
                foreach ($attachments as $attachment) {
                    $message->attach($attachment['path'], [
                        'as' => $attachment['name'] ?? basename($attachment['path']),
                    ]);
                }
            });

            Log::info('Email sent via Laravel Mail fallback', [
                'recipients' => $recipients,
                'subject' => $subject,
            ]);

            return [
                'success' => true,
                'provider' => 'laravel_mail',
            ];

        } catch (Exception $e) {
            Log::error('Failed to send email via fallback', [
                'error' => $e->getMessage(),
                'recipients' => $recipients,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get default manufacturer email template
     */
    protected function getDefaultManufacturerEmailTemplate(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #f8f9fa; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background-color: #ffffff; padding: 20px; border: 1px solid #dee2e6; }
        .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #6c757d; border-radius: 0 0 8px 8px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background-color: #f8f9fa; font-weight: bold; }
        .highlight { background-color: #e3f2fd; padding: 15px; border-radius: 5px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>New Order Request - {{order_number}}</h2>
            <p>Patient: {{patient_name}}</p>
        </div>
        
        <div class="content">
            <div class="highlight">
                <strong>Please find the attached IVR form for this order.</strong>
            </div>
            
            <h3>Order Details</h3>
            <table>
                <tr>
                    <th>Field</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td>Order Number</td>
                    <td>{{order_number}}</td>
                </tr>
                <tr>
                    <td>Patient Name</td>
                    <td>{{patient_name}}</td>
                </tr>
                <tr>
                    <td>Provider</td>
                    <td>{{provider_name}}</td>
                </tr>
                <tr>
                    <td>Facility</td>
                    <td>{{facility_name}}</td>
                </tr>
                <tr>
                    <td>Product</td>
                    <td>{{product_name}} ({{product_code}})</td>
                </tr>
                <tr>
                    <td>Quantity</td>
                    <td>{{quantity}}</td>
                </tr>
                <tr>
                    <td>Expected Service Date</td>
                    <td>{{expected_service_date}}</td>
                </tr>
            </table>
            
            <p>Please review the attached IVR form and process this order at your earliest convenience.</p>
            
            <p>If you have any questions, please contact our support team.</p>
        </div>
        
        <div class="footer">
            <p>This is an automated message from MSC Wound Care Platform</p>
            <p>&copy; 2024 MSC Wound Care. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}