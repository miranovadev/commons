<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Substrate;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreSubstrateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Substrate::class);
    }

    public function rules(): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'alpha_dash',
                'max:100',
                Rule::unique('substrates', 'slug'),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'visibility' => ['required', Rule::in(['public', 'private'])],
            'configuration' => ['sometimes', 'array'],
            'configuration.maximum_participants' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }
}
