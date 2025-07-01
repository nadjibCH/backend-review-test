<?php

declare(strict_types=1);

namespace App\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

class EventCommentInput
{
    /**
     * @Assert\NotBlank
     *
     * @Assert\Length(min=20)
     */
    public string $comment;

    public function __construct(string $comment)
    {
        $this->comment = $comment;
    }
}
