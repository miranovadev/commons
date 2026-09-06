<?php

namespace App\Http\Requests;

use App\Enums\ConnectionProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('connection'));
    }

    public function rules(): array
    {
        $connection = $this->route('connection');
        $provider = (string) $this->input('provider', $connection->provider->value);

        return [
            'provider' => ['sometimes', Rule::enum(ConnectionProvider::class)],
            'name' => [
                'sometimes', 'string', 'max:100',
                Rule::unique('connections', 'name')->where('user_id', $this->user()->getKey())->where('provider', $provider)->ignore($connection->getKey()),
            ],
            'configuration' => ['sometimes', 'array'],
            'configuration.model' => ['sometimes', 'string', 'max:255'],
            'configuration.instructions' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'credentials' => ['sometimes', 'array'],
            'credentials.api_key' => ['required_with:credentials', 'string', 'max:4096'],
        ];
    }
}
