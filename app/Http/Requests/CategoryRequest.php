<?php

namespace App\Http\Requests;

use App\Enums\CategoryColor;
use App\Enums\CategoryIcon;
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->ignore($this->route('category')),
            ],
            'color' => ['required', Rule::enum(CategoryColor::class)],
            'icon' => ['nullable', Rule::enum(CategoryIcon::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'color.required' => 'Please pick a colour.',
            'color.enum' => 'That colour is not one of the available options.',
            'icon.enum' => 'That icon is not one of the available options.',
        ];
    }
}
