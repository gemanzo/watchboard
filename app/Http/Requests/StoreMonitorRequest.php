<?php

namespace App\Http\Requests;

use App\Models\Monitor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMonitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Monitor::class);
    }

    public function rules(): array
    {
        return [
            'name'             => ['nullable', 'string', 'max:255'],
            'url'              => ['required', 'url', 'max:2048'],
            'method'           => ['required', 'in:GET,HEAD'],
            'interval_minutes' => ['required', Rule::in($this->user()->planConfig()['intervals'])],
        ];
    }
}
