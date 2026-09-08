<?php

namespace App\Models;

use App\Models\Concerns\HasUuidRouteKey;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line in a goal's ledger. `amount` is signed: positive is a deposit,
 * negative a withdrawal — see the migration for why there is no type column.
 */
class SavingsEntry extends Model
{
    use HasFactory, HasUuidRouteKey, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * Omits user_id and savings_goal_id — both come from the goal the entry is
     * written through, never from request input.
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

    public function goal(): BelongsTo
    {
        return $this->belongsTo(SavingsGoal::class, 'savings_goal_id');
    }

    public function isWithdrawal(): bool
    {
        return (float) $this->amount < 0;
    }

    public function activityLabel(): string
    {
        $sign = $this->isWithdrawal() ? '-' : '+';

        return ($this->goal?->name ?? 'Savings').' · '.$sign.'$'.number_format(abs((float) $this->amount), 2);
    }
}
