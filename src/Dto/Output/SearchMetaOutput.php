<?php

namespace App\Dto\Output;

class SearchMetaOutput
{
    public int $totalEvents;
    public int $totalPullRequests;
    public int $totalCommits;
    public int $totalComments;

    public function __construct(
        int $totalEvents,
        int $totalPullRequests,
        int $totalCommits,
        int $totalComments
    ) {
        $this->totalEvents = $totalEvents;
        $this->totalPullRequests = $totalPullRequests;
        $this->totalCommits = $totalCommits;
        $this->totalComments = $totalComments;
    }
}
