<?php

namespace App\Http\Resources;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
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
            'item' => $this->item,
            // The raw per-locale map, so a client editing an expense can round
            // trip it back to POST/PATCH, which take item[en]/item[km].
            'item_translations' => $this->getTranslations('item'),
            // Money is a string throughout this API — see the money note in
            // DEVELOPMENT_PLAN.md. Formatted to two places rather than cast
            // straight through: the column holds four so that riel amounts
            // survive conversion (see Currency::SCALE), but this API has always
            // emitted "12.50" and clients parse it as a USD figure.
            'price' => number_format((float) $this->price, 2, '.', ''),
            'spent_on' => $this->spent_on?->toDateString(),
            'category' => new CategoryResource($this->whenLoaded('category')),
            // Deliberately not UserResource: the owner is only meaningful to an
            // admin looking across users, and the full resource would leak email
            // addresses and fire a roles query per row for is_admin.
            'owner' => $this->whenLoaded('user', fn () => [
                'uuid' => $this->user->uuid,
                'name' => $this->user->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
