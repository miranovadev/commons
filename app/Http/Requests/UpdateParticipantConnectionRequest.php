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
        return Gate::allows(
            'manageConnections',
            $this->route('participant'),
        );
    }

    public function rules(): array
    {
        $participant = $this->route('participant');
        $connection = $this->route('connection');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('participant_connections', 'name')
                    ->where('participant_id', $participant->getKey())
                    ->where('provider', $connection->provider->value)
                    ->ignore($connection->getKey()),
            ],
            'is_enabled' => ['sometimes', 'boolean'],
            'configuration' => ['sometimes', 'array'],
            'configuration.model' => ['sometimes', 'string', 'max:255'],
            'configuration.instructions' => [
                'sometimes',
                'nullable',
                'string',
                'max:20000',
            ],
            'configuration.trigger' => ['sometimes', 'array'],
            'configuration.trigger.mode' => [
                'required_with:configuration.trigger',
                Rule::enum(ConnectionTriggerMode::class),
            ],
            'configuration.trigger.interval_minutes' => [
                'required_if:configuration.trigger.mode,interval',
                'integer',
                'min:1',
            ],
            'credentials' => ['sometimes', 'array'],
            'credentials.api_key' => [
                'required_with:credentials',
                'string',
                'max:4096',
            ],
        ];
    }
}
