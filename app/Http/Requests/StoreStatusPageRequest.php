<?php

namespace App\Http\Requests;

use App\Models\StatusPage;
use Illuminate\Foundation\Http\FormRequest;

class StoreStatusPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', StatusPage::class);
    }

    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:255'],
            'slug'        => ['required', 'string', 'min:3', 'max:255', 'regex:/^[a-z0-9-]+$/', 'unique:status_pages,slug'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Lo slug può contenere solo lettere minuscole, numeri e trattini.',
        ];
    }
}
