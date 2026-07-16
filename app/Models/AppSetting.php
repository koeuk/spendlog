<?php

namespace App\Models;

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
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'app_name',
        'logo_path',
        'favicon_path',
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
     */
    public static function current(): self
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => static::query()->firstOrCreate([], ['app_name' => config('app.name', 'SpendLog')]),
        );
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
