<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Writes an ActivityLog line whenever the model is created, changed or
 * deleted by a signed-in person. Hangs off model events, so the web forms and
 * the API are covered alike without either knowing.
 *
 * Nothing is logged without an actor: seeders, console commands and cascade
 * deletes have no one to attribute to, and a line that says "somebody" is
 * noise.
 *
 * The using model supplies activityLabel(); it may override activityTracked()
 * to narrow which attributes count as a change, and activityRelated() to
 * render a foreign key (a category id, say) as something a person can read.
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn (Model $model) => $model->recordActivity(ActivityLog::CREATED));

        static::updated(function (Model $model) {
            $changes = $model->activityChanges();

            // A save that touched only timestamps is not something anyone did.
            if ($changes !== []) {
                $model->recordActivity(ActivityLog::UPDATED, $changes);
            }
        });

        static::deleted(fn (Model $model) => $model->recordActivity(ActivityLog::DELETED));
    }

    /** One line, frozen now: what a person would call this record. */
    abstract public function activityLabel(): string;

    /**
     * The attributes worth diffing. Fillable by default — that is the set a
     * form can change.
     *
     * @return array<int, string>
     */
    public function activityTracked(): array
    {
        return $this->getFillable();
    }

    /**
     * How a value reads in the log. Dates lose their midnight, and models
     * override this to turn foreign keys into names.
     */
    public function activityValue(string $attribute, mixed $value): mixed
    {
        $related = $this->activityRelated($attribute, $value);

        if ($related !== null) {
            return $related;
        }

        // Enum casts (a category's colour, say) come back as objects; the
        // log stores what the column does.
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2} 00:00:00$/', $value)) {
            return substr($value, 0, 10);
        }

        // Translatable columns are stored as JSON per locale; show the one in
        // use, matching what the form displayed.
        if (property_exists($this, 'translatable') && in_array($attribute, $this->translatable, true)) {
            $decoded = is_string($value) ? json_decode($value, true) : $value;

            if (is_array($decoded)) {
                return $decoded[app()->getLocale()] ?? $decoded[config('app.fallback_locale')] ?? reset($decoded);
            }
        }

        return $value;
    }

    /**
     * A model's chance to name what a foreign key points at ("Food" for a
     * category_id). Null falls through to the default rendering.
     */
    protected function activityRelated(string $attribute, mixed $value): mixed
    {
        return null;
    }

    /**
     * @return array<string, array{from: mixed, to: mixed}>
     */
    protected function activityChanges(): array
    {
        $changes = [];

        foreach ($this->activityTracked() as $attribute) {
            if (! array_key_exists($attribute, $this->getChanges())) {
                continue;
            }

            $from = $this->activityValue($attribute, $this->getOriginal($attribute));
            $to = $this->activityValue($attribute, $this->getAttribute($attribute));

            if ((string) $from === (string) $to) {
                continue;
            }

            $changes[$attribute] = ['from' => $from, 'to' => $to];
        }

        return $changes;
    }

    /**
     * @param  array<string, array{from: mixed, to: mixed}>|null  $changes
     */
    protected function recordActivity(string $action, ?array $changes = null): void
    {
        $actor = Auth::user();

        if ($actor === null) {
            return;
        }

        ActivityLog::create([
            'user_id' => $actor->getKey(),
            'action' => $action,
            'subject_type' => static::class,
            'subject_id' => $this->getKey(),
            'subject_label' => $this->activityLabel(),
            'changes' => $changes,
        ]);
    }
}
