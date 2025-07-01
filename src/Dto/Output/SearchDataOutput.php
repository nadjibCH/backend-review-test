<?php

declare(strict_types=1);

namespace App\Dto\Output;

class SearchDataOutput
{
    public array $events;

    public array $stats;

    public function __construct(array $events, array $stats)
    {
        $this->events = $events;
        $this->stats  = $stats;
    }
}
