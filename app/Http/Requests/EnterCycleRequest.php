<?php

namespace App\Http\Requests;

use App\Enums\LedgerEntryKind;
use App\Models\Participant;
use App\Models\Substrate;
use App\Models\SubstrateLedgerEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EnterCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $participant = $this->route('participant');

        return $participant instanceof Participant
            && Gate::allows('act', $participant);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $substrate = $this->route('substrate');
        $hasOrigin = $substrate instanceof Substrate
            && SubstrateLedgerEntry::query()
                ->whereBelongsTo($substrate)
                ->where('kind', LedgerEntryKind::Proposition->value)
                ->exists();

        if ($hasOrigin) {
            return [];
        }

        return [
            'origin' => ['required', 'array:statement,attestation'],
            'origin.statement' => ['required', 'string', 'max:20000'],
            'origin.attestation' => ['required', 'array:direction,rationale'],
            'origin.attestation.direction' => [
                'required',
                'string',
                Rule::in(['support', 'refute']),
            ],
            'origin.attestation.rationale' => [
                'nullable',
                'string',
                'max:20000',
            ],
        ];
    }
}
