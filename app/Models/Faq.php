<?php

namespace App\Models;

use App\Enums\FaqStatus;
use App\Models\Concerns\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Faq extends Model
{
    /** @use HasFactory<\Database\Factories\FaqFactory> */
    use HasFactory, HasTranslations, HasUuidRouteKey;

    /**
     * Reading $faq->question returns the active locale's value, falling back to
     * the app fallback_locale when that locale is missing — same as Category.
     *
     * @var array<int, string>
     */
    public array $translatable = ['question', 'answer'];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'question',
        'answer',
        'status',
        'position',
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
            'status' => FaqStatus::class,
        ];
    }

    /**
     * The help page's rows: published only, in the admin's hand-set order.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', FaqStatus::Published->value)->orderBy('position');
    }
}
