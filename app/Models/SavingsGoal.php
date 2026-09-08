<?php

namespace App\Models;

use App\Enums\CategoryColor;
use App\Models\Concerns\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something being saved towards, with a ledger of deposits and withdrawals.
 *
 * The goal itself holds no running balance: what is saved is always
 * SUM(entries.amount), so the figure cannot drift from the ledger. The maths
 * around it (percent, remaining, reached) lives in App\Services\SavingsSummary.
 */
class SavingsGoal extends Model
{
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * Deliberately omits user_id — it is set from the authenticated user via
     * the savingsGoals() relationship, never from request input.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'target_amount',
        'deadline',
        'color',
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
            'target_amount' => 'decimal:4',
            'deadline' => 'date',
            'color' => CategoryColor::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(SavingsEntry::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Attach the ledger total as `saved_total`, so a list of goals pays one
     * query rather than one per row. SavingsSummary::saved() reads it back.
     */
    public function scopeWithSaved(Builder $query): Builder
    {
        return $query->withSum('entries as saved_total', 'amount');
    }
}
