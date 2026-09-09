<?php

namespace App\Models;

use App\Models\Concerns\HasUuidRouteKey;
use App\Models\Concerns\LogsActivity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How much a person intends to put aside in one month.
 *
 * The savings counterpart to Budget, down to the upsert on (user, month): a
 * plan is a number for a month, not a thing with a lifecycle. What was
 * actually saved is never stored here — it is SUM(savings_entries.amount), so
 * the two can never drift. The maths lives in App\Services\SavingsSummary.
 */
class SavingsPlan extends Model
{
    use HasFactory, HasUuidRouteKey, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * Deliberately omits user_id — it is set from the authenticated user via
     * the savingsPlans() relationship, never from request input.
     *
     * @var array
     */
    protected $fillable = [
        'month',
        'amount',
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
            'month' => 'date',
            'amount' => 'decimal:4',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForMonth(Builder $query, string $month): Builder
    {
        return $query->whereDate('month', CarbonImmutable::parse($month)->startOfMonth());
    }

    public function activityLabel(): string
    {
        return __('Savings').' · '.$this->month?->format('M Y').' · $'.number_format((float) $this->amount, 2);
    }
}
