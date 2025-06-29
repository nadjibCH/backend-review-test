<?php

declare(strict_types=1);

namespace App\Exception;

class GitHubEventParsingException extends \RuntimeException
{
    public static function invalidJson(\JsonException $previous): self
    {
        return new self(
            sprintf('Invalid JSON in GitHub event - ErrorMessage: %s', $previous->getMessage()),
            0,
            $previous
        );
    }
    
    public static function processingError(\Throwable $previous): self
    {
        return new self(
            sprintf('Error processing GitHub event - ErrorMessage: %s', $previous->getMessage()),
            0,
            $previous
        );
    }
}
