<?php

namespace App\Http\Requests;

use App\Enums\ConnectionTriggerMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateParticipantConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageConnections', $this->route('participant'));
    }

    public function rules(): array
    {
        return [
            'is_enabled' => ['sometimes', 'boolean'],
            'configuration' => ['sometimes', 'array'],
            'configuration.trigger' => ['sometimes', 'array'],
            'configuration.trigger.mode' => ['required_with:configuration.trigger', Rule::enum(ConnectionTriggerMode::class)],
            'configuration.trigger.interval_minutes' => ['required_if:configuration.trigger.mode,interval', 'integer', 'min:1'],
        ];
    }
}
