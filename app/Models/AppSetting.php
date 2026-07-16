<?php

namespace App\Models;

use App\Support\Color;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Branding for the whole app, stored as one row.
 *
 * Read on every request (the layout and the <head> both need it), so current()
 * caches — and any write clears that cache via the saved() hook below.
 */
class AppSetting extends Model
{
    private const CACHE_KEY = 'app_settings.current';

    /**
     * Doubles as a sentinel for "no brand colour chosen" — see cssVariables().
     * Must match the column default in the migration.
     */
    public const DEFAULT_BUTTON_COLOR = '#171717';

    /**
     * Also a sentinel: at the default the page keeps its ambient wash, and any
     * other colour renders flat. See plainBackground().
     */
    public const DEFAULT_BODY_COLOR = '#ffffff';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'app_name',
        'logo_path',
        'favicon_path',
        'button_color',
        'body_color',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'id',
    ];

    protected static function booted(): void
    {
        // Covers update() and delete() alike, so no caller has to remember.
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * The one row, creating it on first use so a fresh install has no empty state.
     *
     * Only the raw attributes go in the cache, never the model itself. Laravel's
     * `cache.serializable_classes` defaults to false, so the store unserializes
     * with `allowed_classes: false` — a cached object comes back as
     * __PHP_Incomplete_Class, which this method's return type then turns into a
     * TypeError on every request. Caching scalars keeps that protection intact.
     */
    public static function current(): self
    {
        $attributes = Cache::rememberForever(self::CACHE_KEY, self::readRow(...));

        // A cache written before a migration added a column rehydrates a model
        // silently missing it, and the first read of that attribute returns null
        // — which surfaces somewhere far away as a type error, not as "your cache
        // is stale". Cheaper to notice here and rebuild once.
        if (self::isStale($attributes)) {
            Cache::forget(self::CACHE_KEY);
            $attributes = Cache::rememberForever(self::CACHE_KEY, self::readRow(...));
        }

        // newFromBuilder, not new: this row exists, so it must not be marked
        // dirty or re-inserted on a later save.
        return (new static)->newFromBuilder($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    private static function readRow(): array
    {
        return static::query()
            ->firstOrCreate([], ['app_name' => config('app.name', 'SpendLog')])
            ->getRawOriginal();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function isStale(array $attributes): bool
    {
        foreach ((new static)->getFillable() as $column) {
            if (! array_key_exists($column, $attributes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The design tokens this row overrides, as bare HSL triplets ready to drop
     * into a CSS custom property.
     *
     * `primary` is null while the button colour is still the default, and the
     * page then leaves the token alone. That matters: app.css defines --primary
     * as a *theme-aware pair* — near-black in light, near-white in dark — and
     * the default #171717 is only the light half of it. Forcing that half into
     * both themes puts a #171717 button on a #0a0a0a page: 1.1:1, invisible.
     *
     * A real brand colour is a single value and does belong in both themes, so
     * once an admin picks one it applies to each. The default is the one value
     * that cannot, which is exactly why it means "leave the stock tokens alone".
     *
     * `background` is light-mode only, for the mirror reason: an admin picking
     * Cream should not be able to switch dark mode off for everyone.
     *
     * @return array{primary: string|null, primaryForeground: string|null, background: string}
     */
    public function cssVariables(): array
    {
        $branded = $this->button_color !== self::DEFAULT_BUTTON_COLOR;

        return [
            'primary' => $branded ? Color::toHslTriplet($this->button_color) : null,
            // Computed, never chosen: a free colour picker otherwise lets an
            // admin put white text on a pale button and lose the label.
            'primaryForeground' => $branded
                ? Color::readableForegroundTriplet($this->button_color)
                : null,
            'background' => Color::toHslTriplet($this->body_color),
        ];
    }

    /**
     * Whether the page should render its background flat.
     *
     * The ambient wash is three big blurred colour circles laid over the page.
     * It is what the default look is built on — but it sits *on top* of the
     * background, so a chosen colour comes through tinted and gradient-y rather
     * than as the colour that was picked. Choosing a colour therefore turns the
     * wash off: you get the colour you chose, flat.
     */
    public function plainBackground(): bool
    {
        return $this->body_color !== self::DEFAULT_BODY_COLOR;
    }

    /** Null when unset — the frontend falls back to the built-in wordmark. */
    public function logoUrl(): ?string
    {
        return $this->fileUrl($this->logo_path);
    }

    public function faviconUrl(): ?string
    {
        return $this->fileUrl($this->favicon_path);
    }

    private function fileUrl(?string $path): ?string
    {
        // Guard the file's existence: a path pointing at a deleted file would
        // render a broken image rather than falling back cleanly.
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        // Cache-busted, or browsers keep showing the old logo after a change.
        return Storage::disk('public')->url($path).'?v='.$this->updated_at?->timestamp;
    }
}
