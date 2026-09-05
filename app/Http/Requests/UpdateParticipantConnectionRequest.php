<?php

namespace App\Http\Requests;

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
                'sometimes',
                Rule::in(['every_cycle', 'cycle_interval', 'manual']),
            ],
            'configuration.trigger.interval' => [
                'required_if:configuration.trigger.mode,cycle_interval',
                'integer',
                'min:2',
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
