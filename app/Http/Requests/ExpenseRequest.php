<?php

namespace App\Http\Requests;

use App\Enums\CategoryColor;
use App\Enums\Currency;
use App\Http\Requests\Concerns\ConvertsEnteredCurrency;
use App\Http\Requests\Concerns\ResolvesCategoryUuid;
use App\Models\Category;
use App\Support\TranslatableInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    use ConvertsEnteredCurrency, ResolvesCategoryUuid;

    /**
     * Authorization is handled by ExpensePolicy via the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * One Item box, not one per language — see TranslatableInput. The value is
     * stored under the fallback locale.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['item' => TranslatableInput::toString($this->input('item'))]);
    }

    public function rules(): array
    {
        return [
            'item' => ['required', 'string', 'max:255'],
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
                /*
                 * Case-insensitive: "coffee" must not create a second Coffee.
                 *
                 * Through Category::named(), which is the one place that decides
                 * what "the same name" means — and checks every locale, not just
                 * English. This rule used to compare the "en" key alone, so a
                 * category named only in Khmer was invisible to it and got a
                 * second row beside itself.
                 */
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (blank($value)) {
                        return;
                    }

                    if (Category::query()->named($value)->exists()) {
                        $fail(__('A category called ":name" already exists — pick it from the list.', ['name' => trim($value)]));
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'item.required' => __('The item is required.'),
            'spent_on.before_or_equal' => __('You cannot log an expense in the future.'),
            'category_uuid.required' => __('Please pick a category.'),
            'category_uuid.exists' => __('That category no longer exists.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'item' => __('item'),
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

        $data['category_id'] = $this->categoryId();
        unset($data['category_uuid'], $data['new_category']);

        $data['price'] = $this->usdAmount('price');
        unset($data['currency']);

        // Written under the fallback locale — the column is still translatable
        // JSON, the app just no longer authors a second language for it.
        $data['item'] = TranslatableInput::toTranslations($data['item']);

        return $data;
    }

    /**
     * An inline name creates the category; otherwise the picked uuid is resolved.
     *
     * The lookup goes through Category::named(), the same scope the Categories
     * page uses to refuse a duplicate — so "coffee" finds "Coffee", and a
     * Khmer-only name is found too. It used to compare the "en" key by hand
     * here, which is how the inline picker and the Categories page came to
     * disagree and split one real category into two rows.
     *
     * Two people naming the same category at the same instant can still both
     * insert: there is no unique index on the name to make this atomic, and a
     * firstOrCreate would not add one. That is a narrow race with a visible,
     * mergeable outcome.
     */
    private function categoryId(): int
    {
        $name = trim((string) $this->input('new_category'));

        if (blank($name)) {
            return $this->resolveCategoryId($this->validated('category_uuid'));
        }

        $existing = Category::query()->named($name)->value('id');

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
