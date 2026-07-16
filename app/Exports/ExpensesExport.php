<?php

namespace App\Exports;

use App\Models\Expense;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One row per expense, for the chosen period.
 *
 * @implements FromCollection<int, Expense>
 */
class ExpensesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /**
     * @param  Collection<int, Expense>  $expenses
     */
    public function __construct(
        private readonly Collection $expenses,
        private readonly string $periodLabel,
    ) {}

    public function collection(): Collection
    {
        return $this->expenses;
    }

    public function title(): string
    {
        // Sheet names cannot contain : \ / ? * [ ] and cap at 31 characters.
        return mb_substr(preg_replace('/[:\\\\\/?*\[\]]/', '-', $this->periodLabel), 0, 31);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            __('Date'),
            __('Item'),
            __('Category'),
            __('Amount'),
        ];
    }

    /**
     * @param  Expense  $expense
     * @return array<int, mixed>
     */
    public function map($expense): array
    {
        return [
            $expense->spent_on->toDateString(),
            $expense->item,
            $expense->category?->name,
            // A number, not a formatted string: a spreadsheet's whole point is
            // that you can sum the column afterwards.
            (float) $expense->price,
        ];
    }

    /**
     * @return array<int|string, mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('D')->getNumberFormat()->setFormatCode('#,##0.00');

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
