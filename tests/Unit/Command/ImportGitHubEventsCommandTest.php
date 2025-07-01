<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\ImportGitHubEventsCommand;
use App\Enum\GitHubEventType;
use App\Exception\FlushBatchException;
use App\Exception\GitHubArchiveStreamingException;
use App\Exception\GitHubEventProcessingException;
use App\Repository\WriteEventRepository;
use App\Service\GitHubArchiveStreamer;
use App\Service\GitHubEventProcessor;
use App\Utils\ErrorFileLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ImportGitHubEventsCommandTest extends TestCase
{
    private const string TEST_DATE        = '2023-01-01';
    private const int TEST_HOUR           = 5;
    private const string TEST_URL_PATTERN = 'https://data.gharchive.org/%s-%d.json.gz';

    private GitHubArchiveStreamer&MockObject $archiveStreamer;
    private GitHubEventProcessor&MockObject $eventProcessor;
    private WriteEventRepository&MockObject $writeEventRepository;
    private ErrorFileLogger&MockObject $errorFileLogger;
    private CommandTester $commandTester;
    private ImportGitHubEventsCommand $command;

    protected function setUp(): void
    {
        $this->archiveStreamer      = $this->createMock(GitHubArchiveStreamer::class);
        $this->eventProcessor       = $this->createMock(GitHubEventProcessor::class);
        $this->writeEventRepository = $this->createMock(WriteEventRepository::class);
        $this->errorFileLogger      = $this->createMock(ErrorFileLogger::class);
        $this->errorFileLogger      = $this->createMock(ErrorFileLogger::class);

        $this->command = new ImportGitHubEventsCommand(
            $this->archiveStreamer,
            $this->eventProcessor,
            $this->writeEventRepository,
            $this->errorFileLogger
        );

        $application = new Application();
        $application->add($this->command);

        $this->commandTester = new CommandTester($this->command);
    }

    public function testItShouldConfigureCommandCorrectly(): void
    {
        // Verify command name and description
        $this->assertEquals('app:import-github-events', $this->command->getName());
        $this->assertStringContainsString('Import GitHub events', $this->command->getDescription());

        // Verify command arguments and options
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasArgument('date'));
        $this->assertTrue($definition->hasOption('hour'));

        // Verify argument and option properties
        $dateArgument = $definition->getArgument('date');
        $this->assertTrue($dateArgument->isRequired());

        $hourOption = $definition->getOption('hour');
        $this->assertFalse($hourOption->isArray());
        $this->assertTrue($hourOption->acceptValue());
    }

    public function testItShouldReturnInvalidWhenInputIsInvalid(): void
    {
        // Test with invalid date format
        $exitCode = $this->commandTester->execute([
            'date' => '2015-Mars-01',
        ]);

        $this->assertEquals(Command::INVALID, $exitCode);
        $this->assertStringContainsString('Invalid date format', $this->commandTester->getDisplay());
    }

    public function testItShouldProcessEventsForSpecificHour(): void
    {
        $date = self::TEST_DATE;
        $hour = self::TEST_HOUR;
        $url  = \sprintf(self::TEST_URL_PATTERN, $date, $hour);

        // Mock the archive URL generation
        $this->archiveStreamer->expects($this->once())
            ->method('generateArchiveUrl')
            ->with($date, (int) $hour)
            ->willReturn($url);

        // Mock the event streaming
        $events = [
            ['id' => 1, 'type' => GitHubEventType::PUSH],
            ['id' => 2, 'type' => GitHubEventType::ISSUE_COMMENT],
        ];

        $this->archiveStreamer->expects($this->once())
            ->method('streamGitHubArchive')
            ->with($url)
            ->willReturn($events);

        // Mock event processing
        $this->eventProcessor->expects($this->exactly(2))
            ->method('processRawEvent')
            ->willReturn(true);

        // Mock repository operations
        $this->writeEventRepository->expects($this->once())
            ->method('flush');

        $this->writeEventRepository->expects($this->once())
            ->method('clear');

        $exitCode = $this->commandTester->execute([
            'date'   => $date,
            '--hour' => (string) $hour,
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Total events imported: 2', $this->commandTester->getDisplay());
    }

    public function testShouldProcess24HoursWhenHourNotSpecified(): void
    {
        $date = self::TEST_DATE;

        // Prepare the mocks
        $this->archiveStreamer
            ->expects($this->exactly(24))
            ->method('generateArchiveUrl')
            ->willReturnCallback(function ($date, $hour) {
                return "https://data.gharchive.org/{$date}-{$hour}.json.gz";
            });

        $this->archiveStreamer
            ->expects($this->exactly(24))
            ->method('streamGitHubArchive')
            ->willReturn([
                ['id' => 1, 'type' => GitHubEventType::PUSH],
                ['id' => 2, 'type' => GitHubEventType::ISSUE_COMMENT],
                ['id' => 3, 'type' => GitHubEventType::PULL_REQUEST],
            ]);

        $this->eventProcessor
            ->expects($this->exactly(72))
            ->method('processRawEvent')
            ->willReturn(true);

        $this->writeEventRepository
            ->expects($this->exactly(2))
            ->method('flush');

        $this->writeEventRepository
            ->expects($this->exactly(2))
            ->method('clear');

        // Execute the command without the hour option
        $exitCode = $this->commandTester->execute([
            'date' => $date,
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Import completed', $this->commandTester->getDisplay());
        $this->assertStringContainsString('Total events imported: 72', $this->commandTester->getDisplay());
    }

    public function testItShouldHandleStreamingExceptionsGracefully(): void
    {
        $date = self::TEST_DATE;
        $hour = self::TEST_HOUR;
        $url  = \sprintf(self::TEST_URL_PATTERN, $date, $hour);

        // Mock the archive URL generation
        $this->archiveStreamer->expects($this->once())
            ->method('generateArchiveUrl')
            ->with($date, (int) $hour)
            ->willReturn($url);

        // Mock a streaming exception
        $exception = new GitHubArchiveStreamingException('Failed to stream GitHub archive from URL');
        $this->archiveStreamer->expects($this->once())
            ->method('streamGitHubArchive')
            ->with($url)
            ->willThrowException($exception);

        // No event processing should happen
        $this->eventProcessor->expects($this->never())
            ->method('processRawEvent');

        $this->errorFileLogger->expects($this->once())
            ->method('log');

        $exitCode = $this->commandTester->execute([
            'date'   => $date,
            '--hour' => (string) $hour,
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Error processing', $this->commandTester->getDisplay());
        $this->assertStringContainsString('Failed to stream', $this->commandTester->getDisplay());
    }

    public function testItShouldHandleEventProcessingExceptionsGracefully(): void
    {
        $date = self::TEST_DATE;
        $hour = self::TEST_HOUR;
        $url  = \sprintf(self::TEST_URL_PATTERN, $date, $hour);

        // Mock the archive URL generation
        $this->archiveStreamer->expects($this->once())
            ->method('generateArchiveUrl')
            ->with($date, (int) $hour)
            ->willReturn($url);

        // Mock the event streaming
        $events = [
            ['id' => 1, 'type' => GitHubEventType::PUSH],
            ['id' => 2, 'type' => GitHubEventType::ISSUE_COMMENT],
        ];

        $this->archiveStreamer->expects($this->once())
            ->method('streamGitHubArchive')
            ->with($url)
            ->willReturn($events);

        // Mock event processing with an exception
        $this->eventProcessor->expects($this->once())
            ->method('processRawEvent')
            ->willThrowException(new GitHubEventProcessingException('Failed to process event'));

        $this->writeEventRepository->expects($this->once())
            ->method('flush');

        $this->writeEventRepository->expects($this->once())
            ->method('clear');

        $this->errorFileLogger->expects($this->once())
            ->method('log');

        $exitCode = $this->commandTester->execute([
            'date'   => $date,
            '--hour' => (string) $hour,
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Import completed', $this->commandTester->getDisplay());
        $this->assertStringContainsString('0', $this->commandTester->getDisplay());
    }

    public function testItShouldThrowFlushBatchExceptionWhenFlushFails(): void
    {
        $date = self::TEST_DATE;
        $hour = self::TEST_HOUR;
        $url  = \sprintf(self::TEST_URL_PATTERN, $date, $hour);

        // Mock the archive URL generation
        $this->archiveStreamer->expects($this->once())
            ->method('generateArchiveUrl')
            ->with($date, (int) $hour)
            ->willReturn($url);

        // Mock the event streaming
        $events = [
            ['id' => 1, 'type' => GitHubEventType::PUSH],
        ];

        $this->archiveStreamer->expects($this->once())
            ->method('streamGitHubArchive')
            ->with($url)
            ->willReturn($events);

        // Mock event processing
        $this->eventProcessor->expects($this->once())
            ->method('processRawEvent')
            ->willReturn(true);

        // Mock repository flush to throw an exception
        $exception = new \Exception('Database error');
        $this->writeEventRepository->expects($this->once())
            ->method('flush')
            ->willThrowException($exception);

        $this->expectException(FlushBatchException::class);
        $this->expectExceptionMessage('Error while flushing data by batch to database');

        $this->commandTester->execute([
            'date'   => $date,
            '--hour' => (string) $hour,
        ]);
    }

    public function testItShouldBatchFlushWhenProcessedCountReachesBatchSize(): void
    {
        $date = self::TEST_DATE;
        $hour = self::TEST_HOUR;
        $url  = \sprintf(self::TEST_URL_PATTERN, $date, $hour);

        // Mock the archive URL generation
        $this->archiveStreamer->expects($this->once())
            ->method('generateArchiveUrl')
            ->with($date, (int) $hour)
            ->willReturn($url);

        // Generate 51 events to trigger a batch flush
        $events = [];
        for ($i = 1; $i <= 51; ++$i) {
            $events[] = ['id' => $i, 'type' => GitHubEventType::PUSH];
        }

        $this->archiveStreamer->expects($this->once())
            ->method('streamGitHubArchive')
            ->with($url)
            ->willReturn($events);

        // Mock event processing
        $this->eventProcessor->expects($this->exactly(51))
            ->method('processRawEvent')
            ->willReturn(true);

        // Should flush twice: once at batch size and once at the end
        $this->writeEventRepository->expects($this->exactly(2))
            ->method('flush');

        $this->writeEventRepository->expects($this->exactly(2))
            ->method('clear');

        $exitCode = $this->commandTester->execute([
            'date'   => $date,
            '--hour' => (string) $hour,
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Import completed', $this->commandTester->getDisplay());
        $this->assertStringContainsString('51', $this->commandTester->getDisplay());
    }
}
