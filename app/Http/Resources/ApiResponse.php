<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class ApiResponse extends JsonResource
{
    private bool $success;
    private ?string $message;
    private array $warnings;
    private array $metadata;

    /**
     * Create a new resource instance.
     */
    public function __construct($resource, bool $success = true, ?string $message = null)
    {
        parent::__construct($resource);
        $this->success = $success;
        $this->message = $message;
        $this->warnings = [];
        $this->metadata = [];
    }

    /**
     * Create a success response
     */
    public static function success($data = null, string $message = null): self
    {
        return new self($data, true, $message);
    }

    /**
     * Create an error response
     */
    public static function error($data = null, string $message = 'An error occurred'): self
    {
        return new self($data, false, $message);
    }

    /**
     * Add warnings to the response
     */
    public function withWarnings(array $warnings): self
    {
        $this->warnings = $warnings;
        return $this;
    }

    /**
     * Add metadata to the response
     */
    public function withMetadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $response = [
            'success' => $this->success,
        ];

        if ($this->message) {
            $response['message'] = $this->message;
        }

        // Handle different types of data
        if ($this->resource instanceof JsonResource) {
            $response['data'] = $this->resource->toArray($request);
        } elseif ($this->resource instanceof Collection) {
            $response['data'] = $this->resource->toArray();
        } elseif (is_null($this->resource)) {
            // Don't include data key if resource is null
        } else {
            $response['data'] = $this->resource;
        }

        if (!empty($this->warnings)) {
            $response['warnings'] = $this->warnings;
        }

        if (!empty($this->metadata)) {
            $response['metadata'] = array_merge($this->metadata, [
                'timestamp' => now()->toIso8601String(),
                'request_id' => $request->header('X-Request-ID', uniqid('req_')),
            ]);
        }

        return $response;
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'api_version' => config('app.api_version', 'v1'),
                'timestamp' => now()->toIso8601String(),
                'request_id' => $request->header('X-Request-ID', uniqid('req_')),
                'response_time' => round((microtime(true) - LARAVEL_START) * 1000, 2) . 'ms',
            ],
        ];
    }

    /**
     * Create a response with additional context
     */
    public static function withContext($data, array $context): self
    {
        $response = new self($data, true);
        
        if (isset($context['message'])) {
            $response->message = $context['message'];
        }
        
        if (isset($context['warnings'])) {
            $response->warnings = $context['warnings'];
        }
        
        if (isset($context['metadata'])) {
            $response->metadata = $context['metadata'];
        }
        
        return $response;
    }

    /**
     * Create a paginated response
     */
    public static function paginated($paginator, string $resourceClass): array
    {
        return [
            'success' => true,
            'data' => $resourceClass::collection($paginator)->resolve(request()),
            'pagination' => [
                'total' => $paginator->total(),
                'count' => $paginator->count(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'total_pages' => $paginator->lastPage(),
                'links' => [
                    'first' => $paginator->url(1),
                    'last' => $paginator->url($paginator->lastPage()),
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                ],
            ],
            'meta' => [
                'api_version' => config('app.api_version', 'v1'),
                'timestamp' => now()->toIso8601String(),
                'request_id' => request()->header('X-Request-ID', uniqid('req_')),
            ],
        ];
    }
}