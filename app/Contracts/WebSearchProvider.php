<?php

namespace App\Contracts;

use App\Domain\Evidence\SearchObservation;

interface WebSearchProvider
{
    public function search(string $query): SearchObservation;
}
