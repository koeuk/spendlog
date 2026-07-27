<?php

namespace App\Http\Requests;

use App\Enums\CategoryColor;
use App\Enums\ExerciseIcon;
use App\Enums\MuscleGroup;
use App\Models\ExerciseType;
use App\Support\TranslatableInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExerciseTypeRequest extends FormRequest
{
    /**
     * Authorization is handled by ExerciseTypePolicy via the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * One Name box, not one per language — see TranslatableInput. The value is
     * stored under the fallback locale.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['name' => TranslatableInput::toString($this->input('name'))]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($this->nameIsTaken((string) $value)) {
                        $fail(__('You already have an exercise called ":name".', ['name' => trim((string) $value)]));
                    }
                },
            ],
            'muscle_group' => ['required', Rule::enum(MuscleGroup::class)],
            'is_cardio' => ['nullable', 'boolean'],
            'color' => ['nullable', Rule::enum(CategoryColor::class)],
            'icon' => ['nullable', Rule::enum(ExerciseIcon::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('name'),
            'muscle_group' => __('muscle group'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function exerciseTypeAttributes(): array
    {
        $data = $this->validated();

        // Written under the fallback locale — the column is still translatable
        // JSON, the app just no longer authors a second language for it.
        $data['name'] = TranslatableInput::toTranslations($data['name']);

        $group = MuscleGroup::from($data['muscle_group']);

        // Colour follows the muscle group unless one was picked, so the
        // dashboard breakdown stays readable by group rather than by row. Same
        // reasoning as CategorySeeder colouring by theme.
        $data['color'] = $data['color'] ?? $group->color()->value;
        // Cardio defaults from the group, so filing something under Cardio
        // without ticking the box still logs distance and time.
        $data['is_cardio'] = $this->boolean('is_cardio') || $group === MuscleGroup::Cardio;

        return $data;
    }

    /**
     * Whether this person already has a movement by this name.
     *
     * Case-insensitive, and scoped to what they can see: their own types plus
     * the globals. Two *different* people may both invent "Row", so this is
     * deliberately not a global uniqueness check — unlike categories, which are
     * shared and therefore unique app-wide.
     */
    private function nameIsTaken(string $name): bool
    {
        $name = mb_strtolower(trim($name));

        if ($name === '') {
            return false;
        }

        return ExerciseType::query()
            ->availableTo((int) $this->user()->id)
            ->whereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, "$.en"))) = ?', [$name])
            // On update, the row being edited is not a clash with itself.
            ->when(
                $this->route('exercise_type'),
                fn ($query, $current) => $query->where('uuid', '!=', $current->uuid),
            )
            ->exists();
    }
}
