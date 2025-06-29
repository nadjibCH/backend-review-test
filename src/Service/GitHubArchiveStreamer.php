<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Exception\GitHubArchiveStreamingException;
use App\Exception\GitHubEventParsingException;

class GitHubArchiveStreamer
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $githubArchiveBaseUrl
    ) {}

    /**
     * Generates the GitHub archive URL for a given date and hour
     */
    public function generateArchiveUrl(string $date, int $hour): string
    {
        return "{$this->githubArchiveBaseUrl}/{$date}-{$hour}.json.gz";
    }

    /**
     * Downloads and decompresses a GitHub archive, then returns an iterator of events
     * 
     * @param string $url URL of the archive to download
     * @return iterable<array> Iterator of GitHub events
     */
    public function streamGitHubArchive(string $url): iterable
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                $url,
                [
                    'buffer' => false, // For streaming
                    'timeout' => 120,  // 2min
                ]
            );

            $stream = $this->httpClient->stream($response);

            // Initialize inflate to decompress gzip
            $inflate = inflate_init(ZLIB_ENCODING_GZIP);

            $buffer = '';

            foreach ($stream as $chunk) {
                if ($chunk->isTimeout()) {
                    continue;
                }

                // Progressive decompression
                $buffer .= inflate_add($inflate, $chunk->getContent());

                // Process line by line (one event per line)
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);

                    // Ignore empty lines
                    if (trim($line) === '') {
                        continue;
                    }

                    try {
                        $eventData = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                        yield $eventData;
                    } catch (\JsonException $e) {
                        throw GitHubEventParsingException::invalidJson($e);
                    } catch (\Exception $e) {
                        throw GitHubEventParsingException::processingError($e);
                    }
                }
            }
        } catch (TransportExceptionInterface | \Exception $e) {
            throw GitHubArchiveStreamingException::streamingFailed($url, $e);
        }
    }
}
