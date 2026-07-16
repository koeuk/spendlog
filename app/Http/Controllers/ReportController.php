<?php

namespace App\Http\Controllers;

use App\Enums\TrendGranularity;
use App\Exports\ExpensesExport;
use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use App\Services\SpendingTrend;
use Carbon\CarbonImmutable;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
    /**
     * Hex twins of the Tailwind classes in categoryStyles.js — the PDF has no
     * stylesheet to resolve a class name against.
     */
    private const SWATCHES = [
        'slate' => '#64748b', 'red' => '#ef4444', 'orange' => '#f97316', 'amber' => '#f59e0b',
        'green' => '#22c55e', 'teal' => '#14b8a6', 'blue' => '#3b82f6', 'indigo' => '#6366f1',
        'purple' => '#a855f7', 'pink' => '#ec4899',
    ];

    public function __construct(private readonly SpendingTrend $trend) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        // A junk ?period= falls back rather than 500s — it is a query string.
        $granularity = TrendGranularity::tryFrom((string) $request->query('period'))
            ?? TrendGranularity::Month;

        $anchor = $this->trend->resolveAnchor($granularity, $request->query('at'));
        [$start, $end] = $this->trend->range($granularity, $anchor, $user);

        $series = $this->trend->series($user, $granularity, $anchor);
        $breakdown = $this->breakdown($user, $start, $end);

        return Inertia::render('Reports/Index', [
            'granularity' => $granularity->value,
            'anchor' => $this->trend->anchorValue($granularity, $anchor),
            'options' => $this->trend->options($user, $granularity),
            'series' => $series,
            'breakdown' => $breakdown,
            'stats' => $this->stats($user, $start, $end, $granularity, $anchor, $breakdown),
            'expenses' => $this->expenses($user, $start, $end),
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

        $periodLabel = $this->periodLabelFor($granularity, $anchor);
        $expenses = $this->allExpenses($user, $start, $end);
        $filename = $this->filename($periodLabel, $format, $listOnly);

        // The spreadsheet is already one row per expense, so the scope only
        // changes its name, not its contents.
        if ($format !== 'pdf') {
            return Excel::download(new ExpensesExport($expenses, $periodLabel), $filename);
        }

        $breakdown = $listOnly ? [] : $this->breakdown($user, $start, $end);

        $pdf = Pdf::loadView('reports.pdf', [
            'brand' => AppSetting::current()->app_name,
            'userName' => $user->name,
            'periodLabel' => $periodLabel,
            'generatedAt' => CarbonImmutable::now()->isoFormat('D MMM YYYY, HH:mm'),
            'stats' => $this->stats($user, $start, $end, $granularity, $anchor, $this->breakdown($user, $start, $end)),
            'breakdown' => $breakdown,
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
    private function expenses(User $user, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $paginator = Expense::query()
            ->with('category:id,uuid,name,color,icon')
            ->where('user_id', $user->id)
            ->whereBetween('spent_on', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('spent_on')
            ->orderByDesc('id')
            ->paginate(25, ['*'], 'page')
            ->withQueryString();

        return [
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
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'prev_page_url' => $paginator->previousPageUrl(),
            'next_page_url' => $paginator->nextPageUrl(),
        ];
    }

    /**
     * Spend per category over the range, largest first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function breakdown(User $user, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = Expense::query()
            ->where('user_id', $user->id)
            ->whereBetween('spent_on', [$start->toDateString(), $end->toDateString()])
            ->groupBy('category_id')
            ->selectRaw('category_id, SUM(price) as total, COUNT(*) as count')
            ->get()
            ->keyBy('category_id');

        $total = (float) $rows->sum('total');

        return Category::query()
            ->whereIn('id', $rows->keys())
            ->get()
            ->map(fn (Category $category) => [
                'uuid' => $category->uuid,
                'name' => $category->name,
                'color' => $category->color?->value,
                'icon' => $category->icon?->value,
                'total' => round((float) $rows[$category->id]->total, 2),
                'count' => (int) $rows[$category->id]->count,
                'average' => round((float) $rows[$category->id]->total / max($rows[$category->id]->count, 1), 2),
                'share' => $total > 0 ? round(((float) $rows[$category->id]->total / $total) * 100, 1) : 0,
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * Headline figures, including the change against the previous period —
     * a total means little without something to compare it to.
     *
     * @param  array<int, array<string, mixed>>  $breakdown
     * @return array<string, mixed>
     */
    private function stats(
        User $user,
        CarbonImmutable $start,
        CarbonImmutable $end,
        TrendGranularity $granularity,
        CarbonImmutable $anchor,
        array $breakdown,
    ): array {
        $total = round(array_sum(array_column($breakdown, 'total')), 2);
        $count = array_sum(array_column($breakdown, 'count'));

        // Averaged over elapsed days only: dividing this month's spend by 31 on
        // the 3rd would report a daily average three times lower than reality.
        $now = CarbonImmutable::now();
        $last = $end->gt($now) ? $now : $end;
        $days = max($start->diffInDays($last) + 1, 1);

        // All time has nothing before it, so there is no comparison to make.
        $previousAnchor = match ($granularity) {
            TrendGranularity::Week => $anchor->subWeek(),
            TrendGranularity::Month => $anchor->subMonth(),
            TrendGranularity::Year => $anchor->subYear(),
            TrendGranularity::All => null,
        };

        $previous = 0.0;

        if ($previousAnchor !== null) {
            [$prevStart, $prevEnd] = $this->trend->range($granularity, $previousAnchor, $user);

            $previous = round((float) Expense::query()
                ->where('user_id', $user->id)
                ->whereBetween('spent_on', [$prevStart->toDateString(), $prevEnd->toDateString()])
                ->sum('price'), 2);
        }

        return [
            'total' => $total,
            'count' => $count,
            'daily_average' => round($total / $days, 2),
            'previous' => $previous,
            // Null, not 0: with nothing to compare against, "+100%" would be a
            // fabricated claim rather than a measurement.
            'change_percent' => $previous > 0
                ? round((($total - $previous) / $previous) * 100, 1)
                : null,
            'previous_label' => $previousAnchor !== null
                ? $this->periodLabelFor($granularity, $previousAnchor)
                : null,
        ];
    }

    private function periodLabelFor(TrendGranularity $granularity, CarbonImmutable $date): string
    {
        return match ($granularity) {
            TrendGranularity::Week => $date->startOfWeek()->isoFormat('D MMM').' – '.$date->endOfWeek()->isoFormat('D MMM YYYY'),
            TrendGranularity::Month => $date->isoFormat('MMMM YYYY'),
            TrendGranularity::Year => $date->isoFormat('YYYY'),
            TrendGranularity::All => __('All time'),
        };
    }
}
