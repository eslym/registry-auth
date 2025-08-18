<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read User $resource
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->whenHas('id'),
            'username' => $this->whenHas('username'),
            'is_admin' => $this->whenHas('is_admin'),
            'password_expired_at' => $this->whenHas('password_expired_at', fn($val) => $val?->toIso8601String()),
            'created_at' => $this->whenHas('created_at', fn($val) => $val?->toIso8601String()),
            'groups' => $this->whenLoaded('groups', fn() => GroupResource::collection($this->groups)),
            'access_controls' => $this->whenLoaded(
                'access_controls',
                fn() => AccessControlResource::collection($this->access_controls)
            ),
        ];
    }
}
