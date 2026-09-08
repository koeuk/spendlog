<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\Concerns\PaginatesLists;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Activity: the web face of the same log the API serves. Your own
 * is open to any signed-in account; everyone's needs an admin — the same rule
 * as Api\V1\ActivityController, so the two doors cannot disagree.
 */
class ActivityController extends Controller
{
    use PaginatesLists;

    public function index(Request $request): Response
    {
        $me = $request->user();
        $all = $request->query('scope') === 'all';

        abort_if($all && ! $me->isAdmin(), 403);

        $paginator = ActivityLog::query()
            ->with('user:id,uuid,name')
            ->when(! $all, fn ($query) => $query->where('user_id', $me->id))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        $entries = collect($paginator->items())->map(fn (ActivityLog $log) => [
            'uuid' => $log->uuid,
            'action' => $log->action,
            'subject' => $log->subjectKind(),
            'label' => $log->subject_label,
            // A list rather than the stored map, so the template need not
            // iterate object keys.
            'changes' => collect($log->changes ?? [])
                ->map(fn ($change, $field) => [
                    'field' => str_replace('_', ' ', preg_replace('/_id$/', '', $field)),
                    'from' => $change['from'] ?? null,
                    'to' => $change['to'] ?? null,
                ])
                ->values(),
            'user' => $log->user?->name,
            'when' => $log->created_at?->toIso8601String(),
            'when_human' => $log->created_at?->diffForHumans(),
        ]);

        return Inertia::render('Settings/Activity', [
            'entries' => $entries,
            'pagination' => $this->paginationMeta($paginator),
            'scope' => $all ? 'all' : 'mine',
            'can' => ['all' => $me->isAdmin()],
        ]);
    }
}
