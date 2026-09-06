<?php

namespace App\Http\Requests;

use App\Enums\ConnectionTriggerMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreParticipantConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageConnections', $this->route('participant'));
    }

    public function rules(): array
    {
        $participant = $this->route('participant');

        return [
            'connection_id' => [
                'required',
                Rule::exists('connections', 'id')->where('user_id', $this->user()->getKey()),
                Rule::unique('participant_connections', 'connection_id')->where('participant_id', $participant->getKey()),
            ],
            'is_enabled' => ['sometimes', 'boolean'],
            'configuration' => ['sometimes', 'array'],
            'configuration.trigger' => ['sometimes', 'array'],
            'configuration.trigger.mode' => ['required_with:configuration.trigger', Rule::enum(ConnectionTriggerMode::class)],
            'configuration.trigger.interval_minutes' => ['required_if:configuration.trigger.mode,interval', 'integer', 'min:1'],
        ];
    }
}
