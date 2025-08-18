<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read LengthAwarePaginator $resource
 */
class PaginatedCollection extends JsonResource
{
    public static $wrap = null;

    /**
     * @param LengthAwarePaginator $resource
     * @param class-string $collects
     * @param array $meta
     */
    public function __construct(LengthAwarePaginator $resource, public string $collects, public array $meta)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'items' => collect($this->resource->items())->mapInto($this->collects),
            'page' => [
                'current' => $this->resource->currentPage(),
                'max' => $this->resource->lastPage(),
                'total' => $this->resource->total(),
                'limit' => $this->resource->perPage(),
            ],
            'meta' => $this->meta,
        ];
    }
}
