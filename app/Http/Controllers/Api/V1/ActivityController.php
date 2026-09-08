<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Support\Concerns\PaginatesLists;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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
     * @queryParam page integer Example: 1
     * @queryParam per_page integer One of 20, 50, 100, 150, 200. Example: 20
     *
     * @response 200 {"data": [{"uuid": "0198a...", "action": "updated", "subject": "expense", "label": "Lunch · $3.00", "changes": {"price": {"from": "2.5000", "to": "3.0000"}}, "user": {"uuid": "0198b...", "name": "Koeuk"}, "created_at": "2026-09-09T10:00:00+07:00"}], "links": {"next": null}, "meta": {"current_page": 1}}
     * @response 403 scenario="scope=all without being an admin" {"message": "Only an admin can see everyone's activity."}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $all = $request->query('scope') === 'all';

        if ($all && ! $request->user()->isAdmin()) {
            throw new HttpException(403, "Only an admin can see everyone's activity.");
        }

        $logs = ActivityLog::query()
            ->with('user')
            ->when(! $all, fn ($query) => $query->where('user_id', $request->user()->id))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return ActivityLogResource::collection($logs);
    }
}
