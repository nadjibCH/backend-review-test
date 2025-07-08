<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\Input\SearchInput;
use App\Entity\EventType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;

class DbalReadEventRepository implements ReadEventRepository
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Calculate the start and end of the day for a given date to use the created_at index.
     */
    private function getDayBoundaries(\DateTimeImmutable $date): array
    {
        return [
            'start' => $date->setTime(0, 0, 0),
            'end'   => $date->setTime(23, 59, 59),
        ];
    }

    /**
     * @throws Exception
     */
    public function countAll(SearchInput $searchInput): int
    {
        ['start' => $start, 'end' => $end] = $this->getDayBoundaries($searchInput->date);

        $sql = <<<SQL
        SELECT sum(count) as count
        FROM event
        WHERE created_at BETWEEN :start AND :end
        AND payload::text like :keyword
SQL;

        return (int) $this->connection->fetchOne(
            $sql,
            [
                'start'   => $start,
                'end'     => $end,
                'keyword' => '%'.$searchInput->keyword.'%',
            ],
            [
                'start'   => Types::DATETIME_IMMUTABLE,
                'end'     => Types::DATETIME_IMMUTABLE,
                'keyword' => Types::STRING,
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function countByType(SearchInput $searchInput): array
    {
        ['start' => $start, 'end' => $end] = $this->getDayBoundaries($searchInput->date);

        $sql = <<<'SQL'
            SELECT type, sum(count) as count
            FROM event
            WHERE created_at BETWEEN :start AND :end
            AND payload::text like :keyword
            GROUP BY type
SQL;

        return $this->connection->fetchAllKeyValue(
            $sql,
            [
                'start'   => $start,
                'end'     => $end,
                'keyword' => '%'.$searchInput->keyword.'%',
            ],
            [
                'start'   => Types::DATETIME_IMMUTABLE,
                'end'     => Types::DATETIME_IMMUTABLE,
                'keyword' => Types::STRING,
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function statsByTypePerHour(SearchInput $searchInput): array
    {
        ['start' => $start, 'end' => $end] = $this->getDayBoundaries($searchInput->date);

        $sql = <<<SQL
            SELECT extract(hour from created_at) as hour, type, sum(count) as count
            FROM event
            WHERE created_at BETWEEN :start AND :end
            AND payload::text like :keyword
            GROUP BY TYPE, EXTRACT(hour from created_at)
SQL;

        $stats = $this->connection->fetchAllAssociative(
            $sql,
            [
                'start'   => $start,
                'end'     => $end,
                'keyword' => '%'.$searchInput->keyword.'%',
            ],
            [
                'start'   => Types::DATETIME_IMMUTABLE,
                'end'     => Types::DATETIME_IMMUTABLE,
                'keyword' => Types::STRING,
            ]
        );

        $data = array_fill(0, 24, [EventType::COMMIT => 0, EventType::PULL_REQUEST => 0, EventType::COMMENT => 0]);

        foreach ($stats as $stat) {
            $data[(int) $stat['hour']][$stat['type']] = $stat['count'];
        }

        return $data;
    }

    /**
     * @throws Exception
     */
    public function getLatest(SearchInput $searchInput): array
    {
        ['start' => $start, 'end' => $end] = $this->getDayBoundaries($searchInput->date);

        $sql = <<<SQL
            SELECT e.type, r.name AS repo, e.payload::text AS payload
            FROM event e
            JOIN repo r ON e.repo_id = r.id
            WHERE e.created_at BETWEEN :start AND :end
            AND e.payload::text LIKE :keyword
            ORDER BY created_at DESC
            LIMIT 10
SQL;

        return $this->connection->fetchAllAssociative(
            $sql,
            [
                'start'   => $start,
                'end'     => $end,
                'keyword' => '%'.$searchInput->keyword.'%',
            ],
            [
                'start'   => Types::DATETIME_IMMUTABLE,
                'end'     => Types::DATETIME_IMMUTABLE,
                'keyword' => Types::STRING,
            ]
        );

        // no need, because repo.name is returned
        /*return array_map(static function ($item) {
            $item['repo'] = json_decode($item['repo'], true);
            return $item;
        }, $result);*/
    }

    /**
     * @throws Exception
     */
    public function exist(int $id): bool
    {
        $sql = <<<SQL
            SELECT 1
            FROM event
            WHERE id = :id
        SQL;

        $result = $this->connection->fetchOne($sql, [
            'id' => $id,
        ]);

        return (bool) $result;
    }
}
