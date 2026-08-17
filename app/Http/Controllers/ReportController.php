<?php

namespace App\Http\Controllers;

use App\Enums\TrendGranularity;
use App\Exports\ExpensesExport;
use App\Models\AppSetting;
use App\Models\Expense;
use App\Models\User;
use App\Services\SpendingReport;
use App\Services\SpendingTrend;
use App\Support\Concerns\PaginatesLists;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The dashboard answers "how am I doing right now"; this page answers "where did
 * the money actually go over a period" — the same numbers, but broken down and
 * comparable against the period before.
 */
class ReportController extends Controller
{
    use PaginatesLists;

    /**
     * Hex twins of the Tailwind classes in categoryStyles.js — the PDF has no
     * stylesheet to resolve a class name against.
     */
    private const SWATCHES = [
        'slate' => '#64748b', 'red' => '#ef4444', 'orange' => '#f97316', 'amber' => '#f59e0b',
        'green' => '#22c55e', 'teal' => '#14b8a6', 'blue' => '#3b82f6', 'indigo' => '#6366f1',
        'purple' => '#a855f7', 'pink' => '#ec4899',
    ];

    public function __construct(
        private readonly SpendingTrend $trend,
        private readonly SpendingReport $report,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewReports');

        $user = $request->user();

        // A junk ?period= falls back rather than 500s — it is a query string.
        $granularity = TrendGranularity::tryFrom((string) $request->query('period'))
            ?? TrendGranularity::Month;

        $anchor = $this->trend->resolveAnchor($granularity, $request->query('at'));
        [$start, $end] = $this->trend->range($granularity, $anchor, $user);

        $series = $this->trend->series($user, $granularity, $anchor);
        $breakdown = $this->report->breakdown($user, $start, $end);

        return Inertia::render('Reports/Index', [
            'granularity' => $granularity->value,
            'anchor' => $this->trend->anchorValue($granularity, $anchor),
            'options' => $this->trend->options($user, $granularity),
            'series' => $series,
            'breakdown' => $breakdown,
            'stats' => $this->report->stats($user, $start, $end, $granularity, $anchor, $breakdown),
            'expenses' => $this->expenses($user, $start, $end, $this->perPage($request)),
        ]);
    }

    /**
     * The same report as a file. Reads through the same period resolution as
     * index(), so a download can never disagree with the screen it came from.
     *
     * Not paginated: a file is exactly where you want every row.
     */
    public function export(Request $request, string $format): BinaryFileResponse|HttpResponse
    {
        abort_unless(in_array($format, ['pdf', 'xlsx', 'csv'], true), 404);

        $user = $request->user();

        $granularity = TrendGranularity::tryFrom((string) $request->query('period'))
            ?? TrendGranularity::Month;
        $anchor = $this->trend->resolveAnchor($granularity, $request->query('at'));
        [$start, $end] = $this->trend->range($granularity, $anchor, $user);

        // 'expenses' drops the summary and ships the list alone — the button
        // on the Expenses card, for when you want the rows and nothing else.
        $listOnly = $request->query('scope') === 'expenses';

        $periodLabel = $this->trend->periodLabel($granularity, $anchor);
        $expenses = $this->allExpenses($user, $start, $end);
        $filename = $this->filename($periodLabel, $format, $listOnly);

        // The spreadsheet is already one row per expense, so the scope only
        // changes its name, not its contents.
        if ($format !== 'pdf') {
            return Excel::download(new ExpensesExport($expenses, $periodLabel), $filename);
        }

        // Computed once and used twice: the stats need the real figures even
        // for a list-only export, which only drops the table from the page.
        $breakdown = $this->report->breakdown($user, $start, $end);

        $pdf = Pdf::loadView('reports.pdf', [
            'brand' => AppSetting::current()->app_name,
            'userName' => $user->name,
            'periodLabel' => $periodLabel,
            'generatedAt' => CarbonImmutable::now()->isoFormat('D MMM YYYY, HH:mm'),
            'stats' => $this->report->stats($user, $start, $end, $granularity, $anchor, $breakdown),
            'breakdown' => $listOnly ? [] : $breakdown,
            'listOnly' => $listOnly,
            'expenses' => $expenses,
            // Formatting helpers, so the view holds no logic.
            'money' => fn (float $amount) => '$'.number_format($amount, 2),
            'swatch' => fn (?string $color) => self::SWATCHES[$color] ?? self::SWATCHES['slate'],
        ])->setPaper('a4');

        return $pdf->download($filename);
    }

    /**
     * @return Collection<int, Expense>
     */
    private function allExpenses(User $user, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return Expense::query()
            ->with('category:id,name,color,icon')
            ->where('user_id', $user->id)
            ->whereBetween('spent_on', [$start->toDateString(), $end->toDateString()])
            ->orderBy('spent_on')
            ->orderBy('id')
            ->get();
    }

    /** e.g. "moneylog-expenses-july-2026.pdf" — sortable, and safe on every filesystem. */
    private function filename(string $periodLabel, string $format, bool $listOnly = false): string
    {
        $brand = Str::slug(AppSetting::current()->app_name) ?: 'report';
        $kind = $listOnly ? 'expenses-' : '';

        return $brand.'-'.$kind.Str::slug($periodLabel).'.'.$format;
    }

    /**
     * The period's expenses, newest first.
     *
     * Paginated: a year holds hundreds of rows, and shipping them all would
     * bloat every page load for a list most people scan the top of. The exports
     * are the way to get the lot.
     *
     * @return array<string, mixed>
     */
    private function expenses(User $user, CarbonImmutable $start, CarbonImmutable $end, int $perPage): array
    {
        $paginator = Expense::query()
            ->with('category:id,uuid,name,color,icon')
            ->where('user_id', $user->id)
            ->whereBetween('spent_on', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('spent_on')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page')
            ->withQueryString();

        return [
            ...$this->paginationMeta($paginator),
            'data' => collect($paginator->items())
                ->map(fn (Expense $expense) => [
                    'uuid' => $expense->uuid,
                    'item' => $expense->item,
                    'price' => (float) $expense->price,
                    'spent_on' => $expense->spent_on->toDateString(),
                    'date_label' => $expense->spent_on->isoFormat('ddd D MMM'),
                    'category' => $expense->category->name,
                    'color' => $expense->category->color?->value,
                    'icon' => $expense->category->icon?->value,
                ])
                ->all(),
        ];
    }

}
