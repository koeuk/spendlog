<?php

namespace App\Http\Resources;

use App\Models\Workout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Workout
 */
class WorkoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'performed_on' => $this->performed_on->toDateString(),
            'duration_seconds' => $this->duration_seconds,
            'notes' => $this->notes,
            // Always kilograms, as stored. A client that wants pounds converts
            // on its own side — see App\Enums\WeightUnit for why the canonical
            // unit never leaves the server converted.
            'volume_kg' => $this->whenLoaded('sets', fn () => round($this->volumeKg())),
            'sets' => $this->whenLoaded('sets', fn () => $this->sets->map(fn ($set) => [
                'uuid' => $set->uuid,
                'exercise' => $this->when(
                    $set->relationLoaded('exerciseType') && $set->exerciseType !== null,
                    fn () => [
                        'uuid' => $set->exerciseType->uuid,
                        'name' => $set->exerciseType->name,
                        'muscle_group' => $set->exerciseType->muscle_group?->value,
                        'is_cardio' => $set->exerciseType->is_cardio,
                    ],
                ),
                'set_no' => $set->set_no,
                'reps' => $set->reps,
                'weight_kg' => $set->weight_kg === null ? null : (float) $set->weight_kg,
                'distance_m' => $set->distance_m,
                'duration_seconds' => $set->duration_seconds,
                'rpe' => $set->rpe,
            ])->values()),
            'created_at' => $this->created_at,
        ];
    }
}
