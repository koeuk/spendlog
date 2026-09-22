<?php

namespace App\Models;

use App\Models\Concerns\HasUuidRouteKey;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One name an account is offered when it logs income, or a deposit into
 * savings: "Salary", "Freelance", "Gift".
 *
 * A catalogue of suggestions, not a foreign key. `incomes.source` stays free
 * text, so a row here can be renamed or removed without rewriting or losing
 * the income that used the name — and a name typed straight into an income
 * still works when it was never curated. See the migration.
 */
class IncomeSource extends Model
{
    use HasFactory, HasUuidRouteKey, LogsActivity;

    /**
     * Omits user_id — it comes from the authenticated user via the
     * incomeSources() relationship, never from request input.
     *
     * @var array
     */
    protected $fillable = [
        'name',
    ];

    /**
     * @var array
     */
    protected $hidden = [
        'id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Add a name to an account's catalogue without disturbing anything.
     *
     * Deliberately a plain insert rather than a model save: this runs as a
     * side effect of logging income, and a person who typed "Gift" into an
     * amount form did not ask to create a source. An activity line saying they
     * did would be noise in a log that is meant to read as a list of things
     * someone chose to do.
     */
    public static function remember(int $userId, ?string $name): void
    {
        $name = trim((string) $name);

        if ($name === '') {
            return;
        }

        static::query()->insertOrIgnore([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function activityLabel(): string
    {
        return __('Income source').' · '.$this->name;
    }
}
