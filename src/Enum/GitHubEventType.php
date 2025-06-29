<?php

declare(strict_types=1);

namespace App\Enum;

use App\Entity\EventType;

enum GitHubEventType: string
{
    case PUSH = 'PushEvent';
    case ISSUE_COMMENT = 'IssueCommentEvent';
    case COMMIT_COMMENT = 'CommitCommentEvent';
    case PULL_REQUEST_REVIEW_COMMENT = 'PullRequestReviewCommentEvent';
    case PULL_REQUEST = 'PullRequestEvent';

    /**
     * Maps GitHub event type to internal EventType
     */
    public function toEventType(): ?string
    {
        return match($this) {
            self::PUSH => EventType::COMMIT,
            self::ISSUE_COMMENT, self::COMMIT_COMMENT, self::PULL_REQUEST_REVIEW_COMMENT => EventType::COMMENT,
            self::PULL_REQUEST => EventType::PULL_REQUEST,
            default => null,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function fromString(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
