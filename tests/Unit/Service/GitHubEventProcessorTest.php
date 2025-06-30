<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Actor;
use App\Entity\Event;
use App\Entity\EventType;
use App\Entity\Repo;
use App\Enum\GitHubEventType;
use App\Exception\GitHubEventProcessingException;
use App\Repository\ReadEventRepository;
use App\Repository\WriteEventRepository;
use App\Service\GitHubEventProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GitHubEventProcessorTest extends TestCase
{
    /**
     * @var WriteEventRepository&MockObject
     */
    private $writeEventRepository;
    
    /**
     * @var ReadEventRepository&MockObject
     */
    private $readEventRepository;
    private GitHubEventProcessor $processor;

    protected function setUp(): void
    {
        $this->writeEventRepository = $this->createMock(WriteEventRepository::class);
        $this->readEventRepository = $this->createMock(ReadEventRepository::class);
        $this->processor = new GitHubEventProcessor(
            $this->writeEventRepository,
            $this->readEventRepository
        );
    }

    /**
     * @test
     * @dataProvider eventTypeProvider
     */
    public function it_should_map_github_event_type_to_event_type(string $githubEventType, ?string $expectedEventType): void
    {
        $actualEventType = $this->processor->mapGitHubEventTypeToEventType($githubEventType);
        $this->assertEquals($expectedEventType, $actualEventType);
    }

    public function eventTypeProvider(): array
    {
        return [
            'Push event' => ['PushEvent', EventType::COMMIT],
            'Issue comment event' => ['IssueCommentEvent', EventType::COMMENT],
            'Pull request review comment event' => ['PullRequestReviewCommentEvent', EventType::COMMENT],
            'Commit comment event' => ['CommitCommentEvent', EventType::COMMENT],
            'Pull request event' => ['PullRequestEvent', EventType::PULL_REQUEST],
        ];
    }

    /**
     * @test
     */
    public function it_should_process_valid_event(): void
    {
        $rawEvent = [
            'id' => '12345',
            'type' => GitHubEventType::PUSH->value,
            'created_at' => '2023-01-01T05:00:00Z',
            'actor' => [
                'id' => 123,
                'login' => 'testuser',
                'url' => 'https://api.github.com/users/testuser',
                'avatar_url' => 'https://avatars.githubusercontent.com/u/123'
            ],
            'repo' => [
                'id' => 456,
                'name' => 'testuser/testrepo',
                'url' => 'https://api.github.com/repos/testuser/testrepo'
            ],
            'payload' => ['ref' => 'refs/heads/main']
        ];

        $this->readEventRepository->expects($this->once())
            ->method('exist')
            ->with(12345)
            ->willReturn(false);

        $actor = new Actor(123, 'testuser', 'https://api.github.com/users/testuser', 'https://avatars.githubusercontent.com/u/123');
        $this->writeEventRepository->expects($this->once())
            ->method('findOrCreateActor')
            ->with(123, 'testuser', 'https://api.github.com/users/testuser', 'https://avatars.githubusercontent.com/u/123')
            ->willReturn($actor);

        $repo = new Repo(456, 'testuser/testrepo', 'https://api.github.com/repos/testuser/testrepo');
        $this->writeEventRepository->expects($this->once())
            ->method('findOrCreateRepo')
            ->with(456, 'testuser/testrepo', 'https://api.github.com/repos/testuser/testrepo')
            ->willReturn($repo);

        $this->writeEventRepository->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Event::class), false);

        $result = $this->processor->processRawEvent($rawEvent);
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function it_should_skip_processing_if_event_already_exists(): void
    {
        $rawEvent = [
            'id' => '12345',
            'type' => GitHubEventType::PUSH->value,
            'created_at' => '2023-01-01T05:00:00Z',
            'actor' => [
                'id' => 123,
                'login' => 'testuser',
                'url' => 'https://api.github.com/users/testuser',
                'avatar_url' => 'https://avatars.githubusercontent.com/u/123'
            ],
            'repo' => [
                'id' => 456,
                'name' => 'testuser/testrepo',
                'url' => 'https://api.github.com/repos/testuser/testrepo'
            ],
            'payload' => ['ref' => 'refs/heads/main']
        ];

        $this->readEventRepository->expects($this->once())
            ->method('exist')
            ->with(12345)
            ->willReturn(true);
        $this->writeEventRepository->expects($this->never())->method('findOrCreateActor');
        $this->writeEventRepository->expects($this->never())->method('findOrCreateRepo');
        $this->writeEventRepository->expects($this->never())->method('persist');

        $result = $this->processor->processRawEvent($rawEvent);
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function it_should_skip_processing_if_event_type_is_not_supported(): void
    {
        $rawEvent = [
            'id' => '12345',
            'type' => 'UnsupportedEventType',
            'created_at' => '2023-01-01T05:00:00Z',
            'actor' => [
                'id' => 123,
                'login' => 'testuser',
                'url' => 'https://api.github.com/users/testuser',
                'avatar_url' => 'https://avatars.githubusercontent.com/u/123'
            ],
            'repo' => [
                'id' => 456,
                'name' => 'testuser/testrepo',
                'url' => 'https://api.github.com/repos/testuser/testrepo'
            ],
            'payload' => []
        ];

        $this->readEventRepository->expects($this->never())->method('exist');
        $this->writeEventRepository->expects($this->never())->method('findOrCreateActor');
        $this->writeEventRepository->expects($this->never())->method('findOrCreateRepo');
        $this->writeEventRepository->expects($this->never())->method('persist');

        $result = $this->processor->processRawEvent($rawEvent);
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function it_should_throw_exception_when_processing_fails(): void
    {
        $rawEvent = [
            'id' => '12345',
            'type' => GitHubEventType::PUSH->value,
            'created_at' => '2023-01-01T05:00:00Z',
            'actor' => [
                'id' => 123,
                'login' => 'testuser',
                'url' => 'https://api.github.com/users/testuser',
                'avatar_url' => 'https://avatars.githubusercontent.com/u/123'
            ],
            'repo' => [
                'id' => 456,
                'name' => 'testuser/testrepo',
                'url' => 'https://api.github.com/repos/testuser/testrepo'
            ],
            'payload' => ['ref' => 'refs/heads/main']
        ];

        $this->readEventRepository->expects($this->once())
            ->method('exist')
            ->with(12345)
            ->willReturn(false);

        $exception = new \Exception('Database error');
        $this->writeEventRepository->expects($this->once())
            ->method('findOrCreateActor')
            ->willThrowException($exception);

        $this->expectException(GitHubEventProcessingException::class);
        $this->expectExceptionMessage('Failed to process GitHub event with ID: 12345');
        $this->processor->processRawEvent($rawEvent);
    }

    /**
     * @test
     */
    public function it_should_process_comment_event_with_comment_data(): void
    {
        $rawEvent = [
            'id' => '12345',
            'type' => GitHubEventType::ISSUE_COMMENT->value,
            'created_at' => '2023-01-01T05:00:00Z',
            'actor' => [
                'id' => 123,
                'login' => 'testuser',
                'url' => 'https://api.github.com/users/testuser',
                'avatar_url' => 'https://avatars.githubusercontent.com/u/123'
            ],
            'repo' => [
                'id' => 456,
                'name' => 'testuser/testrepo',
                'url' => 'https://api.github.com/repos/testuser/testrepo'
            ],
            'payload' => [
                'comment' => [
                    'body' => 'This is a test comment'
                ]
            ]
        ];

        $this->readEventRepository->expects($this->once())
            ->method('exist')
            ->with(12345)
            ->willReturn(false);

        $actor = new Actor(123, 'testuser', 'https://api.github.com/users/testuser', 'https://avatars.githubusercontent.com/u/123');
        $this->writeEventRepository->expects($this->once())
            ->method('findOrCreateActor')
            ->willReturn($actor);

        $repo = new Repo(456, 'testuser/testrepo', 'https://api.github.com/repos/testuser/testrepo');
        $this->writeEventRepository->expects($this->once())
            ->method('findOrCreateRepo')
            ->willReturn($repo);

        $this->writeEventRepository->expects($this->once())
            ->method('persist')
            ->with(
                $this->callback(function (Event $event) {
                    return $event->type() === EventType::COMMENT && 
                           isset($event->payload()['comment']['body']) && 
                           $event->payload()['comment']['body'] === 'This is a test comment';
                }),
                false
            );

        $result = $this->processor->processRawEvent($rawEvent);
        $this->assertTrue($result);
    }
}
