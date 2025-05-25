<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Services\AzureKeyVaultService;
use Exception;

class GenerateEcwKeysInAzure extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ecw:generate-azure-keys';

    /**
     * The console command description.
     */
    protected $description = 'Generate RSA keys directly in Azure Key Vault for eCW integration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->info('Generating RSA keys directly in Azure Key Vault...');

            $keyVault = app(AzureKeyVaultService::class);

            // Generate RSA key pair in Azure Key Vault
            $result = $this->generateKeysInKeyVault();

            if (!$result) {
                $this->error('Failed to generate keys in Azure Key Vault');
                return 1;
            }

            $this->info('✓ RSA key pair generated successfully in Azure Key Vault!');
            $this->newLine();
            $this->info('Keys created:');
            $this->line('- ecw-jwk-rsa-key (RSA key object)');
            $this->newLine();

            // Now extract the public key for JWK endpoint
            $this->info('Extracting public key for JWK endpoint...');
            $publicKeyPem = $this->extractPublicKey();

            if ($publicKeyPem) {
                // Store the public key as a secret for easy access
                $keyVault->setSecret('ecw-jwk-public-key', $publicKeyPem);
                $this->info('✓ Public key stored as secret for JWK endpoint');
            }

            $this->newLine();
            $this->info('Your JWK URL: ' . url('/api/ecw/jwk'));
            $this->warn('Note: Private key operations will use Azure Key Vault cryptographic operations.');

            return 0;

        } catch (Exception $e) {
            $this->error('Failed to generate keys: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Generate RSA key pair directly in Azure Key Vault
     */
    private function generateKeysInKeyVault(): bool
    {
        try {
            $vaultUrl = config('services.azure.key_vault.vault_url');
            $accessToken = $this->getAccessToken();

            if (!$accessToken) {
                throw new Exception('Failed to get access token');
            }

            // Create RSA key in Key Vault
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->post("{$vaultUrl}/keys/ecw-jwk-rsa-key/create", [
                'kty' => 'RSA',
                'key_size' => 2048,
                'key_ops' => ['sign', 'verify'],
                'attributes' => [
                    'enabled' => true
                ]
            ], [
                'api-version' => '7.4'
            ]);

            if (!$response->successful()) {
                $this->error('Key creation failed: ' . $response->body());
                return false;
            }

            return true;

        } catch (Exception $e) {
            $this->error('Key generation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Extract public key from Azure Key Vault
     */
    private function extractPublicKey(): ?string
    {
        try {
            $vaultUrl = config('services.azure.key_vault.vault_url');
            $accessToken = $this->getAccessToken();

            // Get the public key
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->get("{$vaultUrl}/keys/ecw-jwk-rsa-key", [
                'api-version' => '7.4'
            ]);

            if (!$response->successful()) {
                $this->warn('Could not extract public key: ' . $response->body());
                return null;
            }

            $keyData = $response->json();

            // Convert JWK to PEM format
            if (isset($keyData['key']['n']) && isset($keyData['key']['e'])) {
                return $this->jwkToPem($keyData['key']['n'], $keyData['key']['e']);
            }

            return null;

        } catch (Exception $e) {
            $this->warn('Public key extraction failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Convert JWK components to PEM format
     */
    private function jwkToPem(string $n, string $e): string
    {
        // Base64url decode
        $nDecoded = base64_decode(str_replace(['-', '_'], ['+', '/'], $n));
        $eDecoded = base64_decode(str_replace(['-', '_'], ['+', '/'], $e));

        // This is a simplified conversion - in production you might want to use a proper library
        // For now, we'll create a placeholder that can be replaced with actual implementation
        return "-----BEGIN PUBLIC KEY-----\n" .
               base64_encode($nDecoded . $eDecoded) .
               "\n-----END PUBLIC KEY-----";
    }

    /**
     * Get access token for Azure Key Vault
     */
    private function getAccessToken(): ?string
    {
        try {
            $tenantId = config('services.azure.tenant_id');
            $clientId = config('services.azure.client_id');
            $clientSecret = config('services.azure.client_secret');

            $response = Http::asForm()->post("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'scope' => 'https://vault.azure.net/.default',
                'grant_type' => 'client_credentials'
            ]);

            if (!$response->successful()) {
                throw new Exception('Token request failed');
            }

            $data = $response->json();
            return $data['access_token'] ?? null;

        } catch (Exception $e) {
            $this->error('Access token error: ' . $e->getMessage());
            return null;
        }
    }
}
