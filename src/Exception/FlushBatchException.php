<?php

declare(strict_types=1);

namespace App\Exception;

class FlushBatchException extends \RuntimeException
{
    public static function fromPreviousException(\Throwable $previous): self
    {
        return new self(
            sprintf('Error while flushing data by batch to database: %s', $previous->getMessage()),
            0,
            $previous
        );
    }
}
