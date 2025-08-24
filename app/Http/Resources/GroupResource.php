<?php

namespace App\Http\Resources;

use App\Lib\Utils;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Group $resource
 * @mixin Group
 */
class GroupResource extends JsonResource
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
            'name' => $this->whenHas('name'),
            'users_count' => $this->whenCounted('users'),

            'created_at' => $this->whenHas('created_at', Utils::invoke('toIso8601String')),
            'updated_at' => $this->whenHas('updated_at', Utils::invoke('toIso8601String')),

            'access_controls' => $this->whenLoaded(
                'access_controls',
                fn() => AccessControlResource::collection($this->access_controls)
            ),
        ];
    }
}
