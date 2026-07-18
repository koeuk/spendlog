<?php

namespace App\Http\Resources;

use App\Models\ExerciseType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExerciseType
 */
class ExerciseTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            // The active locale's value. Reading ->name goes through spatie's
            // accessor; the raw JSON map would leak every translation.
            'name' => $this->name,
            'muscle_group' => $this->muscle_group?->value,
            'is_cardio' => $this->is_cardio,
            'color' => $this->color?->value,
            'icon' => $this->icon?->value,
            // Whether this is one the caller invented, as opposed to a shared
            // default. The owner's id itself stays internal.
            'is_mine' => ! $this->isGlobal(),
        ];
    }
}
