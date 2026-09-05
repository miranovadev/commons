<?php

namespace App\Http\Requests;

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

    public function rules(): array
    {
        /** @var Substrate $substrate */
        $substrate = $this->route('substrate');

        return [
            'callsign' => [
                'required',
                'string',
                'max:255',
                Rule::unique('participants', 'callsign')
                    ->where('substrate_id', $substrate->getKey()),
            ],
            'designation' => ['nullable', 'string', 'max:255'],
            'max_read_count' => ['sometimes', 'integer', 'min:1'],
            'max_token_count' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
