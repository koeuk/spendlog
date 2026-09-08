<?php

namespace App\Models;

use App\Models\Concerns\HasUuidRouteKey;
use App\Models\Concerns\LogsActivity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class Expense extends Model
{
    use HasFactory, HasTranslations, HasUuidRouteKey, LogsActivity;

    /**
     * Reading $expense->item returns the active locale's value, falling back to
     * the app fallback_locale when that locale is missing. Mirrors Category::$name.
     *
     * @var array<int, string>
     */
    public array $translatable = ['item'];

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
            'price' => 'decimal:4',
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

    /**
     * The rule that wrote this row, when one did. Not fillable: a client
     * cannot claim a row is recurring, only RecurringRunner sets it.
     */
    public function recurringRule(): BelongsTo
    {
        return $this->belongsTo(RecurringRule::class);
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

    public function activityLabel(): string
    {
        return $this->item.' · $'.number_format((float) $this->price, 2);
    }

    protected function activityRelated(string $attribute, mixed $value): mixed
    {
        return $attribute === 'category_id' ? Category::find($value)?->name : null;
    }
}
