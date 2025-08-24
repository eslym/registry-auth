<?php

namespace App\Http\Resources;

use App\Lib\Utils;
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
            'is_refresh_token' => $this->whenHas('is_refresh_token'),
            'description' => $this->whenHas('description'),
            'last_used_at' => $this->whenHas('last_used_at', Utils::invoke('toIso8601String')),
            'last_used_ip' => $this->whenHas('last_used_ip'),
            'expired_at' => $this->whenHas('expired_at', Utils::invoke('toIso8601String')),

            'created_at' => $this->whenHas('created_at', Utils::invoke('toIso8601String')),
            'updated_at' => $this->whenHas('updated_at', Utils::invoke('toIso8601String')),

            'user_id' => $this->whenHas('user_id'),

            'access_controls' => $this
                ->whenLoaded(
                    'access_controls',
                    fn() => AccessControlResource::collection($this->access_controls)
                ),

            'user' => $this
                ->whenLoaded(
                    'user',
                    fn() => UserResource::make($this->user)
                ),
        ];
    }
}
