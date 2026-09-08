<?php

namespace App\Http\Resources;

use App\Models\SavingsGoal;
use App\Services\SavingsSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SavingsGoal
 */
class SavingsGoalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $summary = app(SavingsSummary::class);

        // Reads the withSum() attribute when the query attached one, and falls
        // back to summing the ledger for a goal loaded on its own.
        $saved = $summary->saved($this->resource);
        $target = (float) $this->target_amount;

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'target_amount' => number_format($target, 2, '.', ''),
            'saved' => number_format($saved, 2, '.', ''),
            'remaining' => number_format($summary->remaining($saved, $target), 2, '.', ''),
            // A ratio, not money — stays numeric, and is capped at 100 so a
            // progress bar cannot overflow its track.
            'percent' => $summary->percent($saved, $target),
            'reached' => $summary->reached($saved, $target),
            'deadline' => $this->deadline?->toDateString(),
            'color' => $this->color?->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            // Only on the detail view — the list would otherwise carry every
            // ledger for every goal.
            'entries' => SavingsEntryResource::collection($this->whenLoaded('entries')),
        ];
    }
}
