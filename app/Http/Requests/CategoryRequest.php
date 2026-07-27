<?php

namespace App\Http\Requests;

use App\Enums\CategoryColor;
use App\Enums\CategoryIcon;
use App\Support\TranslatableInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shape only. What a valid name means against the rest of the table — that no
 * other category already answers to it — is the controller's, since that is
 * where the write it guards happens.
 */
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
     * Name box and the value is stored under the fallback locale. An older API
     * client still sending a per-locale map is reduced to one string here rather
     * than failing the rule below.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['name' => TranslatableInput::toString($this->input('name'))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
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
}
