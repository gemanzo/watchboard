<?php

namespace App\Http\Requests;

use App\Models\Monitor;
use Illuminate\Foundation\Http\FormRequest;

class StoreMonitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Monitor::class);
    }

    public function rules(): array
    {
        $minimumInterval = (int) $this->user()->planConfig()['min_interval_minutes'];

        return [
            'name'             => ['nullable', 'string', 'max:255'],
            'url'              => ['required', 'url', 'max:2048'],
            'method'           => ['required', 'in:GET,HEAD'],
            'interval_minutes' => ['required', 'integer', 'min:'.$minimumInterval],
        ];
    }
}
