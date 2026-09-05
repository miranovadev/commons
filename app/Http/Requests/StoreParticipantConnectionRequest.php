<?php

namespace App\Http\Requests;

use App\Enums\ConnectionProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreParticipantConnectionRequest extends FormRequest
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
        $provider = (string) $this->input('provider');

        return [
            'provider' => ['required', Rule::enum(ConnectionProvider::class)],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('participant_connections', 'name')
                    ->where('participant_id', $participant->getKey())
                    ->where('provider', $provider),
            ],
            'is_enabled' => ['sometimes', 'boolean'],
            'configuration' => ['required', 'array'],
            'configuration.model' => ['required', 'string', 'max:255'],
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
            'credentials' => ['required', 'array'],
            'credentials.api_key' => ['required', 'string', 'max:4096'],
        ];
    }
}
