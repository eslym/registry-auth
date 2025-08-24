<?php

namespace App\Http\Resources;

use App\Lib\Utils;
use App\Models\AccessControl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AccessControl $resource
 * @mixin AccessControl
 */
class AccessControlResource extends JsonResource
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
            'rule' => $this->whenHas('rule'),
            'access_level' => $this->whenHas('access_level'),

            'created_at' => $this->whenHas('created_at', Utils::invoke('toIso8601String')),
            'updated_at' => $this->whenHas('updated_at', Utils::invoke('toIso8601String')),
        ];
    }
}
