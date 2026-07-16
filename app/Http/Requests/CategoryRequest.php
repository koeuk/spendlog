<?php

namespace App\Http\Requests;

use App\Enums\CategoryColor;
use App\Enums\CategoryIcon;
use App\Enums\Locale;
use App\Models\Category;
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

    public function rules(): array
    {
        return [
            'name' => ['required', 'array'],
            // English is the fallback locale, so it is the one that must exist.
            'name.en' => ['required', 'string', 'max:255', $this->uniqueIn('en')],
            'name.km' => ['nullable', 'string', 'max:255', $this->uniqueIn('km')],
            'color' => ['required', Rule::enum(CategoryColor::class)],
            'icon' => ['nullable', Rule::enum(CategoryIcon::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.en.required' => __('The English name is required.'),
            'color.required' => __('Please pick a colour.'),
            'color.enum' => __('That colour is not one of the available options.'),
            'icon.enum' => __('That icon is not one of the available options.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'name.en' => __('English name'),
            'name.km' => __('Khmer name'),
        ];
    }

    /**
     * Rule::unique cannot target a key inside a JSON column, so uniqueness per
     * locale is checked with whereJsonContains instead.
     */
    private function uniqueIn(string $locale): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($locale) {
            if (blank($value)) {
                return;
            }

            $exists = Category::query()
                ->whereJsonContains("name->{$locale}", $value)
                ->when(
                    $this->route('category'),
                    fn ($query, Category $category) => $query->whereKeyNot($category->getKey()),
                )
                ->exists();

            if ($exists) {
                $fail(__('A category called ":name" already exists.', ['name' => $value]));
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function categoryAttributes(): array
    {
        $data = $this->validated();

        // Drop empty locales so spatie stores only real translations and the
        // fallback can kick in, rather than persisting "".
        $data['name'] = array_filter(
            $data['name'],
            fn (?string $value, string $locale) => filled($value) && Locale::tryFrom($locale),
            ARRAY_FILTER_USE_BOTH,
        );

        return $data;
    }
}
