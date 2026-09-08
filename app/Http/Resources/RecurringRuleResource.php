<?php

namespace App\Http\Resources;

use App\Models\RecurringRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecurringRule
 */
class RecurringRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'kind' => $this->kind->value,
            'title' => $this->title,
            // Money is a string throughout this API, formatted to two places
            // rather than cast straight through — see ExpenseResource::price.
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            // Null for an income rule, which has no category to carry.
            'category' => $this->category_id === null
                ? null
                : new CategoryResource($this->whenLoaded('category')),
            'frequency' => $this->frequency->value,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'next_run_on' => $this->next_run_on?->toDateString(),
            'last_run_on' => $this->last_run_on?->toDateString(),
            'active' => $this->active,
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
