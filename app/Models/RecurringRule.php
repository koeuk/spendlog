<?php

namespace App\Models;

use App\Enums\RecurringFrequency;
use App\Enums\RecurringKind;
use App\Models\Concerns\HasUuidRouteKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An expense or an income that repeats.
 *
 * A template, not a row: the rule holds no money of its own. RecurringRunner
 * materialises real expenses / incomes from it on schedule, so every existing
 * list, report and budget sees ordinary rows and nothing has to know about
 * recurrence. `next_run_on` is the cursor — the first occurrence still to be
 * written.
 */
class RecurringRule extends Model
{
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * Deliberately omits user_id — it is set from the authenticated user via
     * the recurringRules() relationship, never from request input.
     *
     * @var array
     */
    protected $fillable = [
        'kind',
        'category_id',
        'title',
        'amount',
        'frequency',
        'starts_on',
        'ends_on',
        'next_run_on',
        'last_run_on',
        'active',
        'note',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'id',
    ];

    protected function casts(): array
    {
        return [
            'kind' => RecurringKind::class,
            'frequency' => RecurringFrequency::class,
            'amount' => 'decimal:4',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'next_run_on' => 'immutable_date',
            'last_run_on' => 'immutable_date',
            'active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Rules with at least one occurrence still to be written. Hits the
     * (user_id, active, next_run_on) index, so the dashboard's check costs
     * nothing when there is nothing due.
     */
    public function scopeDue(Builder $query, ?CarbonImmutable $today = null): Builder
    {
        return $query
            ->where('active', true)
            ->where('next_run_on', '<=', ($today ?? CarbonImmutable::today())->toDateString());
    }

    /**
     * The first occurrence of this rule's schedule on or after $date.
     *
     * Walked from starts_on rather than computed, so the month-end clamp
     * behaves exactly as the runner's own stepping does. Used to reposition
     * the cursor when starts_on or frequency changes on an existing rule —
     * never backwards, so rows already written are not written again.
     */
    public function firstOccurrenceOnOrAfter(CarbonImmutable $date): CarbonImmutable
    {
        $occurrence = $this->starts_on;

        while ($occurrence->lessThan($date)) {
            $occurrence = $this->frequency->next($occurrence, $this->starts_on->day);
        }

        return $occurrence;
    }
}
