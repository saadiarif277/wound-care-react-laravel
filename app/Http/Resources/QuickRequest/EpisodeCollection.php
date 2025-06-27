<?php

namespace App\Http\Resources\QuickRequest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class EpisodeCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = EpisodeResource::class;

    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => $this->collection,
            'pagination' => $this->when($this->resource instanceof \Illuminate\Pagination\LengthAwarePaginator, [
                'total' => $this->total(),
                'count' => $this->count(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
                'total_pages' => $this->lastPage(),
                'links' => [
                    'first' => $this->url(1),
                    'last' => $this->url($this->lastPage()),
                    'prev' => $this->previousPageUrl(),
                    'next' => $this->nextPageUrl(),
                ],
            ]),
            'filters' => $this->when($request->has('filters'), [
                'status' => $request->input('filters.status'),
                'manufacturer' => $request->input('filters.manufacturer'),
                'date_from' => $request->input('filters.date_from'),
                'date_to' => $request->input('filters.date_to'),
                'search' => $request->input('filters.search'),
            ]),
            'sort' => $this->when($request->has('sort'), [
                'field' => $request->input('sort', 'created_at'),
                'direction' => $request->input('direction', 'desc'),
            ]),
        ];
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
            'links' => [
                'self' => $request->fullUrl(),
                'create' => route('api.v1.quick-request.episodes.store'),
            ],
        ];
    }

    /**
     * Customize the pagination information
     */
    public function paginationInformation($request, $paginated, $default): array
    {
        return [
            'pagination' => [
                'total' => $paginated['total'],
                'count' => count($paginated['data']),
                'per_page' => $paginated['per_page'],
                'current_page' => $paginated['current_page'],
                'total_pages' => $paginated['last_page'],
                'links' => [
                    'first' => $paginated['first_page_url'],
                    'last' => $paginated['last_page_url'],
                    'prev' => $paginated['prev_page_url'],
                    'next' => $paginated['next_page_url'],
                ],
            ],
        ];
    }
}