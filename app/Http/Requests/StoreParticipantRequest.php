<?php

namespace App\Http\Requests;

use App\Enums\ConnectionTriggerMode;
use App\Support\WakeConfiguration;
use Illuminate\Validation\Validator;
use App\Models\Substrate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('join', $this->route('substrate'));
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            WakeConfiguration::validateRange($validator, array_replace_recursive(
                [],
                (array) $this->input('connection.configuration', []),
            ), 'connection.configuration');
        }];
    }

    public function rules(): array
    {
        /** @var Substrate $substrate */
        $substrate = $this->route('substrate');

        return [
            ...WakeConfiguration::rules('connection.configuration'),
            'callsign' => ['required', 'string', 'max:255', Rule::unique('participants', 'callsign')->where('substrate_id', $substrate->getKey())],
            'designation' => ['nullable', 'string', 'max:255'],
            'max_read_count' => ['sometimes', 'integer', 'min:1'],
            'max_token_count' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'connection' => ['sometimes', 'nullable', 'array'],
            'connection.id' => ['required_with:connection', Rule::exists('connections', 'id')->where('user_id', $this->user()->getKey())],
            'connection.is_enabled' => ['sometimes', 'boolean'],
            'connection.configuration' => ['sometimes', 'array'],
            'connection.configuration.trigger' => ['sometimes', 'array'],
            'connection.configuration.trigger.mode' => ['required_with:connection.configuration.trigger', Rule::enum(ConnectionTriggerMode::class)],
            'connection.configuration.trigger.interval_minutes' => ['required_if:connection.configuration.trigger.mode,interval', 'integer', 'min:1'],
        ];
    }
}

