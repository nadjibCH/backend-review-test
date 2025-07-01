<?php

namespace App\Tests\Func;

use App\DataFixtures\EventFixtures;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class EventControllerTest extends WebTestCase
{
    protected AbstractDatabaseTool $databaseTool;
    protected static KernelBrowser $client;

    protected function setUp(): void
    {
        static::$client = static::createClient();

        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $metaData = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->updateSchema($metaData);

        $this->databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $this->databaseTool->loadFixtures(
            [EventFixtures::class]
        );
    }

    public function testUpdateShouldReturnEmptyResponse(): void
    {
        $client = static::$client;

        $client->request(
            'PATCH',
            sprintf('/api/events/%d/comment', EventFixtures::EVENT_1_ID),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['comment' => 'It‘s a test comment !!!!!!!!!!!!!!!!!!!!!!!!!!!']) ?: ''
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }


    public function testUpdateShouldReturnHttpNotFoundResponse(): void
    {
        $client = static::$client;

        $client->request(
            'PATCH',
            sprintf('/api/events/%d/comment', 7897897897),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['comment' => 'It‘s a test comment !!!!!!!!!!!!!!!!!!!!!!!!!!!']) ?: ''
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $expectedJson = <<<JSON
              {
                "message":"Event identified by 7897897897 not found !"
              }
            JSON;

        $content = $client->getResponse()->getContent();
        self::assertJsonStringEqualsJsonString($expectedJson, $content === false ? '' : $content);
    }

    /**
     * @dataProvider providePayloadViolations
     */
    public function testUpdateShouldReturnBadRequest(string $payload, string $expectedResponse): void
    {
        $client = static::$client;

        $client->request(
            'PATCH',
            sprintf('/api/events/%d/comment', EventFixtures::EVENT_1_ID),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $payload
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $content = $client->getResponse()->getContent();
        self::assertJsonStringEqualsJsonString($expectedResponse, $content === false ? '' : $content);

    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public function providePayloadViolations(): iterable
    {
        yield 'comment too short' => [
            <<<JSON
              {
                "comment": "short"
                
            }
            JSON,
            <<<JSON
                {
                    "message": "This value is too short. It should have 20 characters or more."
                }
            JSON
        ];
    }
}