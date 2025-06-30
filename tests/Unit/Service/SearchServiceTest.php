<?php

namespace App\Tests\Unit\Service;

use App\Dto\SearchInput;
use App\Entity\Event;
use App\Entity\EventType;
use App\Repository\ReadEventRepository;
use App\Service\SearchService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SearchServiceTest extends TestCase
{
    private ReadEventRepository $repository;
    private DenormalizerInterface $denormalizer;
    private ValidatorInterface $validator;
    private SearchService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ReadEventRepository::class);
        $this->denormalizer = $this->createMock(DenormalizerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->service = new SearchService(
            $this->repository,
            $this->denormalizer,
            $this->validator
        );
    }

    public function testProcessSearchSuccess(): void
    {
        $queryParams = ['date' => '2023-01-01'];
        $searchInput = new SearchInput(new DateTimeImmutable('2023-01-01'));
        
        $events = [
            $this->createMock(Event::class),
            $this->createMock(Event::class)
        ];
        
        $stats = [
            ['hour' => '00', 'type' => EventType::COMMIT, 'count' => 5],
            ['hour' => '01', 'type' => EventType::PULL_REQUEST, 'count' => 3]
        ];
        
        $countByType = [
            EventType::COMMIT => 10,
            EventType::PULL_REQUEST => 5,
            EventType::COMMENT => 3
        ];
        
        $this->denormalizer->expects($this->once())
            ->method('denormalize')
            ->with($queryParams, SearchInput::class, 'array')
            ->willReturn($searchInput);
            
        $this->validator->expects($this->once())
            ->method('validate')
            ->with($searchInput)
            ->willReturn(new ConstraintViolationList());
            
        $this->repository->expects($this->once())
            ->method('countByType')
            ->with($searchInput)
            ->willReturn($countByType);
            
        $this->repository->expects($this->once())
            ->method('countAll')
            ->with($searchInput)
            ->willReturn(18);
            
        $this->repository->expects($this->once())
            ->method('getLatest')
            ->with($searchInput)
            ->willReturn($events);
            
        $this->repository->expects($this->once())
            ->method('statsByTypePerHour')
            ->with($searchInput)
            ->willReturn($stats);
            
        $result = $this->service->processSearch($queryParams);

        $this->assertEquals(18, $result->meta->totalEvents);
        $this->assertEquals(5, $result->meta->totalPullRequests);
        $this->assertEquals(10, $result->meta->totalCommits);
        $this->assertEquals(3, $result->meta->totalComments);
        
        $this->assertSame($events, $result->data->events);
        $this->assertSame($stats, $result->data->stats);
        $this->assertSame($events, $result->data->events);
        $this->assertSame($stats, $result->data->stats);
    }
    
    public function testProcessSearchInvalidDate(): void
    {
        $queryParams = ['date' => 'invalid-date'];
        
        $this->denormalizer->expects($this->once())
            ->method('denormalize')
            ->with($queryParams, SearchInput::class, 'array')
            ->willThrowException(new NotNormalizableValueException('Invalid date format'));
            
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid date format');
        
        $this->service->processSearch($queryParams);
    }
    
    public function testProcessSearchValidationError(): void
    {
        $queryParams = ['date' => '2023-01-01'];
        $searchInput = new SearchInput(new DateTimeImmutable('2023-01-01'));
        
        $errorMessage = "This value should not be null.";
        $violation = $this->createMock(ConstraintViolation::class);
        $violation->expects($this->once())
            ->method('getMessage')
            ->willReturn($errorMessage);
            
        $violationList = new ConstraintViolationList([$violation]);
        
        $this->denormalizer->expects($this->once())
            ->method('denormalize')
            ->with($queryParams, SearchInput::class, 'array')
            ->willReturn($searchInput);
            
        $this->validator->expects($this->once())
            ->method('validate')
            ->with($searchInput)
            ->willReturn($violationList);
            
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage($errorMessage);
        
        $this->service->processSearch($queryParams);
    }
}
