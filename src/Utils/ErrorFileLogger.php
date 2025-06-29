<?php

declare(strict_types=1);

namespace App\Utils;

class ErrorFileLogger
{
    private string $baseLogDir;

    public function __construct(string $baseLogDir = __DIR__ . '/../../var/log/')
    {
        $this->baseLogDir = rtrim($baseLogDir, '/') . '/';
    }

    /**
     * Log an error to a file.
     *
     * @param string     $folderName   Name of the folder where the log will be stored
     * @param string     $key          Unique key for this log (URL, date, etc)
     * @param \Throwable $exception    Exception or Error to log
     * @param array      $context      Extra context to add to the log (optional)
     */
    public function log(
        string $folderName,
        string $key,
        \Throwable $exception,
        array $context = []
    ): void {
        $logDir = $this->baseLogDir . $folderName;

        if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            throw new \RuntimeException("Unable to create log directory: {$logDir}");
        }

        $fileKey = preg_replace('/[^A-Za-z0-9_\-]/', '_', $key);
        $datePart = (new \DateTime())->format('Ymd_His');
        $logFile = rtrim($logDir, '/') . "/error-{$fileKey}-{$datePart}.log";

        $logEntry = sprintf(
            "[%s] %s: %s\nContext: %s\n\n",
            (new \DateTime())->format('Y-m-d H:i:s'),
            get_class($exception),
            $exception->getMessage(),
            json_encode($context)
        );

        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}
