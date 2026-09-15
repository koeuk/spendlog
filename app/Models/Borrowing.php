<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\LenderType;
use App\Models\Concerns\HasUuidRouteKey;
use App\Models\Concerns\LogsActivity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Money borrowed from someone — a friend, family, a bank — with a ledger of
 * repayments against it.
 *
 * The row holds what was borrowed and never what is still owed: that is
 * always amount − SUM(repayments), so the figure cannot drift from the
 * ledger. A borrowing is *settled* once that difference reaches zero, and
 * *overdue* while it has not and its due date has passed.
 */
class Borrowing extends Model
{
    use HasFactory, HasUuidRouteKey, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * Deliberately omits user_id — it is set from the authenticated user via
     * the borrowings() relationship, never from request input.
     *
     * @var array
     */
    protected $fillable = [
        'lender',
        'lender_type',
        'amount',
        'borrowed_on',
        'due_on',
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
            'lender_type' => LenderType::class,
            'amount' => 'decimal:4',
            'borrowed_on' => 'date',
            'due_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(BorrowingRepayment::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Attach the ledger total as `repaid_total`, so a list pays one query
     * rather than one per row. repaid() reads it back.
     */
    public function scopeWithRepaid(Builder $query): Builder
    {
        return $query->withSum('repayments as repaid_total', 'amount');
    }

    /** Still owing something. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereRaw(self::remainingSql().' > 0');
    }

    /** Paid back in full. */
    public function scopeSettled(Builder $query): Builder
    {
        return $query->whereRaw(self::remainingSql().' <= 0');
    }

    /**
     * What is still owed, in SQL, for filtering and ordering a list without
     * loading every row. Mirrors remaining() below.
     */
    public static function remainingSql(): string
    {
        return '(borrowings.amount - COALESCE((SELECT SUM(r.amount) FROM borrowing_repayments r WHERE r.borrowing_id = borrowings.id), 0))';
    }

    /**
     * What has been paid back so far.
     *
     * Reads the withRepaid() column when the query attached it, the loaded
     * ledger when show() eager-loaded it, and only then asks the database.
     */
    public function repaid(): float
    {
        if (array_key_exists('repaid_total', $this->attributes)) {
            return round((float) $this->attributes['repaid_total'], Currency::SCALE);
        }

        if ($this->relationLoaded('repayments')) {
            return round((float) $this->repayments->sum('amount'), Currency::SCALE);
        }

        return round((float) $this->repayments()->sum('amount'), Currency::SCALE);
    }

    /** What is still owed. Never below zero: a repayment is capped at this. */
    public function remaining(): float
    {
        return max(round((float) $this->amount - $this->repaid(), Currency::SCALE), 0.0);
    }

    /** How much of it has been paid back, 0–100. */
    public function percentRepaid(): int
    {
        $amount = (float) $this->amount;

        if ($amount <= 0) {
            return 100;
        }

        return (int) min(round(($this->repaid() / $amount) * 100), 100);
    }

    public function isSettled(): bool
    {
        return $this->remaining() <= 0;
    }

    public function isOverdue(): bool
    {
        return ! $this->isSettled()
            && $this->due_on !== null
            && $this->due_on->lt(CarbonImmutable::today());
    }

    public function activityLabel(): string
    {
        return $this->lender.' · $'.number_format((float) $this->amount, 2);
    }
}
