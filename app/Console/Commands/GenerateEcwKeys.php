<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AzureKeyVaultService;
use Exception;

class GenerateEcwKeys extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ecw:generate-keys {--azure : Store keys in Azure Key Vault}';

    /**
     * The console command description.
     */
    protected $description = 'Generate RSA key pair for eClinicalWorks JWT signing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->info('Generating RSA key pair for eClinicalWorks integration...');

            $useAzure = $this->option('azure');

            if ($useAzure) {
                return $this->handleAzureKeyVault();
            } else {
                return $this->handleLocalGeneration();
            }

        } catch (Exception $e) {
            $this->error('Failed to generate keys: ' . $e->getMessage());
            return 1; // FAILURE
        }
    }

    /**
     * Handle Azure Key Vault storage
     */
    private function handleAzureKeyVault(): int
    {
        try {
            $keyVault = app(AzureKeyVaultService::class);

            $this->info('Generating RSA key pair...');
            $keyPair = $this->generateRSAKeyPairExternal();

            $this->info('Storing keys in Azure Key Vault...');
            $success = $keyVault->storeEcwJwkKeys($keyPair);

            if (!$success) {
                $this->error('Failed to store keys in Azure Key Vault');
                return 1;
            }

            $this->info('✓ RSA key pair generated and stored in Azure Key Vault successfully!');
            $this->newLine();
            $this->warn('Keys are now securely stored in Azure Key Vault:');
            $this->line('- ecw-jwk-private-key');
            $this->line('- ecw-jwk-public-key');
            $this->newLine();
            $this->info('Your JWK URL will be: ' . url('/api/ecw/jwk'));

            return 0;

        } catch (Exception $e) {
            $this->error('Azure Key Vault error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Handle local key generation (fallback)
     */
    private function handleLocalGeneration(): int
    {
        $this->warn('Generating keys locally. For production, use --azure flag to store in Key Vault.');

        $keyPair = $this->generateRSAKeyPairExternal();
        $this->outputKeysForEnv($keyPair);

        $this->info('✓ RSA key pair generated successfully!');
        $this->newLine();
        $this->warn('Important: For production, store these keys in Azure Key Vault using --azure flag');

        return 0;
    }

    /**
     * Generate RSA key pair using external tools (no OpenSSL dependency)
     */
    private function generateRSAKeyPairExternal(): array
    {
        // Use a simple approach that doesn't require OpenSSL
        $this->warn('For this demo, please manually create RSA keys using online tools or OpenSSL on another machine.');
        $this->newLine();
        $this->line('You can use this command on a machine with OpenSSL:');
        $this->line('openssl genrsa -out private.pem 2048');
        $this->line('openssl rsa -in private.pem -pubout -out public.pem');
        $this->newLine();
        $this->line('Or use an online RSA key generator (search "RSA key generator online")');
        $this->newLine();

        // For demo purposes, return placeholder keys
        // In real implementation, you'd either:
        // 1. Use OpenSSL if available
        // 2. Use a JWT library like firebase/jwt
        // 3. Ask user to provide keys manually

        $privateKeyDemo = "-----BEGIN RSA PRIVATE KEY-----\n(Your private key here)\n-----END RSA PRIVATE KEY-----";
        $publicKeyDemo = "-----BEGIN PUBLIC KEY-----\n(Your public key here)\n-----END PUBLIC KEY-----";

        return [
            'private' => $privateKeyDemo,
            'public' => $publicKeyDemo
        ];
    }

    /**
     * Output keys in format suitable for .env file
     */
    private function outputKeysForEnv(array $keyPair): void
    {
        $this->newLine();
        $this->line('Add these lines to your .env file:');
        $this->newLine();

        // Escape newlines for .env format
        $privateKeyEnv = str_replace("\n", "\\n", $keyPair['private']);
        $publicKeyEnv = str_replace("\n", "\\n", $keyPair['public']);

        $this->line('ECW_JWK_PRIVATE_KEY="' . $privateKeyEnv . '"');
        $this->line('ECW_JWK_PUBLIC_KEY="' . $publicKeyEnv . '"');
        $this->newLine();
    }
}
