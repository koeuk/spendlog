<?php

namespace App\Http\Requests;

use App\Enums\CategoryColor;
use App\Enums\Currency;
use App\Enums\Locale;
use App\Models\AppSetting;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    /**
     * Authorization is handled by ExpensePolicy via the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item' => ['required', 'array'],
            // English is the fallback locale, so it is the one that must exist —
            // same rule as CategoryRequest::$name.
            'item.en' => ['required', 'string', 'max:255'],
            'item.km' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            // What the *entered* price is denominated in. Absent means USD, so
            // any existing client that never sends it keeps working unchanged.
            'currency' => ['nullable', Rule::enum(Currency::class)],
            'spent_on' => ['required', 'date', 'before_or_equal:today'],

            /*
             * Exactly one of these. The dialog either picks an existing category
             * or names a new one inline, so requiring the uuid outright would
             * reject every inline creation.
             */
            'category_uuid' => [
                Rule::requiredIf(fn () => blank($this->input('new_category'))),
                'nullable',
                'uuid',
                'exists:categories,uuid',
            ],
            'new_category' => [
                'nullable',
                'string',
                'max:255',
                // Case-insensitive: "coffee" must not create a second Coffee.
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (blank($value)) {
                        return;
                    }

                    $exists = Category::query()
                        ->whereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, "$.en"))) = ?', [mb_strtolower(trim($value))])
                        ->exists();

                    if ($exists) {
                        $fail(__('A category called ":name" already exists — pick it from the list.', ['name' => trim($value)]));
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'item.en.required' => __('The English item is required.'),
            'spent_on.before_or_equal' => __('You cannot log an expense in the future.'),
            'category_uuid.required' => __('Please pick a category.'),
            'category_uuid.exists' => __('That category no longer exists.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'item.en' => __('English item'),
            'item.km' => __('Khmer item'),
            'new_category' => __('category'),
        ];
    }

    /**
     * The frontend only ever sees UUIDs, so swap the category UUID for the
     * internal foreign key before the model is written.
     *
     * @return array<string, mixed>
     */
    public function expenseAttributes(): array
    {
        $data = $this->validated();

        $data['category_id'] = $this->resolveCategoryId();
        unset($data['category_uuid'], $data['new_category']);

        // Every stored price is USD — see App\Enums\Currency. The currency is a
        // property of what was typed, not of the expense, so it is consumed here
        // rather than persisted.
        $currency = Currency::tryFrom((string) $this->input('currency')) ?? Currency::Usd;
        $data['price'] = $currency->toUsd((float) $data['price'], AppSetting::current()->khrPerUsd());
        unset($data['currency']);

        // Drop empty locales so spatie stores only real translations and the
        // fallback can kick in, rather than persisting "".
        $data['item'] = array_filter(
            $data['item'],
            fn (?string $value, string $locale) => filled($value) && Locale::tryFrom($locale),
            ARRAY_FILTER_USE_BOTH,
        );

        return $data;
    }

    /**
     * An inline name creates the category; otherwise the picked uuid is resolved.
     *
     * firstOrCreate, not create: two people naming the same category at once
     * would otherwise race past the validator and insert a duplicate.
     */
    private function resolveCategoryId(): int
    {
        $name = trim((string) $this->input('new_category'));

        if (blank($name)) {
            return (int) Category::where('uuid', $this->validated('category_uuid'))->value('id');
        }

        $existing = Category::query()
            ->whereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, "$.en"))) = ?', [mb_strtolower($name)])
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        $category = new Category;
        // Only English: whoever typed it was logging an expense, not translating.
        // The Khmer name can be filled in later on the Categories page.
        $category->setTranslations('name', ['en' => $name]);
        $category->color = CategoryColor::Slate;
        $category->save();

        return (int) $category->id;
    }
}
