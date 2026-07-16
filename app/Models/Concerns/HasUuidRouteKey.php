<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Keeps the auto-incrementing integer primary key for foreign keys and joins,
 * while exposing a generated UUID as the public route key.
 *
 * Overriding uniqueIds() to a column other than the primary key is what stops
 * HasUuids from also switching the key type to string and disabling increments.
 */
trait HasUuidRouteKey
{
    use HasUuids;

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
