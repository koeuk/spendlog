<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            // Resolved for the active locale, falling back to English — this is
            // the one to render.
            'name' => $this->name,
            // The raw per-locale map as stored. Writes take a plain `name` and
            // land under the fallback locale, so this is now a read of what is
            // there rather than the shape to send back.
            'name_translations' => $this->getTranslations('name'),
            'color' => $this->color?->value,
            'icon' => $this->icon?->value,
            // Only present when the caller loaded withCount('expenses'), rather
            // than triggering an N+1 by counting here.
            'expenses_count' => $this->whenCounted('expenses'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
