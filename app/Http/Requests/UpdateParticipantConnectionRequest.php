<?php

namespace App\Http\Requests;

use App\Enums\ConnectionTriggerMode;
use App\Support\WakeConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateParticipantConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageConnections', $this->route('participant'));
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            WakeConfiguration::validateRange($validator, array_replace_recursive(
                $this->route('connection')?->configuration ?? [],
                (array) $this->input('configuration', []),
            ), 'configuration');
        }];
    }

    public function rules(): array
    {
        return [
            ...WakeConfiguration::rules('configuration'),
            'is_enabled' => ['sometimes', 'boolean'],
            'configuration' => ['sometimes', 'array'],
            'configuration.trigger' => ['sometimes', 'array'],
            'configuration.trigger.mode' => ['required_with:configuration.trigger', Rule::enum(ConnectionTriggerMode::class)],
            'configuration.trigger.interval_minutes' => ['required_if:configuration.trigger.mode,interval', 'integer', 'min:1'],
        ];
    }
}

