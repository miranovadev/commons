<?php

namespace App\Services;

use App\Enums\WakeOffsetMode;
use App\Support\WakeConfiguration;

class WakeOffsetResolver
{
    public function seconds(?array $configuration): int
    {
        $offset = WakeConfiguration::resolve($configuration)['trigger']['offset'];

        return $offset['mode'] === WakeOffsetMode::Random->value
            ? $this->sample((int) $offset['random_from'], (int) $offset['random_to'])
            : (int) $offset['seconds'];
    }

    protected function sample(int $from, int $to): int
    {
        return mt_rand($from, $to);
    }
}
