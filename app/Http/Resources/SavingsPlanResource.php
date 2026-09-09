<?php

namespace App\Http\Resources;

use App\Models\SavingsPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SavingsPlan
 */
class SavingsPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            // Stored as the first of the month; the API speaks in months, so
            // emit 'YYYY-MM' and keep the day out of the contract.
            'month' => $this->month?->format('Y-m'),
            // Two places, though the column holds four — see BudgetResource.
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
