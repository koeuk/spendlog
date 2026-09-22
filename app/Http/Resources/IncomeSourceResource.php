<?php

namespace App\Http\Resources;

use App\Models\IncomeSource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IncomeSource
 */
class IncomeSourceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            /*
             * How much income is filed under this name. Counted on the string,
             * because that is what an income carries — a source the catalogue
             * has never heard of still counts, and a name nobody has used yet
             * is honestly zero rather than missing.
             *
             * The list query loads both; a single row from a write does not,
             * and reports zero rather than making the client handle absence.
             */
            'uses' => (int) ($this->uses ?? 0),
            'total' => number_format((float) ($this->total ?? 0), 2, '.', ''),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
