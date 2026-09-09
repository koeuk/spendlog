<?php

namespace App\Models;

use App\Models\Concerns\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One line of who-did-what: an account created, changed or removed one of
 * its records. Written by the LogsActivity trait, never by hand, and never
 * updated — a log that can be edited is not a log.
 */
class ActivityLog extends Model
{
    use HasUuidRouteKey;

    public const UPDATED_AT = null;

    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const DELETED = 'deleted';

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'subject_label',
        'changes',
    ];

    protected $hidden = ['id', 'user_id', 'subject_id'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The subject's short, stable name for clients: "expense", "savings_plan".
     * The class name is an implementation detail that a rename would break.
     */
    public function subjectKind(): string
    {
        return str(class_basename($this->subject_type))->snake()->toString();
    }
}
