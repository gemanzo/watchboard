<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStatusPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('status_page'));
    }

    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:255'],
            'slug'        => ['required', 'string', 'min:3', 'max:255', 'regex:/^[a-z0-9-]+$/', Rule::unique('status_pages', 'slug')->ignore($this->route('status_page'))],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Lo slug può contenere solo lettere minuscole, numeri e trattini.',
        ];
    }
}
