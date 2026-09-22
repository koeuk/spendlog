<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Support\Concerns\PaginatesLists;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @group Activity
 *
 * The activity log: every create, change and delete an account has made,
 * newest first. Your own is open to any signed-in token — it is a record of
 * what you did, not a capability. Everyone's needs an admin.
 *
 * @authenticated
 */
class ActivityController extends Controller
{
    use PaginatesLists;

    /**
     * List activity
     *
     * @queryParam scope string `mine` (default) or `all`. `all` is admin-only. Example: mine
     * @queryParam subject string Comma-separated kinds to narrow to: `expense`, `income`, `budget`, `category`, `savings_plan`, `savings_entry`, `borrowing`, `borrowing_repayment`. Unknown kinds are a 422 rather than a silent empty list. Example: savings_plan,savings_entry
     * @queryParam page integer Example: 1
     * @queryParam per_page integer One of 20, 50, 100, 150, 200. Example: 20
     *
     * @response 200 {"data": [{"uuid": "0198a...", "action": "updated", "subject": "expense", "label": "Lunch · $3.00", "changes": {"price": {"from": "2.5000", "to": "3.0000"}}, "user": {"uuid": "0198b...", "name": "Koeuk"}, "created_at": "2026-09-09T10:00:00+07:00"}], "links": {"next": null}, "meta": {"current_page": 1}}
     * @response 403 scenario="scope=all without being an admin" {"message": "Only an admin can see everyone's activity."}
     * @response 422 scenario="a kind that does not exist" {"message": "Unknown activity subject: plans.", "errors": {"subject": ["Unknown activity subject: plans."]}}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $all = $request->query('scope') === 'all';

        if ($all && ! $request->user()->isAdmin()) {
            throw new HttpException(403, "Only an admin can see everyone's activity.");
        }

        $kinds = $this->kinds($request);

        $logs = ActivityLog::query()
            ->with('user')
            ->when(! $all, fn ($query) => $query->where('user_id', $request->user()->id))
            ->when($kinds !== [], fn ($query) => $query->ofKind($kinds))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return ActivityLogResource::collection($logs);
    }

    /**
     * The `subject` filter, split and checked against what the log knows.
     *
     * A typo is a 422 rather than an empty list: "no activity yet" and "you
     * asked for something that does not exist" are different answers, and a
     * client that cannot tell them apart shows the wrong empty state.
     *
     * @return array<int, string>
     */
    private function kinds(Request $request): array
    {
        $raw = trim((string) $request->query('subject'));

        if ($raw === '') {
            return [];
        }

        $kinds = array_values(array_filter(array_map('trim', explode(',', $raw))));
        $unknown = array_diff($kinds, array_keys(ActivityLog::SUBJECTS));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'subject' => __('Unknown activity subject: :kinds.', [
                    'kinds' => implode(', ', $unknown),
                ]),
            ]);
        }

        return $kinds;
    }
}
