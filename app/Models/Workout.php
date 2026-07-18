<?php

namespace App\Models;

use App\Models\Concerns\HasUuidRouteKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One training session on one day, holding the sets performed in it.
 *
 * The exercise-module counterpart to Expense: user-owned, dated, and the row
 * every dashboard series is built from.
 */
class Workout extends Model
{
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * Deliberately omits user_id — it is set from the authenticated user via
     * the workouts() relationship, never from request input.
     *
     * @var array
     */
    protected $fillable = [
        'performed_on',
        'duration_seconds',
        'notes',
    ];

    /**
     * @var array
     */
    protected $hidden = [
        'id',
    ];

    protected function casts(): array
    {
        return [
            'performed_on' => 'date',
            'duration_seconds' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sets(): HasMany
    {
        return $this->hasMany(WorkoutSet::class)->orderBy('set_no');
    }

    /**
     * Total load moved in this session, in kilograms.
     *
     * Σ reps × weight for the strength sets. Cardio sets contribute nothing —
     * they have no weight — which is why the dashboard shows volume and time as
     * two figures rather than trying to reconcile them into one.
     *
     * Reads from the loaded collection rather than querying, so callers that
     * eager-load sets pay one query for a whole page of workouts.
     */
    public function volumeKg(): float
    {
        return (float) $this->sets
            ->filter(fn (WorkoutSet $set) => $set->reps !== null && $set->weight_kg !== null)
            ->sum(fn (WorkoutSet $set) => $set->reps * (float) $set->weight_kg);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /** Mirrors Expense::scopeInMonth so both dashboards filter the same way. */
    public function scopeInMonth(Builder $query, string $month): Builder
    {
        $date = CarbonImmutable::parse($month);

        return $query->whereBetween('performed_on', [
            $date->startOfMonth()->toDateString(),
            $date->endOfMonth()->toDateString(),
        ]);
    }
}
