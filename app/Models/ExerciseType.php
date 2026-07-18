<?php

namespace App\Models;

use App\Enums\CategoryColor;
use App\Enums\ExerciseIcon;
use App\Enums\MuscleGroup;
use App\Models\Concerns\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * A movement that can be performed — "Bench Press", "Running".
 *
 * The catalogue, not the log. A workout_set points at one of these; the type
 * itself carries no history.
 */
class ExerciseType extends Model
{
    use HasFactory, HasTranslations, HasUuidRouteKey;

    /**
     * Reading $type->name returns the active locale's value, falling back to the
     * app fallback_locale when that locale is missing. Mirrors Category::$name.
     *
     * @var array<int, string>
     */
    public array $translatable = ['name'];

    /**
     * The attributes that are mass assignable.
     *
     * Deliberately omits user_id — it is set from the authenticated user via the
     * exerciseTypes() relationship, never from request input, so no one can
     * forge a type onto somebody else or promote their own to a global.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'muscle_group',
        'is_cardio',
        'color',
        'icon',
    ];

    /**
     * @var array
     */
    protected $hidden = [
        'id',
    ];

    protected function casts(): array
    {
        return [
            'muscle_group' => MuscleGroup::class,
            'is_cardio' => 'boolean',
            'color' => CategoryColor::class,
            'icon' => ExerciseIcon::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sets(): HasMany
    {
        return $this->hasMany(WorkoutSet::class);
    }

    /** A seeded movement everyone can log, as opposed to one someone invented. */
    public function isGlobal(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Everything this person may log against: the shared defaults plus their own.
     *
     * Note the parenthesised group. Without it the OR would escape any WHERE
     * this scope is chained onto and widen the result set — the same trap the
     * expense controllers avoid by applying ownership last.
     */
    public function scopeAvailableTo(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $query) use ($userId) {
            $query->whereNull('user_id')->orWhere('user_id', $userId);
        });
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
