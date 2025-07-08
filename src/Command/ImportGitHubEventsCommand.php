<?php

declare(strict_types=1);

namespace App\Command;

use App\Dto\Input\ImportGitHubEventsInput;
use App\Exception\FlushBatchException;
use App\Repository\WriteEventRepository;
use App\Service\GitHubArchiveStreamer;
use App\Service\GitHubEventProcessor;
use App\Utils\ErrorFileLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:import-github-events')]
class ImportGitHubEventsCommand extends Command
{
    private const int FLUSH_BATCH_SIZE = 50;

    private int $processedCount = 0;

    public function __construct(
        private readonly GitHubArchiveStreamer $archiveStreamer,
        private readonly GitHubEventProcessor $eventProcessor,
        private readonly WriteEventRepository $writeEventRepository,
        private readonly ErrorFileLogger $errorFileLogger,
        private readonly string $dateFormat = 'Y-m-d',
        private readonly string $logFolderName = 'github-import',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Import GitHub events')
            ->addArgument('date', InputArgument::REQUIRED, 'Date in format '.$this->dateFormat)
            ->addOption('hour', null, InputOption::VALUE_REQUIRED, 'Specific hour to import (0-23)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $inputDto = ImportGitHubEventsInput::fromConsoleInput($input, $this->dateFormat);

        $io = new SymfonyStyle($input, $output);

        if (!$inputDto->isValid()) {
            foreach ($inputDto->getValidationErrors() as $error) {
                $io->error($error);
            }

            return Command::INVALID;
        }

        $date = $inputDto->getDate();
        $hour = $inputDto->getHour();

        $io->title('GitHub Events Importer');
        $io->section("Importing events for date: $date".
            ($hour !== null ? ", hour: $hour" : ''));

        $totalImportedByDay = 0;
        $hours              = $hour !== null ? [(int) $hour] : range(0, 23);

        $hasErrors  = false;
        $currentUrl = '';

        foreach ($hours as $currentHour) {
            $url = $this->archiveStreamer->generateArchiveUrl($date, $currentHour);
            $currentUrl = $url;

            $io->section("Processing $url");

            try {
                $totalImportedByHour = 0;

                $events = $this->archiveStreamer->streamGitHubArchive($url);

                foreach ($events as $event) {
                    if ($this->eventProcessor->processRawEvent($event)) {
                        ++$this->processedCount;
                        ++$totalImportedByHour;

                        // Batch flush to optimize performance
                        if ($this->processedCount % self::FLUSH_BATCH_SIZE === 0) {
                            $this->flushBatch($io);
                        }
                    }
                }

                $totalImportedByDay += $totalImportedByHour;

                $io->comment("Processed {$totalImportedByHour} events from {$url}");
            } catch (\Exception $e) {
                $this->errorFileLogger->log($this->logFolderName, $url, $e, ['url' => $url, 'hour' => $currentHour, 'date' => $date]);
                $io->error("Error processing $url: ".$e->getMessage());
                $hasErrors = true;
            }
        }

        try {
            $this->flushBatch($io);

            if ($hasErrors) {
                $io->warning("Import completed with errors! Check logs for details. Total events imported: $totalImportedByDay");
                return Command::FAILURE;
            } else {
                $io->success("Import completed! Total events imported: $totalImportedByDay");
                return Command::SUCCESS;
            }
        } catch (\Exception $e) {
            $this->errorFileLogger->log($this->logFolderName, $currentUrl, $e, ['Error final flush']);
            $io->error('Error in final flush! Check logs for details');

            return Command::FAILURE;
        }
    }

    private function flushBatch(SymfonyStyle $io): void
    {
        try {
            $this->writeEventRepository->flush();
            $this->writeEventRepository->clear();

            $io->write('<fg=yellow;options=bold>★</>');
            $memoryUsage = round(memory_get_usage() / 1024 / 1024, 2);
            $io->write("Memory usage: {$memoryUsage}MB // ");
        } catch (\Exception $exception) {
            $io->error('Error flushing: '.$exception->getMessage());
            throw FlushBatchException::fromPreviousException($exception);
        }
    }
}
