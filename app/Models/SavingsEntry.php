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
 * One line in a person's savings ledger. `amount` is signed: positive is a
 * deposit, negative a withdrawal — see the migration for why there is no type
 * column.
 *
 * An entry belongs to a person, not to a plan. The month's plan is a separate
 * row that says what was intended; nothing about the money depends on one
 * existing, which is why an entry survived the goals being dropped.
 */
class SavingsEntry extends Model
{
    use HasFactory, HasUuidRouteKey, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * Omits user_id — it comes from the authenticated user via the
     * savingsEntries() relationship, never from request input.
     *
     * @var array
     */
    protected $fillable = [
        'amount',
        'saved_on',
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
            'saved_on' => 'date',
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

    /** Everything saved or taken out inside one calendar month. */
    public function scopeInMonth(Builder $query, string $month): Builder
    {
        $start = CarbonImmutable::parse($month)->startOfMonth();

        return $query->whereBetween('saved_on', [
            $start->toDateString(),
            $start->endOfMonth()->toDateString(),
        ]);
    }

    public function isWithdrawal(): bool
    {
        return (float) $this->amount < 0;
    }

    public function activityLabel(): string
    {
        $sign = $this->isWithdrawal() ? '-' : '+';

        return __('Savings').' · '.$sign.'$'.number_format(abs((float) $this->amount), 2);
    }
}
