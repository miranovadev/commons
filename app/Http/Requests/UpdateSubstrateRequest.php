<?php

namespace App\Http\Requests;

use App\Support\SubstratePresentation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateSubstrateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('substrate'));
    }

    public function rules(): array
    {
        return [
            'configuration' => ['required', 'array:presentation'],
            ...SubstratePresentation::rules(),
        ];
    }
}
