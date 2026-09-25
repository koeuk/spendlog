<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Concerns\PaginatesLists;
use Carbon\CarbonImmutable;
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
     * @queryParam user string One person's uuid, to see only what they did. Admin-only, and implies `scope=all`; an account may always name itself. Example: 0198b...
     * @queryParam from string The earliest moment to include. A date (`2026-09-01`) starts at that day's midnight; a date-time is used as given. Example: 2026-09-01
     * @queryParam to string The latest moment to include. A date runs to the end of that day; a date-time is used as given. Example: 2026-09-25T18:00
     * @queryParam page integer Example: 1
     * @queryParam per_page integer One of 20, 50, 100, 150, 200. Example: 20
     *
     * @response 200 {"data": [{"uuid": "0198a...", "action": "updated", "subject": "expense", "label": "Lunch · $3.00", "changes": {"price": {"from": "2.5000", "to": "3.0000"}}, "user": {"uuid": "0198b...", "name": "Koeuk"}, "created_at": "2026-09-09T10:00:00+07:00"}], "links": {"next": null}, "meta": {"current_page": 1}}
     * @response 403 scenario="scope=all, or another person's uuid, without being an admin" {"message": "Only an admin can see everyone's activity."}
     * @response 422 scenario="a kind that does not exist" {"message": "Unknown activity subject: plans.", "errors": {"subject": ["Unknown activity subject: plans."]}}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'user' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $me = $request->user();
        $person = $this->person($validated['user'] ?? null);
        $all = $request->query('scope') === 'all' || $person !== null;

        // Naming yourself is just "mine"; naming anyone else is looking at
        // someone else's history, which is the admin's view.
        if ($all && ! ($person?->is($me) ?? false) && ! $me->isAdmin()) {
            throw new HttpException(403, "Only an admin can see everyone's activity.");
        }

        $kinds = $this->kinds($request);
        [$from, $to] = $this->window($validated['from'] ?? null, $validated['to'] ?? null);

        $logs = ActivityLog::query()
            ->with('user')
            ->when($person !== null, fn ($query) => $query->where('user_id', $person->id))
            ->when(! $all, fn ($query) => $query->where('user_id', $me->id))
            ->when($kinds !== [], fn ($query) => $query->ofKind($kinds))
            ->when($from !== null, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to !== null, fn ($query) => $query->where('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return ActivityLogResource::collection($logs);
    }

    /**
     * The `user` filter: an account by uuid, or null when none was asked for.
     * An unknown uuid is a 422, for the same reason an unknown kind is.
     */
    private function person(?string $uuid): ?User
    {
        if ($uuid === null || trim($uuid) === '') {
            return null;
        }

        return User::query()->where('uuid', trim($uuid))->first()
            ?? throw ValidationException::withMessages(['user' => __('No such person.')]);
    }

    /**
     * The `from` / `to` window, in the app's timezone.
     *
     * A bare date means the whole day: "from the 1st" starts at its midnight
     * and "to the 25th" runs through its last second, which is what anyone
     * picking two days on a calendar means. A date-time is taken as given.
     *
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function window(?string $from, ?string $to): array
    {
        $parse = function (?string $value, bool $end): ?CarbonImmutable {
            if ($value === null || trim($value) === '') {
                return null;
            }
            $moment = CarbonImmutable::parse($value, config('app.timezone'));
            $dateOnly = preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) === 1;

            return $dateOnly ? ($end ? $moment->endOfDay() : $moment->startOfDay()) : $moment;
        };

        $start = $parse($from, false);
        $finish = $parse($to, true);

        if ($start !== null && $finish !== null && $finish->lt($start)) {
            throw ValidationException::withMessages(['to' => __('The end cannot come before the start.')]);
        }

        return [$start, $finish];
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
