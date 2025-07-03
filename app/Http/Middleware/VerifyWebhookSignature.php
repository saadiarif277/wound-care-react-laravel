<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Logging\PhiSafeLogger;

class VerifyWebhookSignature
{
    public function __construct(
        protected PhiSafeLogger $logger
    ) {}

    /**
     * Handle an incoming webhook request.
     */
    public function handle(Request $request, Closure $next, string $provider = 'docuseal'): Response
    {
        $verificationMethod = match($provider) {
            'docuseal' => 'verifyDocusealSignature',
            'stripe' => 'verifyStripeSignature',
            'aws' => 'verifyAwsSignature',
            default => throw new \InvalidArgumentException("Unknown webhook provider: {$provider}")
        };

        if (!$this->$verificationMethod($request)) {
            $this->logger->warning('Invalid webhook signature', [
                'provider' => $provider,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        return $next($request);
    }

    /**
     * Verify Docuseal webhook signature
     */
    protected function verifyDocusealSignature(Request $request): bool
    {
        $signature = $request->header('X-Docuseal-Signature');
        
        if (!$signature) {
            return false;
        }

        $secret = config('services.docuseal.webhook_secret');
        
        if (!$secret) {
            $this->logger->error('Docuseal webhook secret not configured');
            return false;
        }

        $payload = $request->getContent();
        $timestamp = $request->header('X-Docuseal-Timestamp');
        
        // Prevent replay attacks
        if ($timestamp && abs(time() - intval($timestamp)) > 300) {
            $this->logger->warning('Docuseal webhook timestamp too old', [
                'timestamp' => $timestamp,
                'current_time' => time()
            ]);
            return false;
        }

        $expectedSignature = hash_hmac(
            'sha256',
            $timestamp . '.' . $payload,
            $secret
        );

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Verify Stripe webhook signature
     */
    protected function verifyStripeSignature(Request $request): bool
    {
        $signature = $request->header('Stripe-Signature');
        
        if (!$signature) {
            return false;
        }

        $secret = config('services.stripe.webhook_secret');
        
        if (!$secret) {
            $this->logger->error('Stripe webhook secret not configured');
            return false;
        }

        $payload = $request->getContent();
        
        // Parse the signature header
        $elements = explode(',', $signature);
        $timestamp = null;
        $signatures = [];
        
        foreach ($elements as $element) {
            $parts = explode('=', $element, 2);
            if ($parts[0] === 't') {
                $timestamp = $parts[1];
            } elseif ($parts[0] === 'v1') {
                $signatures[] = $parts[1];
            }
        }

        // Prevent replay attacks
        if (!$timestamp || abs(time() - intval($timestamp)) > 300) {
            return false;
        }

        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expectedSignature, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verify AWS SNS webhook signature
     */
    protected function verifyAwsSignature(Request $request): bool
    {
        $messageType = $request->header('x-amz-sns-message-type');
        
        if (!$messageType) {
            return false;
        }

        $message = json_decode($request->getContent(), true);
        
        if (!$message) {
            return false;
        }

        // Handle subscription confirmation
        if ($messageType === 'SubscriptionConfirmation') {
            $this->handleSnsSubscriptionConfirmation($message);
            return true;
        }

        // Verify signature
        $signatureVersion = $message['SignatureVersion'] ?? '';
        
        if ($signatureVersion !== '1') {
            return false;
        }

        $signature = $message['Signature'] ?? '';
        $signingCertUrl = $message['SigningCertURL'] ?? '';
        
        // Verify the certificate URL is from AWS
        if (!$this->isValidAwsCertUrl($signingCertUrl)) {
            return false;
        }

        // Build the string to sign
        $stringToSign = $this->buildAwsStringToSign($message, $messageType);
        
        // Get the certificate
        $certificate = $this->getAwsCertificate($signingCertUrl);
        
        if (!$certificate) {
            return false;
        }

        // Verify the signature
        $publicKey = openssl_get_publickey($certificate);
        $verified = openssl_verify(
            $stringToSign,
            base64_decode($signature),
            $publicKey,
            OPENSSL_ALGO_SHA1
        );

        openssl_free_key($publicKey);

        return $verified === 1;
    }

    /**
     * Handle SNS subscription confirmation
     */
    protected function handleSnsSubscriptionConfirmation(array $message): void
    {
        $subscribeUrl = $message['SubscribeURL'] ?? '';
        
        if ($subscribeUrl) {
            // Confirm the subscription
            file_get_contents($subscribeUrl);
            
            $this->logger->info('SNS subscription confirmed', [
                'topic_arn' => $message['TopicArn'] ?? 'unknown'
            ]);
        }
    }

    /**
     * Validate AWS certificate URL
     */
    protected function isValidAwsCertUrl(string $url): bool
    {
        $parsed = parse_url($url);
        
        return $parsed['scheme'] === 'https' &&
               str_ends_with($parsed['host'], '.amazonaws.com') &&
               str_starts_with($parsed['path'], '/SimpleNotificationService-');
    }

    /**
     * Build AWS string to sign
     */
    protected function buildAwsStringToSign(array $message, string $messageType): string
    {
        $fields = match($messageType) {
            'Notification' => ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'],
            'SubscriptionConfirmation', 'UnsubscribeConfirmation' => 
                ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'],
            default => []
        };

        $stringToSign = '';
        
        foreach ($fields as $field) {
            if (isset($message[$field])) {
                $stringToSign .= $field . "\n" . $message[$field] . "\n";
            }
        }

        return rtrim($stringToSign);
    }

    /**
     * Get AWS certificate
     */
    protected function getAwsCertificate(string $url): ?string
    {
        $cacheKey = 'aws_cert_' . md5($url);
        
        return cache()->remember($cacheKey, 3600, function () use ($url) {
            $context = stream_context_create([
                'http' => ['timeout' => 5]
            ]);
            
            return @file_get_contents($url, false, $context) ?: null;
        });
    }
}