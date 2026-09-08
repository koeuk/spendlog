<?php

namespace App\Http\Resources;

use App\Models\Income;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Income
 */
class IncomeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'source' => $this->source,
            // Money is a string throughout this API, formatted to two places
            // rather than cast straight through — see ExpenseResource::price.
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'received_on' => $this->received_on?->toDateString(),
            // Written by a recurring rule — see ExpenseResource::recurring.
            'recurring' => $this->recurring_rule_id !== null,
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
