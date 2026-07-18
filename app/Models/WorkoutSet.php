<?php

namespace App\Models;

use App\Models\Concerns\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line inside a session: "set 3, 8 reps at 60 kg" or "5 km in 28 minutes".
 *
 * Which half of the columns is filled follows the exercise type's is_cardio
 * flag. Both halves are nullable, so bodyweight work (reps, no weight) and an
 * untimed walk (distance, no duration) are both representable.
 */
class WorkoutSet extends Model
{
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * workout_id is absent for the same reason user_id is absent on Workout:
     * sets are written through the workout's sets() relationship, so a request
     * cannot graft a set onto somebody else's session.
     *
     * @var array
     */
    protected $fillable = [
        'exercise_type_id',
        'set_no',
        'reps',
        'weight_kg',
        'distance_m',
        'duration_seconds',
        'rpe',
    ];

    /**
     * @var array
     */
    protected $hidden = [
        'id',
    ];

    protected function casts(): array
    {
        return [
            'set_no' => 'integer',
            'reps' => 'integer',
            'weight_kg' => 'decimal:3',
            'distance_m' => 'integer',
            'duration_seconds' => 'integer',
            'rpe' => 'integer',
        ];
    }

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }

    public function exerciseType(): BelongsTo
    {
        return $this->belongsTo(ExerciseType::class);
    }

    /** Load moved by this set, in kilograms. Zero for anything without both halves. */
    public function volumeKg(): float
    {
        if ($this->reps === null || $this->weight_kg === null) {
            return 0.0;
        }

        return $this->reps * (float) $this->weight_kg;
    }
}
