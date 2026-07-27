<?php

namespace App\Http\Requests;

use App\Enums\CategoryColor;
use App\Enums\CategoryIcon;
use App\Enums\Locale;
use App\Models\Category;
use App\Support\TranslatableInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    /**
     * Authorization is handled by CategoryPolicy via the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A category is named once, not once per language: the form has a single
     * Name box and the value is stored under the fallback locale. The column is
     * still translatable JSON, so translations already in the database keep
     * rendering — nothing writes a second locale from here any more.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['name' => TranslatableInput::toString($this->input('name'))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', $this->unique()],
            'color' => ['required', Rule::enum(CategoryColor::class)],
            'icon' => ['nullable', Rule::enum(CategoryIcon::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('The name is required.'),
            'color.required' => __('Please pick a colour.'),
            'color.enum' => __('That colour is not one of the available options.'),
            'icon.enum' => __('That icon is not one of the available options.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('name'),
        ];
    }

    /**
     * Rule::unique cannot target a key inside a JSON column, so uniqueness is
     * checked by hand — against every locale, not just the one being written.
     * The name goes in under `en`, but a category seeded with a Khmer
     * translation is the same category to whoever is reading the list, and
     * letting a second row take that name is what this guards.
     *
     * Compared case-insensitively, and not with whereJsonContains: that compiles
     * to JSON_CONTAINS, which MySQL evaluates under a binary collation, so
     * "food" did not match "Food" here — while the inline category picker on the
     * expense form, which uses the LOWER() comparison below, considered them the
     * same name. Categories are shared by everyone, so the disagreement was
     * enough to split one real category into two rows, each able to hold its own
     * budget for the same month, with no merge tool and no way to delete either
     * once it had expenses.
     */
    private function unique(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if (blank($value)) {
                return;
            }

            $exists = Category::query()
                ->where(function ($query) use ($value) {
                    foreach (Locale::cases() as $locale) {
                        // The JSON path has to be a literal — MySQL will not
                        // take it as a bound parameter. It comes from the Locale
                        // enum, never from input.
                        $query->orWhereRaw(
                            'LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, "$.'.$locale->value.'"))) = ?',
                            [mb_strtolower(trim($value))],
                        );
                    }
                })
                ->when(
                    $this->route('category'),
                    fn ($query, Category $category) => $query->whereKeyNot($category->getKey()),
                )
                ->exists();

            if ($exists) {
                $fail(__('A category called ":name" already exists.', ['name' => trim($value)]));
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function categoryAttributes(): array
    {
        $data = $this->validated();

        // Written under the fallback locale, which is the one spatie resolves to
        // when the reader's own language has no translation.
        $data['name'] = TranslatableInput::toTranslations($data['name']);

        return $data;
    }
}
