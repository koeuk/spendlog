<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Concerns\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * Deliberately omits user_id — it is set from the authenticated user via
     * the expenses() relationship, never from request input.
     *
     * @var array
     */
    protected $fillable = [
        'category_id',
        'item',
        'price',
        'spent_on',
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
            'price' => 'decimal:2',
            'spent_on' => 'date',
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

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeInMonth(Builder $query, string $month): Builder
    {
        $date = CarbonImmutable::parse($month);

        return $query->whereBetween('spent_on', [
            $date->startOfMonth()->toDateString(),
            $date->endOfMonth()->toDateString(),
        ]);
    }
}
