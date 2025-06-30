<?php

namespace App\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class ActorDto
{
    /**
     * @Assert\NotBlank
     * @Assert\Type("integer")
     */
    private int $id;
    
    /**
     * @Assert\NotBlank
     */
    private string $login;
    
    /**
     * @Assert\Url
     */
    private string $avatarUrl;
    
    /**
     * @Assert\Url
     */
    private string $url;
    
    public function __construct(array $actorData)
    {
        $this->id = (int)($actorData['id'] ?? 0);
        $this->login = (string)($actorData['login'] ?? '');
        $this->avatarUrl = (string)($actorData['avatar_url'] ?? '');
        $this->url = (string)($actorData['url'] ?? '');
    }
    
    public function getId(): int
    {
        return $this->id;
    }
    
    public function getLogin(): string
    {
        return $this->login;
    }
    
    public function getAvatarUrl(): string
    {
        return $this->avatarUrl;
    }
    
    public function getUrl(): string
    {
        return $this->url;
    }
}
