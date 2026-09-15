<?php

namespace App\Services;

use App\Models\Participant;
use App\Services\Evidence\InstrumentInvocation;
use InvalidArgumentException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;

final readonly class ContributionService
{
    public function __construct(
        private InputPlanService $plans,
        private Validator $validator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function planFor(Participant $participant): array
    {
        return $this->plans->forParticipant($participant);
    }

    /**
     * Validate a response against the participant's currently admissible move.
     *
     * Dispatching valid contributions is intentionally left to the domain
     * services introduced as each operation is implemented.
     *
     * @param  array<string, mixed>  $input
     */
    public function validate(Participant $participant, array $input): void
    {
        $plan = $this->plans->forValidation($participant);
        $this->validateSchema($plan['schema'], $input);
    }

    public function validateSchema(array $schema, array $input): void
    {
        if (($input['action'] ?? null) === 'instrument.execute' && is_array($input['instrument'] ?? null)) {
            try {
                $input['instrument'] = app(InstrumentInvocation::class)->normalize($input['instrument']);
            } catch (\DomainException) {
                // Preserve invalid input for the ordinary shape/domain validation.
            }
        }
        // Phase schema IDs are stable, but their authorized targets change per
        // participant and move. Never reuse a compiled earlier specialization.
        $this->validator->loader()->clearCache();
        $result = $this->validator->validate(
            Helper::toJSON($input),
            Helper::toJSON($schema),
        );

        if ($result->isValid()) {
            return;
        }

        $errors = (new ErrorFormatter)->format($result->error(), true);

        throw new InvalidArgumentException(
            json_encode($errors, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
        );
    }
}
