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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var \App\Models\Monitor $monitor */
            $monitor = $this->route('monitor');
            $checkType = $this->input('check_type', 'http');
            $allowedCheckTypes = $this->user()->planConfig()['allowed_check_types'] ?? ['http', 'ping', 'tcp'];

            if (! in_array($checkType, $allowedCheckTypes, true) && $checkType !== $monitor->check_type) {
                $validator->errors()->add('check_type', 'Il tipo di check selezionato non è disponibile per il tuo piano.');
                return;
            }

            if ($checkType === 'http') {
                if (! filter_var($this->input('url'), FILTER_VALIDATE_URL)) {
                    $validator->errors()->add('url', 'The url field must be a valid URL.');
                }

                if (! in_array($this->input('method'), ['GET', 'HEAD'], true)) {
                    $validator->errors()->add('method', 'The method field is required for HTTP checks.');
                }
            }

            if ($checkType === 'tcp' && ! is_numeric($this->input('port'))) {
                $validator->errors()->add('port', 'The port field is required for TCP checks.');
            }

            if ($checkType !== 'http' && $this->filled('keyword_check')) {
                $validator->errors()->add('keyword_check', 'Il keyword check è disponibile solo per monitor HTTP.');
                return;
            }

            if ($this->boolean('ssl_check_enabled') && ! $monitor->ssl_check_enabled) {
                $maxSsl = $this->user()->planConfig()['max_ssl_monitors'] ?? null;

                if ($maxSsl !== null) {
                    $used = $this->user()->monitors()
                        ->where('ssl_check_enabled', true)
                        ->where('id', '!=', $monitor->id)
                        ->count();

                    if ($used >= $maxSsl) {
                        $validator->errors()->add('ssl_check_enabled', 'Hai raggiunto il limite di monitor SSL per il tuo piano.');
                    }
                }
            }

            $isEnablingKeyword = $this->filled('keyword_check') && empty($monitor->keyword_check);
            if (! $isEnablingKeyword) {
                return;
            }

            $maxKeyword = $this->user()->planConfig()['max_keyword_monitors'] ?? null;
            if ($maxKeyword === null) {
                return;
            }

            $usedKeyword = $this->user()->monitors()
                ->whereNotNull('keyword_check')
                ->where('id', '!=', $monitor->id)
                ->count();

            if ($usedKeyword >= $maxKeyword) {
                $validator->errors()->add('keyword_check', 'Hai raggiunto il limite di monitor con keyword check per il tuo piano.');
            }
        });
    }

    public function rules(): array
    {
        $plan         = $this->user()->planConfig();
        $minInterval  = (int) $plan['min_interval_minutes'];
        $maxThreshold = (int) $plan['max_confirmation_threshold'];
        $responseTimeAlertsAllowed = (bool) $plan['response_time_alerts'];

        return [
            'name'                       => ['nullable', 'string', 'max:255'],
            'url'                        => ['required', 'string', 'max:2048'],
            'check_type'                 => ['nullable', 'in:http,tcp,ping'],
            'method'                     => ['nullable', 'in:GET,HEAD'],
            'port'                       => ['nullable', 'integer', 'min:1', 'max:65535'],
            'interval_minutes'           => ['required', 'integer', 'min:'.$minInterval],
            'confirmation_threshold'     => ['nullable', 'integer', 'min:1', 'max:'.$maxThreshold],
            'response_time_threshold_ms' => $responseTimeAlertsAllowed
                ? ['nullable', 'integer', 'min:100']
                : ['prohibited'],
            'keyword_check'              => ['nullable', 'string', 'max:255', 'required_with:keyword_check_type'],
            'keyword_check_type'         => ['nullable', 'in:contains,not_contains', 'required_with:keyword_check'],
            'ssl_check_enabled'     => ['boolean'],
            'ssl_expiry_alert_days' => ['integer', 'min:1', 'max:90'],
        ];
    }
}
