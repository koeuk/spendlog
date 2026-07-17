<?php

namespace App\Models;

use App\Models\Concerns\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * An editable footer page — About, Privacy Policy. A fixed set seeded by the
 * migration; the admin edits the copy but never creates or deletes rows.
 */
class Page extends Model
{
    use HasTranslations, HasUuidRouteKey;

    /**
     * @var array<int, string>
     */
    public array $translatable = ['title', 'body'];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'body',
        'published',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published' => 'boolean',
        ];
    }

    /**
     * Public pages are addressed by their stable slug, not the uuid — the footer
     * links /p/about, not a random id.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
