<?php

namespace App\Http\Resources;

use App\Http\Requests\SavingsEntryRequest;
use App\Models\SavingsEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SavingsEntry
 */
class SavingsEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            // The column is signed; the API speaks in a direction plus an
            // absolute amount, the same shape the client sent.
            'type' => $this->isWithdrawal() ? SavingsEntryRequest::WITHDRAW : SavingsEntryRequest::DEPOSIT,
            'amount' => number_format(abs((float) $this->amount), 2, '.', ''),
            // Where a deposit came from, or null. Withdrawals never carry one.
            'source' => $this->source,
            'saved_on' => $this->saved_on?->toDateString(),
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
