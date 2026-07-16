<?php

namespace App\Http\Resources;

use App\Models\Budget;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Budget
 */
class BudgetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'amount' => (string) $this->amount,
            // Stored as the first of the month; the API speaks in months, so
            // emit 'YYYY-MM' and keep the day out of the contract.
            'month' => $this->month?->format('Y-m'),
            // Null category means the overall budget covering every category.
            'category' => $this->category
                ? new CategoryResource($this->whenLoaded('category'))
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
