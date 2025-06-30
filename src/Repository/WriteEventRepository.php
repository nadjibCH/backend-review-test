<?php

namespace App\Repository;

use App\Dto\EventCommentInput;
use App\Entity\Actor;
use App\Entity\Event;
use App\Entity\Repo;

interface WriteEventRepository
{
    public function update(EventCommentInput $authorInput, int $id): void;
    public function persist(Event $event, bool $flush = true): void;
    public function flush(): void;
    public function clear(): void;
    public function findOrCreateActor(int $id, string $login, string $url, string $avatarUrl): Actor;
    public function findOrCreateRepo(int $id, string $name, string $url): Repo;
}
