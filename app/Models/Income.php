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
 * Money coming in on one day — the counterpart to Expense.
 *
 * Deliberately simpler than an expense: no shared category, just a free-text
 * source, because where income comes from is personal and the summary groups
 * on the string as typed.
 */
class Income extends Model
{
    use HasFactory, HasUuidRouteKey, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * Deliberately omits user_id — it is set from the authenticated user via
     * the incomes() relationship, never from request input.
     *
     * @var array
     */
    protected $fillable = [
        'source',
        'amount',
        'received_on',
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
            'amount' => 'decimal:4',
            'received_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The rule that wrote this row, when one did — see Expense::recurringRule(). */
    public function recurringRule(): BelongsTo
    {
        return $this->belongsTo(RecurringRule::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /** Mirrors Expense::scopeInMonth so the dashboard filters both the same way. */
    public function scopeInMonth(Builder $query, string $month): Builder
    {
        $date = CarbonImmutable::parse($month);

        return $query->whereBetween('received_on', [
            $date->startOfMonth()->toDateString(),
            $date->endOfMonth()->toDateString(),
        ]);
    }

    public function activityLabel(): string
    {
        return $this->source.' · $'.number_format((float) $this->amount, 2);
    }
}
