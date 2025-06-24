<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'] ?? $this->id ?? uniqid('doc_'),
            'filename' => $this->resource['filename'] ?? $this->filename ?? 'Document',
            'name' => $this->resource['name'] ?? $this->name ?? $this->resource['filename'] ?? 'Document',
            'url' => $this->resource['url'] ?? $this->url ?? '#',
            'size' => $this->resource['size'] ?? $this->size ?? null,
            'type' => $this->resource['type'] ?? $this->type ?? $this->resource['mime_type'] ?? null,
            'created_at' => isset($this->created_at) ? $this->created_at->toIso8601String() : null,
            'updated_at' => isset($this->updated_at) ? $this->updated_at->toIso8601String() : null,
        ];
    }
}
