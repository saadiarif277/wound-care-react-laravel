<?php

namespace App\Services\PDF;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions;
use MicrosoftAzure\Storage\Common\ServiceException;
use Carbon\Carbon;
use Exception;

/**
 * Service for managing PDF storage in Azure Blob Storage
 */
class AzurePDFStorageService
{
    private ?BlobRestProxy $blobClient = null;
    private string $templateContainer;
    private string $documentContainer;
    private bool $isAzureConfigured = false;
    
    public function __construct()
    {
        $connectionString = config('azure.storage.connection_string');
        
        // Only initialize blob client if connection string is available
        if (!empty($connectionString)) {
            try {
                $this->blobClient = BlobRestProxy::createBlobService($connectionString);
                $this->isAzureConfigured = true;
            } catch (\Exception $e) {
                Log::warning('Azure Storage not configured', ['error' => $e->getMessage()]);
            }
        }
        
        $this->templateContainer = config('pdf.azure.template_container', 'pdf-templates');
        $this->documentContainer = config('pdf.azure.document_container', 'order-pdfs');
        
        // Only ensure containers exist if Azure is configured
        if ($this->isAzureConfigured) {
            $this->ensureContainerExists($this->templateContainer);
            $this->ensureContainerExists($this->documentContainer);
        }
    }

    /**
     * Upload a PDF to Azure Blob Storage
     */
    public function uploadPDF(string $filePath, string $blobName, array $metadata = []): string
    {
        // If Azure is not configured, use local storage
        if (!$this->isAzureConfigured) {
            return $this->uploadToLocal($filePath, $blobName, $metadata);
        }

        try {
            $content = fopen($filePath, 'r');
            if (!$content) {
                throw new Exception("Failed to open file: {$filePath}");
            }

            $options = new CreateBlockBlobOptions();
            $options->setContentType('application/pdf');
            
            // Add metadata
            $metadata['uploaded_at'] = now()->toISOString();
            $metadata['uploaded_by'] = auth()->id() ?? 'system';
            $options->setMetadata($metadata);

            // Upload blob
            $this->blobClient->createBlockBlob(
                $this->documentContainer,
                $blobName,
                $content,
                $options
            );

            fclose($content);

            // Generate URL
            $url = $this->getBlobUrl($this->documentContainer, $blobName);
            
            Log::info('PDF uploaded to Azure', [
                'container' => $this->documentContainer,
                'blob' => $blobName,
                'url' => $url
            ]);

            return $url;

        } catch (ServiceException $e) {
            Log::error('Azure upload failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            throw new Exception('Failed to upload PDF to Azure: ' . $e->getMessage());
        }
    }

    /**
     * Upload a PDF template
     */
    public function uploadTemplate(string $filePath, string $destPath, array $metadata = []): array
    {
        try {
            // If Azure is not configured, use local storage
            if (!$this->isAzureConfigured) {
                $url = $this->uploadToLocal($filePath, $destPath, $metadata);
                return [
                    'success' => true,
                    'url' => $url,
                    'path' => $destPath
                ];
            }

            $content = fopen($filePath, 'r');
            if (!$content) {
                throw new Exception("Failed to open file: {$filePath}");
            }

            $options = new CreateBlockBlobOptions();
            $options->setContentType('application/pdf');
            
            // Add metadata
            $metadata['uploaded_at'] = now()->toISOString();
            $metadata['uploaded_by'] = auth()->id() ?? 'system';
            $options->setMetadata($metadata);

            // Upload blob
            $this->blobClient->createBlockBlob(
                $this->templateContainer,
                $destPath,
                $content,
                $options
            );

            fclose($content);

            // Generate URL
            $url = $this->getBlobUrl($this->templateContainer, $destPath);
            
            Log::info('PDF template uploaded', [
                'container' => $this->templateContainer,
                'path' => $destPath,
                'url' => $url
            ]);

            return [
                'success' => true,
                'url' => $url,
                'path' => $destPath
            ];

        } catch (Exception $e) {
            Log::error('Template upload failed', [
                'error' => $e->getMessage(),
                'path' => $destPath
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to upload template: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Download a PDF from Azure
     */
    public function downloadPDF(string $blobName, string $container = null): string
    {
        $container = $container ?? $this->documentContainer;
        $tempPath = storage_path('app/temp/pdf');
        
        if (!is_dir($tempPath)) {
            mkdir($tempPath, 0755, true);
        }

        $localPath = $tempPath . '/' . Str::random(16) . '.pdf';

        try {
            $blob = $this->blobClient->getBlob($container, $blobName);
            file_put_contents($localPath, $blob->getContentStream());
            
            return $localPath;

        } catch (ServiceException $e) {
            Log::error('PDF download failed', [
                'container' => $container,
                'blob' => $blobName,
                'error' => $e->getMessage()
            ]);
            
            throw new Exception('Failed to download PDF: ' . $e->getMessage());
        }
    }

    /**
     * Download a PDF template
     */
    public function downloadTemplate(string $blobName): array
    {
        try {
            // If Azure is not configured, use local storage
            if (!$this->isAzureConfigured) {
                $content = Storage::disk('public')->get('pdfs/' . $blobName);
                return [
                    'success' => true,
                    'content' => $content
                ];
            }

            $blob = $this->blobClient->getBlob($this->templateContainer, $blobName);
            $content = stream_get_contents($blob->getContentStream());
            
            return [
                'success' => true,
                'content' => $content
            ];

        } catch (Exception $e) {
            Log::error('Template download failed', [
                'blob' => $blobName,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to download template: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate a secure URL with SAS token
     */
    public function generateSecureUrl(
        string $blobName,
        string $container = null,
        int $expirationMinutes = 60
    ): string {
        $container = $container ?? $this->documentContainer;
        
        // If Azure is not configured, return local URL
        if (!$this->isAzureConfigured) {
            $path = ($container === $this->templateContainer ? 'templates/' : 'pdfs/') . $blobName;
            return Storage::disk('public')->url($path);
        }
        
        try {
            // Create SAS token
            $sasHelper = new \MicrosoftAzure\Storage\Blob\BlobSharedAccessSignatureHelper(
                config('azure.storage.account_name'),
                config('azure.storage.account_key')
            );

            $expiryTime = Carbon::now()->addMinutes($expirationMinutes);
            
            $sas = $sasHelper->generateBlobServiceSharedAccessSignatureToken(
                \MicrosoftAzure\Storage\Blob\Models\BlobSharedAccessSignaturePermissions::READ,
                $container,
                $blobName,
                $expiryTime,
                Carbon::now()->subMinutes(5), // Start time with 5-minute buffer
                '',
                \MicrosoftAzure\Storage\Common\SharedAccessSignatureProtocol::HTTPS_ONLY
            );

            return $this->getBlobUrl($container, $blobName) . '?' . $sas;

        } catch (Exception $e) {
            Log::error('Failed to generate SAS token', [
                'container' => $container,
                'blob' => $blobName,
                'error' => $e->getMessage()
            ]);
            
            // Fall back to regular URL
            return $this->getBlobUrl($container, $blobName);
        }
    }

    /**
     * Delete a PDF from Azure
     */
    public function deletePDF(string $blobName, string $container = null): bool
    {
        $container = $container ?? $this->documentContainer;

        try {
            $this->blobClient->deleteBlob($container, $blobName);
            
            Log::info('PDF deleted from Azure', [
                'container' => $container,
                'blob' => $blobName
            ]);
            
            return true;

        } catch (ServiceException $e) {
            if ($e->getCode() === 404) {
                return true; // Already deleted
            }
            
            Log::error('Failed to delete PDF', [
                'container' => $container,
                'blob' => $blobName,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Copy a PDF to a new location
     */
    public function copyPDF(string $sourceBlobName, string $destBlobName, string $sourceContainer = null): string
    {
        $sourceContainer = $sourceContainer ?? $this->documentContainer;

        try {
            $sourceUrl = $this->getBlobUrl($sourceContainer, $sourceBlobName);
            
            $this->blobClient->copyBlob(
                $this->documentContainer,
                $destBlobName,
                $sourceUrl
            );

            return $this->getBlobUrl($this->documentContainer, $destBlobName);

        } catch (ServiceException $e) {
            Log::error('Failed to copy PDF', [
                'source' => $sourceBlobName,
                'dest' => $destBlobName,
                'error' => $e->getMessage()
            ]);
            
            throw new Exception('Failed to copy PDF: ' . $e->getMessage());
        }
    }

    /**
     * List PDFs in a container
     */
    public function listPDFs(string $prefix = '', string $container = null): array
    {
        $container = $container ?? $this->documentContainer;
        $pdfs = [];

        try {
            $listBlobsOptions = new \MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions();
            $listBlobsOptions->setPrefix($prefix);

            do {
                $result = $this->blobClient->listBlobs($container, $listBlobsOptions);
                
                foreach ($result->getBlobs() as $blob) {
                    $pdfs[] = [
                        'name' => $blob->getName(),
                        'url' => $this->getBlobUrl($container, $blob->getName()),
                        'size' => $blob->getProperties()->getContentLength(),
                        'last_modified' => $blob->getProperties()->getLastModified(),
                        'metadata' => $blob->getMetadata()
                    ];
                }

                $listBlobsOptions->setContinuationToken($result->getContinuationToken());
            } while ($result->getContinuationToken());

        } catch (ServiceException $e) {
            Log::error('Failed to list PDFs', [
                'container' => $container,
                'prefix' => $prefix,
                'error' => $e->getMessage()
            ]);
        }

        return $pdfs;
    }

    /**
     * Check if a blob exists
     */
    public function exists(string $blobName, string $container = null): bool
    {
        $container = $container ?? $this->documentContainer;

        try {
            $this->blobClient->getBlobMetadata($container, $blobName);
            return true;
        } catch (ServiceException $e) {
            if ($e->getCode() === 404) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Get blob metadata
     */
    public function getMetadata(string $blobName, string $container = null): array
    {
        $container = $container ?? $this->documentContainer;

        try {
            $result = $this->blobClient->getBlobMetadata($container, $blobName);
            return $result->getMetadata();
        } catch (ServiceException $e) {
            Log::error('Failed to get blob metadata', [
                'container' => $container,
                'blob' => $blobName,
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Update blob metadata
     */
    public function updateMetadata(string $blobName, array $metadata, string $container = null): bool
    {
        $container = $container ?? $this->documentContainer;

        try {
            $this->blobClient->setBlobMetadata($container, $blobName, $metadata);
            return true;
        } catch (ServiceException $e) {
            Log::error('Failed to update blob metadata', [
                'container' => $container,
                'blob' => $blobName,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Ensure container exists
     */
    private function ensureContainerExists(string $container): void
    {
        try {
            $this->blobClient->createContainer($container);
        } catch (ServiceException $e) {
            // Container might already exist, which is fine
            if ($e->getCode() !== 409) {
                Log::warning('Failed to create container', [
                    'container' => $container,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Get the full URL for a blob
     */
    private function getBlobUrl(string $container, string $blobName): string
    {
        return sprintf(
            'https://%s.blob.core.windows.net/%s/%s',
            config('azure.storage.account_name'),
            $container,
            $blobName
        );
    }

    /**
     * Archive old PDFs
     */
    public function archiveOldPDFs(int $daysOld = 365): int
    {
        $archived = 0;
        $cutoffDate = Carbon::now()->subDays($daysOld);

        try {
            $pdfs = $this->listPDFs();
            
            foreach ($pdfs as $pdf) {
                if ($pdf['last_modified'] < $cutoffDate) {
                    $archiveName = 'archive/' . $pdf['name'];
                    
                    // Copy to archive
                    $this->copyPDF($pdf['name'], $archiveName);
                    
                    // Delete original
                    $this->deletePDF($pdf['name']);
                    
                    $archived++;
                }
            }
            
            Log::info('PDFs archived', [
                'count' => $archived,
                'cutoff_date' => $cutoffDate->toDateString()
            ]);

        } catch (Exception $e) {
            Log::error('Failed to archive PDFs', [
                'error' => $e->getMessage()
            ]);
        }

        return $archived;
    }

    /**
     * Upload to local storage when Azure is not configured
     */
    private function uploadToLocal(string $filePath, string $blobName, array $metadata = []): string
    {
        $relativePath = 'pdfs/' . $blobName;
        Storage::disk('public')->put($relativePath, file_get_contents($filePath));
        
        Log::info('PDF uploaded to local storage', [
            'path' => $relativePath,
            'metadata' => $metadata
        ]);
        
        return Storage::disk('public')->url($relativePath);
    }

    /**
     * Check if Azure Storage is configured
     */
    public function isConfigured(): bool
    {
        return $this->isAzureConfigured;
    }
}