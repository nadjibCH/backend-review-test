<?php

declare(strict_types=1);

namespace App\Dto\Input;

use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

class SearchInput
{
    #[Assert\NotNull(message: 'The date is required.')]
    #[Assert\Type(\DateTimeImmutable::class, message: 'The date must be a valid date.')]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    public \DateTimeImmutable $date;

    #[Assert\Type('string')]
    public ?string $keyword = null;

    public function __construct(\DateTimeImmutable $date, ?string $keyword = null)
    {
        $this->date    = $date;
        $this->keyword = $keyword;
    }
}
