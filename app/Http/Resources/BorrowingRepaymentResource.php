<?php

namespace App\Http\Resources;

use App\Models\BorrowingRepayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BorrowingRepayment
 */
class BorrowingRepaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            // Money is a string throughout this API — see ExpenseResource::price.
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'paid_on' => $this->paid_on?->toDateString(),
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
