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
        $plan         = $this->user()->planConfig();
        $minInterval  = (int) $plan['min_interval_minutes'];
        $maxThreshold = (int) $plan['max_confirmation_threshold'];
        $responseTimeAlertsAllowed = (bool) $plan['response_time_alerts'];

        return [
            'name'                       => ['nullable', 'string', 'max:255'],
            'url'                        => ['required', 'url', 'max:2048'],
            'method'                     => ['required', 'in:GET,HEAD'],
            'interval_minutes'           => ['required', 'integer', 'min:'.$minInterval],
            'confirmation_threshold'     => ['nullable', 'integer', 'min:1', 'max:'.$maxThreshold],
            'response_time_threshold_ms' => $responseTimeAlertsAllowed
                ? ['nullable', 'integer', 'min:100']
                : ['prohibited'],
            'ssl_check_enabled'    => ['boolean'],
            'ssl_expiry_alert_days' => ['integer', 'min:1', 'max:90'],
        ];
    }
}
