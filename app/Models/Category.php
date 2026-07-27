<?php

namespace App\Models;

use App\Enums\CategoryColor;
use App\Enums\CategoryIcon;
use App\Enums\Locale;
use App\Models\Concerns\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Category extends Model
{
    use HasFactory, HasTranslations, HasUuidRouteKey;

    /**
     * Reading $category->name returns the active locale's value, falling back
     * to the app fallback_locale when that locale is missing.
     *
     * @var array<int, string>
     */
    public array $translatable = ['name'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'color',
        'icon',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => CategoryColor::class,
            'icon' => CategoryIcon::class,
        ];
    }

    /**
     * Rows whose name matches, in any stored language.
     *
     * Categories are shared by everyone, so two rows for one real category is
     * not a cosmetic problem: the breakdown splits, the picker shows the name
     * twice, and each row can hold its own budget for the same month. The unique
     * index on (user, category, month) cannot see they are the same thing.
     *
     * Compared case-insensitively, and not with whereJsonContains: that compiles
     * to JSON_CONTAINS, which MySQL evaluates under a binary collation, so
     * "food" did not match "Food" here — while the inline category picker on the
     * expense form, which uses the LOWER() comparison below, considered them the
     * same name. That disagreement was enough to split one real category into
     * two rows with no merge tool and no way to delete either once either had
     * expenses, which is why this is one scope and not a query per caller.
     *
     * A name is only ever written under the fallback locale now, but a category
     * seeded with a Khmer translation is still the same category to whoever is
     * reading the list — so every locale is searched, not just that one.
     */
    public function scopeNamed(Builder $query, string $name): void
    {
        $name = mb_strtolower(trim($name));

        $query->where(function (Builder $query) use ($name) {
            foreach (Locale::cases() as $locale) {
                // The JSON path has to be a literal — MySQL will not take it as
                // a bound parameter. It comes from the Locale enum, never from
                // input.
                $query->orWhereRaw(
                    'LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, "$.'.$locale->value.'"))) = ?',
                    [$name],
                );
            }
        });
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }
}
