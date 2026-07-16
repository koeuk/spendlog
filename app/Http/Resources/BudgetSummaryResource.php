<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the array from App\Services\BudgetSummary.
 *
 * That service is shared with the Inertia pages, which consume money as floats
 * — so rather than change it and churn the web side, this normalises money to
 * strings at the API boundary. Without it the summary would answer `10` where
 * every other endpoint answers "10.00".
 *
 * Percentages stay numeric: they are ratios, not money, and a client does
 * arithmetic on them.
 */
class BudgetSummaryResource extends JsonResource
{
    /**
     * The summary is a plain array, so the usual model-wrapping does not apply.
     */
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $summary = $this->resource;

        return [
            'month' => $summary['month'],
            'overall' => $this->row($summary['overall']),
            'categories' => array_map(
                fn (array $category) => $this->row($category),
                $summary['categories'],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function row(array $row): array
    {
        return [
            ...$row,
            'spent' => $this->money($row['spent']),
            // Null stays null: "no budget set" is different from "0.00".
            'budget' => $this->money($row['budget']),
            'remaining' => $this->money($row['remaining']),
        ];
    }

    private function money(int|float|string|null $value): ?string
    {
        return $value === null ? null : number_format((float) $value, 2, '.', '');
    }
}
