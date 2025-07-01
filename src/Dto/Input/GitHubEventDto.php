<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Enum\GitHubEventType;
use Symfony\Component\Validator\Constraints as Assert;

final class GitHubEventDto
{
    /**
     * @Assert\NotBlank
     *
     * @Assert\Positive
     */
    private int $id;

    /**
     * @Assert\NotBlank
     */
    private string $type;

    /**
     * @Assert\NotBlank
     *
     * @Assert\Type("\DateTimeInterface")
     */
    private string $createdAt;

    /**
     * @Assert\Valid
     */
    private ActorDto $actor;

    /**
     * @Assert\Valid
     */
    private RepoDto $repo;

    /**
     * @Assert\Type("array")
     *
     * @Assert\NotNull
     */
    private array $payload;

    private ?string $comment = null;

    public function __construct(array $rawEvent)
    {
        $this->id        = (int) ($rawEvent['id'] ?? 0);
        $this->type      = (string) ($rawEvent['type'] ?? '');
        $this->createdAt = (string) ($rawEvent['created_at'] ?? '');
        $this->actor     = new ActorDto($rawEvent['actor'] ?? []);
        $this->repo      = new RepoDto($rawEvent['repo'] ?? []);

        $this->payload = $this->removeNullBytes($rawEvent['payload'] ?? []);

        if (\in_array($this->type, [GitHubEventType::ISSUE_COMMENT, GitHubEventType::COMMIT_COMMENT, GitHubEventType::PULL_REQUEST_REVIEW_COMMENT])) {
            $comment       = (string) ($this->payload['comment']['body'] ?? null);
            $this->comment = $this->sanitizeComment($comment);
        }
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getActor(): ActorDto
    {
        return $this->actor;
    }

    public function getActorId(): int
    {
        return $this->actor->getId();
    }

    public function getActorLogin(): string
    {
        return $this->actor->getLogin();
    }

    public function getActorUrl(): string
    {
        return $this->actor->getUrl();
    }

    public function getActorAvatarUrl(): string
    {
        return $this->actor->getAvatarUrl();
    }

    public function getRepo(): RepoDto
    {
        return $this->repo;
    }

    public function getRepoId(): int
    {
        return $this->repo->getId();
    }

    public function getRepoName(): string
    {
        return $this->repo->getName();
    }

    public function getRepoUrl(): string
    {
        return $this->repo->getUrl();
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    private function sanitizeComment(string $comment): string
    {
        $comment = strip_tags(trim($comment));

        return str_replace("\0", '', $comment);
    }

    public function removeNullBytes(mixed $value): mixed
    {
        if (\is_string($value)) {
            return str_replace("\0", '', $value);
        } elseif (\is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->removeNullBytes($v);
            }

            return $value;
        }

        return $value;
    }
}
