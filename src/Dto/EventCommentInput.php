<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class EventCommentInput
{
    /**
     * @Assert\NotBlank
     * @Assert\Length(min=20)
     */
    public string $comment;

    public function __construct(string $comment) {
        $this->comment = $comment;
    }
}
