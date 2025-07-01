<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\Input\EventCommentInput;
use App\Repository\ReadEventRepository;
use App\Repository\WriteEventRepository;
use App\Service\EventCommentService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EventCommentServiceTest extends TestCase
{
    private ReadEventRepository&MockObject $readEventRepository;
    private WriteEventRepository&MockObject $writeEventRepository;
    private SerializerInterface&MockObject $serializer;
    private ValidatorInterface&MockObject $validator;
    private EventCommentService $service;

    protected function setUp(): void
    {
        $this->writeEventRepository = $this->createMock(WriteEventRepository::class);
        $this->readEventRepository  = $this->createMock(ReadEventRepository::class);
        $this->serializer           = $this->createMock(SerializerInterface::class);
        $this->validator            = $this->createMock(ValidatorInterface::class);

        $this->service = new EventCommentService(
            $this->writeEventRepository,
            $this->readEventRepository,
            $this->serializer,
            $this->validator
        );
    }

    public function testProcessEventCommentUpdateSuccess(): void
    {
        $eventId     = 123;
        $jsonContent = '{"comment": "This is a valid comment with more than 20 characters"}';
        $eventInput  = new EventCommentInput($jsonContent);

        $this->readEventRepository->expects($this->once())
            ->method('exist')
            ->with($eventId)
            ->willReturn(true);

        $this->serializer->expects($this->once())
            ->method('deserialize')
            ->with($jsonContent, EventCommentInput::class, 'json')
            ->willReturn($eventInput);

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($eventInput)
            ->willReturn(new ConstraintViolationList());

        $this->writeEventRepository->expects($this->once())
            ->method('update')
            ->with($eventInput, $eventId);

        $this->service->processEventCommentUpdate($jsonContent, $eventId);

        $this->addToAssertionCount(1);
    }

    public function testProcessEventCommentUpdateEventNotFound(): void
    {
        $eventId     = 456;
        $jsonContent = '{"comment": "This is a valid comment"}';

        $this->readEventRepository->expects($this->once())
            ->method('exist')
            ->with($eventId)
            ->willReturn(false);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage("Event identified by $eventId not found !");

        $this->service->processEventCommentUpdate($jsonContent, $eventId);
    }

    public function testProcessEventCommentUpdateInvalidJson(): void
    {
        $eventId            = 123;
        $invalidJsonContent = '{comment: Invalid JSON}';

        $this->readEventRepository->expects($this->once())
            ->method('exist')
            ->with($eventId)
            ->willReturn(true);

        $this->serializer->expects($this->once())
            ->method('deserialize')
            ->with($invalidJsonContent, EventCommentInput::class, 'json')
            ->willThrowException(new NotEncodableValueException('Invalid JSON format'));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid JSON format');

        $this->service->processEventCommentUpdate($invalidJsonContent, $eventId);
    }

    public function testProcessEventCommentUpdateValidationError(): void
    {
        $eventId     = 123;
        $jsonContent = '{"comment": "short"}';
        $eventInput  = new EventCommentInput($jsonContent);

        $errorMessage = 'This value is too short. It should have 20 characters or more.';
        $violation    = $this->createMock(ConstraintViolation::class);
        $violation->expects($this->once())
            ->method('getMessage')
            ->willReturn($errorMessage);

        $violationList = new ConstraintViolationList([$violation]);

        $this->readEventRepository->expects($this->once())
            ->method('exist')
            ->with($eventId)
            ->willReturn(true);

        $this->serializer->expects($this->once())
            ->method('deserialize')
            ->with($jsonContent, EventCommentInput::class, 'json')
            ->willReturn($eventInput);

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($eventInput)
            ->willReturn($violationList);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage($errorMessage);

        $this->service->processEventCommentUpdate($jsonContent, $eventId);
    }
}
