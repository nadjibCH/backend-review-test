<?php

namespace App\Repository;

use App\Dto\EventInput;
use App\Entity\Event;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Actor;
use App\Entity\Repo;

class DbalWriteEventRepository implements WriteEventRepository
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;

    // Cache for actors and repos to avoid multiple database lookups
    private array $actorCache = [];
    private array $repoCache = [];

    public function __construct(
        Connection $connection,
        EntityManagerInterface $entityManager
    ) {
        $this->connection = $connection;
        $this->entityManager = $entityManager;
    }

    public function update(EventInput $authorInput, int $id): void
    {
        $sql = <<<SQL
        UPDATE event
        SET comment = :comment
        WHERE id = :id
SQL;

        $this->connection->executeQuery($sql, ['id' => $id, 'comment' => $authorInput->comment]);
    }

    public function persist(Event $event, bool $flush = true): void
    {
        $this->entityManager->persist($event);
        if ($flush) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }

    /**
     * Clears the caches and the EntityManager
     */
    public function clear(): void
    {
        $this->actorCache = [];
        $this->repoCache = [];
        $this->entityManager->clear();
    }

    /**
     * Finds or creates an actor
     */
    public function findOrCreateActor(int $id, string $login, string $url, string $avatarUrl): Actor
    {
        // First check the in-memory cache
        if (isset($this->actorCache[$id])) {
            return $this->actorCache[$id];
        }

        // Try to find the existing actor
        $actor = $this->entityManager->find(Actor::class, $id);
        if (!$actor) {
            $actor = new Actor($id, $login, $url, $avatarUrl);
            $this->entityManager->persist($actor);
        }
        $this->actorCache[$id] = $actor;
        return $actor;
    }

    /**
     * Finds or creates a repository
     */
    public function findOrCreateRepo(int $id, string $name, string $url): Repo
    {
        // First check the in-memory cache
        if (isset($this->repoCache[$id])) {
            return $this->repoCache[$id];
        }

        // Try to find the existing repo in the DB
        $repo = $this->entityManager->find(Repo::class, $id);

        if (!$repo) {
            $repo = new Repo($id, $name, $url);
            $this->entityManager->persist($repo);
        }

        $this->repoCache[$id] = $repo;
        return $repo;
    }
}
