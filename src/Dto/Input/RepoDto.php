<?php

namespace App\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class RepoDto
{
    /**
     * @Assert\NotBlank
     * @Assert\Type("integer")
     */
    private int $id;
    
    /**
     * @Assert\NotBlank
     */
    private string $name;
    
    /**
     * @Assert\Url
     */
    private string $url;
    
    public function __construct(array $repoData)
    {
        $this->id = (int)($repoData['id'] ?? 0);
        $this->name = (string)($repoData['name'] ?? '');
        $this->url = (string)($repoData['url'] ?? '');
    }
    
    public function getId(): int
    {
        return $this->id;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getUrl(): string
    {
        return $this->url;
    }
}
