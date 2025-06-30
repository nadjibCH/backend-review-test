<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\GitHubEventDto;
use App\Entity\Event;
use App\Enum\GitHubEventType;
use App\Exception\GitHubEventProcessingException;
use App\Repository\ReadEventRepository;
use App\Repository\WriteEventRepository;

class GitHubEventProcessor
{
    public function __construct(
        private readonly WriteEventRepository $writeEventRepository,
        private readonly ReadEventRepository $readEventRepository
    ) {
    }

    /**
     * Converts a GitHub event type to internal EventType
     */
    public function mapGitHubEventTypeToEventType(string $githubEventType): ?string
    {
        $type = GitHubEventType::fromString($githubEventType);
        return $type?->toEventType();
    }

    public function processRawEvent(array $rawEvent): bool
    {
        $eventDto = new GitHubEventDto($rawEvent);

        if (!in_array($eventDto->getType(), GitHubEventType::values())) {
            return false;
        }

        // Check if the event already exists
        if ($this->readEventRepository->exist($eventDto->getId())) {
            return false;
        }

        // Get the mapped event type
        $eventType = $this->mapGitHubEventTypeToEventType($eventDto->getType());
        if ($eventType === null) {
            return false;
        }

        try {
            $actor = $this->writeEventRepository->findOrCreateActor(
                $eventDto->getActorId(),
                $eventDto->getActorLogin(),
                $eventDto->getActorUrl(),
                $eventDto->getActorAvatarUrl()
            );

            $repo = $this->writeEventRepository->findOrCreateRepo(
                $eventDto->getRepoId(),
                $eventDto->getRepoName(),
                $eventDto->getRepoUrl()
            );

            $event = new Event(
                $eventDto->getId(),
                $eventType,
                $actor,
                $repo,
                $eventDto->getPayload(),
                new \DateTimeImmutable($eventDto->getCreatedAt()),
                $eventDto->getComment()
            );

            $this->writeEventRepository->persist($event, false);

            return true;
        } catch (\Exception $e) {
            throw GitHubEventProcessingException::processingFailed((string)$eventDto->getId(), $e);
        }
    }
}
