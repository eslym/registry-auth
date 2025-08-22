<?php

namespace App\Http\Resources;

use App\Models\AccessToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AccessToken $resource
 * @mixin AccessToken
 */
class AccessTokenResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'expired_at' => $this->expired_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            'access_controls' => $this
                ->whenLoaded(
                    'access_controls',
                    fn() => AccessControlResource::collection($this->access_controls)
                ),
        ];
    }
}
