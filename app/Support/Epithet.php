<?php

namespace App\Support;

final class Epithet
{
    /**
     * Compute the primary Epithet for a proposition based on (tier, register).
     * Returns null if the combination is not defined in v0.1.
     */
    public static function for(string $tier, string $register): ?string
    {
        $tier = strtolower($tier);
        $register = strtolower($register);

        // Committal-side epithets
        if ($tier === 'committal') {
            return match ($register) {
                'coherence' => 'Canonical',
                'entropy' => 'Open Problem',
                'activation' => 'Gate Lock',
                'resonance' => 'Concordance',
                'valence' => 'Bias Anchor',
                'salience' => 'Priority',
                'modality' => 'Specification',
                // stimulus, excitation, etc not yet named
            };
        }

        // Expressive-side epithets (v0.1 completeness)
        if ($tier === 'expressive') {
            return match ($register) {
                'activation' => 'Intent',
                'entropy' => 'Open Question',
                // keep others undefined for now
            };
        }

        return 'Not defined';
    }
}
