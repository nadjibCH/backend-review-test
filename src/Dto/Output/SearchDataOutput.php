<?php

namespace App\Dto\Output;

class SearchDataOutput
{
    /**
     * @var array
     */
    public array $events;

    /**
     * @var array
     */
    public array $stats;

    public function __construct(array $events, array $stats)
    {
        $this->events = $events;
        $this->stats = $stats;
    }
}
