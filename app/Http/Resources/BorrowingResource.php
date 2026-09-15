<?php

namespace App\Http\Resources;

use App\Models\Borrowing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Borrowing
 */
class BorrowingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'lender' => $this->lender,
            'lender_type' => $this->lender_type->value,
            // Money is a string throughout this API, formatted to two places
            // rather than cast straight through — see ExpenseResource::price.
            'amount' => $this->money((float) $this->amount),
            // Derived from the ledger every time, never stored — so they
            // cannot drift from the repayments below.
            'repaid' => $this->money($this->repaid()),
            'remaining' => $this->money($this->remaining()),
            'percent_repaid' => $this->percentRepaid(),
            'settled' => $this->isSettled(),
            'overdue' => $this->isOverdue(),
            'borrowed_on' => $this->borrowed_on?->toDateString(),
            'due_on' => $this->due_on?->toDateString(),
            'note' => $this->note,
            // Only on a single borrowing; a listing carries the count instead.
            'repayments' => BorrowingRepaymentResource::collection($this->whenLoaded('repayments')),
            'repayments_count' => $this->whenCounted('repayments'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
