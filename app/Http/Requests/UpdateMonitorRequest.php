<?php

namespace App\Http\Requests;

use App\Models\Monitor;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMonitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('monitor'));
    }

    public function rules(): array
    {
        $minimumInterval = (int) $this->user()->planConfig()['min_interval_minutes'];

        $maxThreshold = (int) $this->user()->planConfig()['max_confirmation_threshold'];

        return [
            'name'                   => ['nullable', 'string', 'max:255'],
            'url'                    => ['required', 'url', 'max:2048'],
            'method'                 => ['required', 'in:GET,HEAD'],
            'interval_minutes'       => ['required', 'integer', 'min:'.$minimumInterval],
            'confirmation_threshold'     => ['nullable', 'integer', 'min:1', 'max:'.$maxThreshold],
            'response_time_threshold_ms' => ['nullable', 'integer', 'min:100'],
        ];
    }
}
