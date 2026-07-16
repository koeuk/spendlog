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
            'item' => $this->item,
            // Money is a string throughout this API — see the money note in
            // DEVELOPMENT_PLAN.md. The decimal:2 cast already yields "12.50",
            // so this preserves the trailing zero a float would drop.
            'price' => (string) $this->price,
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
