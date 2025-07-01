<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Exception\GitHubArchiveStreamingException;
use App\Exception\GitHubEventParsingException;
use App\Service\GitHubArchiveStreamer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class GitHubArchiveStreamerTest extends TestCase
{
    private const string GITHUB_ARCHIVE_BASE_URL = 'https://data.gharchive.org';
    private const string TEST_DATE = '2023-01-01';
    private const int TEST_HOUR = 5;

    private HttpClientInterface&MockObject $httpClient;
    
    private GitHubArchiveStreamer $streamer;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->streamer = new GitHubArchiveStreamer(
            $this->httpClient,
            self::GITHUB_ARCHIVE_BASE_URL
        );
    }

    /**
     * @test
     */
    public function it_should_generate_correct_archive_url(): void
    {
        $expectedUrl = 'https://data.gharchive.org/2023-01-01-5.json.gz';
        $actualUrl = $this->streamer->generateArchiveUrl(self::TEST_DATE, self::TEST_HOUR);
        
        $this->assertEquals($expectedUrl, $actualUrl);
    }

    /**
     * @test
     */
    public function it_should_stream_and_parse_github_archive(): void
    {
        $url = 'https://data.gharchive.org/2023-01-01-5.json.gz';
        
        // Mock response
        $response = $this->createMock(ResponseInterface::class);
        
        // Mock stream
        $responseStream = $this->createMock(ResponseStreamInterface::class);
        
        // Mock chunks with gzipped content
        $chunk1 = $this->createMock(ChunkInterface::class);
        $chunk1->method('isTimeout')->willReturn(false);
        $chunk1->method('getContent')->willReturn(gzencode('{"id":1,"type":"PushEvent","created_at":"2023-01-01T05:00:00Z","actor":{"id":123,"login":"user1","url":"https://api.github.com/users/user1","avatar_url":"https://avatars.githubusercontent.com/u/123"},"repo":{"id":456,"name":"user1/repo1","url":"https://api.github.com/repos/user1/repo1"},"payload":{}}' . "\n"));
        
        $chunk2 = $this->createMock(ChunkInterface::class);
        $chunk2->method('isTimeout')->willReturn(false);
        $chunk2->method('getContent')->willReturn(gzencode('{"id":2,"type":"IssueCommentEvent","created_at":"2023-01-01T05:01:00Z","actor":{"id":124,"login":"user2","url":"https://api.github.com/users/user2","avatar_url":"https://avatars.githubusercontent.com/u/124"},"repo":{"id":457,"name":"user2/repo2","url":"https://api.github.com/repos/user2/repo2"},"payload":{"comment":{"body":"This is a comment"}}}' . "\n"));
        
        // Configure mocks
        $this->httpClient->method('request')
            ->with('GET', $url, ['buffer' => false, 'timeout' => 120])
            ->willReturn($response);
        
        $responseStream->method('current')->willReturnOnConsecutiveCalls($chunk1, $chunk2);
        $responseStream->method('valid')->willReturnOnConsecutiveCalls(true, true, false);
        $responseStream->method('next');
        
        $this->httpClient->method('stream')
            ->with($response)
            ->willReturn($responseStream);
        
        // Execute and verify
        $events = iterator_to_array($this->streamer->streamGitHubArchive($url));
        
        $this->assertCount(2, $events);
        $this->assertEquals(1, $events[0]['id']);
        $this->assertEquals('PushEvent', $events[0]['type']);
        $this->assertEquals(2, $events[1]['id']);
        $this->assertEquals('IssueCommentEvent', $events[1]['type']);
    }

    /**
     * @test
     */
    public function it_should_throw_exception_on_invalid_json(): void
    {
        $url = 'https://data.gharchive.org/2023-01-01-5.json.gz';
        
        // Mock response
        $response = $this->createMock(ResponseInterface::class);
        
        // Mock stream
        $responseStream = $this->createMock(ResponseStreamInterface::class);
        
        // Mock chunk with invalid JSON
        $chunk = $this->createMock(ChunkInterface::class);
        $chunk->method('isTimeout')->willReturn(false);
        $chunk->method('getContent')->willReturn(gzencode('{"id":1,"type":"PushEvent"' . "\n")); // Invalid JSON
        
        // Configure mocks
        $this->httpClient->method('request')
            ->with('GET', $url, ['buffer' => false, 'timeout' => 120])
            ->willReturn($response);
        
        $responseStream->method('current')->willReturn($chunk);
        $responseStream->method('valid')->willReturnOnConsecutiveCalls(true, false);
        $responseStream->method('next');
        
        $this->httpClient->method('stream')
            ->with($response)
            ->willReturn($responseStream);
        
        // Execute and verify
        $this->expectException(GitHubArchiveStreamingException::class);
        iterator_to_array($this->streamer->streamGitHubArchive($url));
    }

    /**
     * @test
     */
    public function it_should_throw_exception_on_transport_error(): void
    {
        $url = 'https://data.gharchive.org/2023-01-01-5.json.gz';
        
        // Mock transport exception
        $transportException = new class extends \Exception implements TransportExceptionInterface {};
        
        // Configure mock to throw exception
        $this->httpClient->method('request')
            ->with('GET', $url, ['buffer' => false, 'timeout' => 120])
            ->willThrowException($transportException);
        
        // Execute and verify
        $this->expectException(GitHubArchiveStreamingException::class);
        iterator_to_array($this->streamer->streamGitHubArchive($url));
    }
}
