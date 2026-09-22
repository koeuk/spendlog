<?php

namespace App\Models;

use App\Models\Concerns\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * The kinds a client may filter by, mapped to the classes stored in
     * `subject_type`. Every model that uses LogsActivity appears here; the
     * keys are what subjectKind() emits, so a filter and a response row always
     * speak the same word.
     *
     * @var array<string, class-string<Model>>
     */
    public const SUBJECTS = [
        'expense' => Expense::class,
        'income' => Income::class,
        'income_source' => IncomeSource::class,
        'budget' => Budget::class,
        'category' => Category::class,
        'savings_plan' => SavingsPlan::class,
        'savings_entry' => SavingsEntry::class,
        'borrowing' => Borrowing::class,
        'borrowing_repayment' => BorrowingRepayment::class,
    ];

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
     * Narrow to one or more subject kinds, named the way the API names them.
     *
     * Unknown kinds are dropped rather than trusted into the query, so a
     * client cannot ask about a class this log was never meant to expose.
     *
     * @param  array<int, string>  $kinds
     */
    public function scopeOfKind(Builder $query, array $kinds): Builder
    {
        $types = array_values(array_intersect_key(
            self::SUBJECTS,
            array_flip($kinds),
        ));

        return $query->whereIn('subject_type', $types);
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
