<?php

declare(strict_types=1);

namespace App\Dto;

class ImportGitHubEventsInput
{
    private string $date;
    private ?int $hour = null;
    private bool $isValid = true;
    private array $validationErrors = [];

    public function __construct(
        string $date,
        ?string $hour = null,
        private readonly string $dateFormat = 'Y-m-d'
    ) {
        $this->date = $date;

        if ($hour !== null) {
            if (!is_numeric($hour)) {
                $this->validationErrors[] = "Hour must be a numeric value between 0 and 23, '$hour' is not valid.";
                $this->isValid = false;
            } else {
                $this->hour = (int) $hour;
            }
        }

        $this->validate();
    }
    
    private function validate(): void
    {
        if (!$this->validateDateFormat($this->date)) {
            $this->isValid = false;
            $this->validationErrors[] = "Invalid date format. Use the format " . $this->dateFormat;
        }
        
        if ($this->hour !== null && ($this->hour < 0 || $this->hour > 23)) {
            $this->isValid = false;
            $this->validationErrors[] = "Hour must be between 0 and 23.";
        }
    }
    
    private function validateDateFormat(string $date): bool
    {
        $format = $this->dateFormat;
        $dateObj = \DateTime::createFromFormat($format, $date);
        return $dateObj && $dateObj->format($format) === $date;
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    public function getDate(): string
    {
        return $this->date;
    }

    public function getHour(): ?int
    {
        return $this->hour;
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }
    
    public static function fromConsoleInput($input, string $dateFormat = 'Y-m-d'): self
    {
        return new self(
            $input->getArgument('date'),
            $input->getOption('hour'),
            $dateFormat
        );
    }
}
