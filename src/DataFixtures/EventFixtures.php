<?php

namespace App\DataFixtures;

use App\Entity\Actor;
use App\Entity\Event;
use App\Entity\EventType;
use App\Entity\Repo;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class EventFixtures extends Fixture
{
    public const int EVENT_1_ID = 1;
    public const int ACTOR_1_ID = 1;
    public const int REPO_1_ID = 1;

    public function load(ObjectManager $manager): void
    {
        $event = new Event(
            self::EVENT_1_ID,
            EventType::COMMENT,
            new Actor(
                self::ACTOR_1_ID,
                'jdoe',
                'https://api.github.com/users/jdoe',
                'https://avatars.githubusercontent.com/u/1?'
            ),
            new Repo(
                self::REPO_1_ID,
                'yousign/test',
                'https://api.github.com/repos/yousign/backend-test'
            ),
            [],
            new \DateTimeImmutable(),
            'Test comment initiate by fixture '
        );

        $manager->persist($event);
        $manager->flush();
    }
}
