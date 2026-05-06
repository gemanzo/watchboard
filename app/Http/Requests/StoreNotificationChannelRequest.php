<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'      => ['required', 'in:webhook,slack,email'],
            'label'     => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'config'    => ['required', 'array'],

            // Webhook
            'config.url'             => ['required_if:type,webhook', 'nullable', 'url', 'max:2048'],
            'config.secret'          => ['nullable', 'string', 'max:255'],
            'config.timeout_seconds' => ['nullable', 'integer', 'min:3', 'max:30'],

            // Slack
            'config.webhook_url' => ['required_if:type,slack', 'nullable', 'url', 'max:2048'],

            // Email
            'config.address' => ['required_if:type,email', 'nullable', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'config.url.required_if'         => 'The webhook URL is required.',
            'config.webhook_url.required_if'  => 'The Slack webhook URL is required.',
            'config.address.required_if'      => 'The email address is required.',
        ];
    }
}
