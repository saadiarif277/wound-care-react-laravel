<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * Azure Key Vault Service
 *
 * Handles secure storage and retrieval of secrets from Azure Key Vault
 */
class AzureKeyVaultService
{
    private string $vaultUrl;
    private string $tenantId;
    private string $clientId;
    private string $clientSecret;
    private bool $useManagedIdentity;

    public function __construct()
    {
        $this->vaultUrl = config('services.azure.key_vault.vault_url');
        $this->tenantId = config('services.azure.tenant_id');
        $this->clientId = config('services.azure.client_id');
        $this->clientSecret = config('services.azure.client_secret');
        $this->useManagedIdentity = config('services.azure.key_vault.use_managed_identity');

        if (!$this->vaultUrl) {
            throw new Exception('Azure Key Vault URL not configured');
        }
    }

    /**
     * Get secret from Azure Key Vault
     *
     * @param string $secretName
     * @return string|null
     */
    public function getSecret(string $secretName): ?string
    {
        try {
            $accessToken = $this->getAccessToken();

            if (!$accessToken) {
                throw new Exception('Failed to obtain access token');
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->get("{$this->vaultUrl}/secrets/{$secretName}", [
                'api-version' => '7.4'
            ]);

            if (!$response->successful()) {
                Log::error('Azure Key Vault secret retrieval failed', [
                    'secret_name' => $secretName,
                    'status' => $response->status(),
                    'error' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();
            return $data['value'] ?? null;

        } catch (Exception $e) {
            Log::error('Azure Key Vault error', [
                'secret_name' => $secretName,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Store secret in Azure Key Vault
     *
     * @param string $secretName
     * @param string $secretValue
     * @return bool
     */
    public function setSecret(string $secretName, string $secretValue): bool
    {
        try {
            $accessToken = $this->getAccessToken();

            if (!$accessToken) {
                throw new Exception('Failed to obtain access token');
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->put("{$this->vaultUrl}/secrets/{$secretName}?api-version=7.4", [
                'value' => $secretValue
            ]);

            if (!$response->successful()) {
                Log::error('Azure Key Vault secret storage failed', [
                    'secret_name' => $secretName,
                    'status' => $response->status(),
                    'error' => $response->body()
                ]);
                return false;
            }

            return true;

        } catch (Exception $e) {
            Log::error('Azure Key Vault storage error', [
                'secret_name' => $secretName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get access token for Azure Key Vault
     *
     * @return string|null
     */
    private function getAccessToken(): ?string
    {
        $cacheKey = 'azure_key_vault_token';

        return Cache::remember($cacheKey, 3300, function () { // Cache for 55 minutes (tokens expire in 60)
            try {
                if ($this->useManagedIdentity) {
                    return $this->getManagedIdentityToken();
                } else {
                    return $this->getClientCredentialsToken();
                }
            } catch (Exception $e) {
                Log::error('Failed to get Azure access token', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Get token using managed identity (for Azure-hosted applications)
     *
     * @return string|null
     */
    private function getManagedIdentityToken(): ?string
    {
        $response = Http::withHeaders([
            'Metadata' => 'true'
        ])->get('http://169.254.169.254/metadata/identity/oauth2/token', [
            'api-version' => '2018-02-01',
            'resource' => 'https://vault.azure.net'
        ]);

        if (!$response->successful()) {
            throw new Exception('Managed identity token request failed');
        }

        $data = $response->json();
        return $data['access_token'] ?? null;
    }

    /**
     * Get token using client credentials
     *
     * @return string|null
     */
    private function getClientCredentialsToken(): ?string
    {
        $response = Http::asForm()->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => 'https://vault.azure.net/.default',
            'grant_type' => 'client_credentials'
        ]);

        if (!$response->successful()) {
            throw new Exception('Client credentials token request failed');
        }

        $data = $response->json();
        return $data['access_token'] ?? null;
    }

    /**
     * Get ECW JWK keys from Key Vault
     *
     * @return array|null
     */
    public function getEcwJwkKeys(): ?array
    {
        $privateKey = $this->getSecret('ecw-jwk-private-key');
        $publicKey = $this->getSecret('ecw-jwk-public-key');

        if (!$privateKey || !$publicKey) {
            return null;
        }

        return [
            'private' => $privateKey,
            'public' => $publicKey
        ];
    }

    /**
     * Store ECW JWK keys in Key Vault
     *
     * @param array $keys
     * @return bool
     */
    public function storeEcwJwkKeys(array $keys): bool
    {
        $privateStored = $this->setSecret('ecw-jwk-private-key', $keys['private']);
        $publicStored = $this->setSecret('ecw-jwk-public-key', $keys['public']);

        return $privateStored && $publicStored;
    }
}
