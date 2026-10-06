<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Level;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Level */
class LevelResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, slug: string, dance_type: array{id: int, name: string}|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'dance_type' => $this->danceType === null ? null : [
                'id' => $this->danceType->id,
                'name' => $this->danceType->name,
            ],
        ];
    }
}
