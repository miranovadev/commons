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
            'origin' => ['required', 'array:statement,attestation,instrument'],
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
            'origin.instrument' => ['required', 'array:kind,reference,query'],
            'origin.instrument.kind' => [
                'required',
                'string',
                Rule::in(['hyperlink', 'web_search']),
            ],
            'origin.instrument.reference' => [
                'required_if:origin.instrument.kind,hyperlink',
                'prohibited_unless:origin.instrument.kind,hyperlink',
                'string', 'max:20000',
            ],
            'origin.instrument.query' => [
                'required_if:origin.instrument.kind,web_search',
                'prohibited_unless:origin.instrument.kind,web_search',
                'string', 'max:1000', 'regex:/^[^\\x00]*[^\\s\\x00][^\\x00]*$/u',
            ],
        ];
    }
}
