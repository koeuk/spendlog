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
            // Two places, though the column holds four: the extra precision
            // exists so riel amounts survive conversion (see Currency::SCALE),
            // not to change what this API has always emitted. Clients read a
            // USD figure, and "300.0000" would break every one that expects
            // "300.00".
            'amount' => number_format((float) $this->amount, 2, '.', ''),
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
