<?php

namespace App\Models;

use App\Models\Concerns\HasUuidRouteKey;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line in a borrowing's ledger: money paid back on a day. Always
 * positive — see the migration for why there is no sign.
 */
class BorrowingRepayment extends Model
{
    use HasFactory, HasUuidRouteKey, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * Omits user_id and borrowing_id — both come from the borrowing the
     * repayment is written through, never from request input.
     *
     * @var array
     */
    protected $fillable = [
        'amount',
        'paid_on',
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
            'paid_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function borrowing(): BelongsTo
    {
        return $this->belongsTo(Borrowing::class);
    }

    public function activityLabel(): string
    {
        return $this->borrowing->lender.' · $'.number_format((float) $this->amount, 2);
    }
}
