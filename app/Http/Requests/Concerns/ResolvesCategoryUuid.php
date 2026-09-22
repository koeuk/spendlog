<?php

namespace App\Http\Requests\Concerns;

use App\Models\Category;
use Illuminate\Validation\ValidationException;

/**
 * Swap a category UUID for the internal foreign key. The frontend only ever
 * sees UUIDs; the column is an integer id.
 */
trait ResolvesCategoryUuid
{
    /**
     * The uuid passed `exists` a moment ago, but that was a separate query and
     * the row can be gone by now. (int) null is 0, which is no category at all:
     * the API surfaced that as a 500 from the foreign key where its own
     * docblock promises a 422, and the web form turned it into a flash message
     * built from the SQL error. So the miss is raised as validation, here, once
     * — rather than in each request that resolves a category.
     */
    protected function resolveCategoryId(string $uuid): int
    {
        $id = Category::query()->where('uuid', $uuid)->value('id');

        if ($id === null) {
            throw ValidationException::withMessages([
                'category_uuid' => __('That category no longer exists.'),
            ]);
        }

        return (int) $id;
    }
}
