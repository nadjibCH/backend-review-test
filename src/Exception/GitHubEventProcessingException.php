<?php

declare(strict_types=1);

namespace App\Exception;

class GitHubEventProcessingException extends \RuntimeException
{
    public static function processingFailed(string $eventId, \Throwable $previous): self
    {
        return new self(
            sprintf('Failed to process GitHub event with ID: %s - ErrorMessage: %s', $eventId, $previous->getMessage()),
            0,
            $previous
        );
    }
}
