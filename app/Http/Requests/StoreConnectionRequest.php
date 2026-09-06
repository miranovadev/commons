<?php

namespace App\Http\Requests;

use App\Enums\ConnectionProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $provider = (string) $this->input('provider');

        return [
            'provider' => ['required', Rule::enum(ConnectionProvider::class)],
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('connections', 'name')->where('user_id', $this->user()->getKey())->where('provider', $provider),
            ],
            'configuration' => ['required', 'array'],
            'configuration.model' => ['required', 'string', 'max:255'],
            'configuration.instructions' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'credentials' => ['required', 'array'],
            'credentials.api_key' => ['required', 'string', 'max:4096'],
        ];
    }
}
