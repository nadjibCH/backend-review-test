<?php

declare(strict_types=1);

namespace App\Exception;

class GitHubArchiveStreamingException extends \RuntimeException
{
    public static function streamingFailed(string $url, \Throwable $previous): self
    {
        return new self(
            sprintf('Failed to stream GitHub archive from URL: %s - ErrorMessage: %s', $url, $previous->getMessage()),
            0,
            $previous
        );
    }

}
