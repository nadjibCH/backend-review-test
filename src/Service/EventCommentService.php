<?php

namespace App\Service;

use App\Dto\EventCommentInput;
use App\Repository\ReadEventRepository;
use App\Repository\WriteEventRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;


readonly class EventCommentService
{
    public function __construct(
        private WriteEventRepository $writeEventRepository,
        private ReadEventRepository $readEventRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {}

    public function processEventCommentUpdate(string $content, int $eventId): void
    {
        if (! $this->readEventRepository->exist($eventId)) {
            throw new NotFoundHttpException("Event identified by $eventId not found !");
        }

        try {
            $input = $this->serializer->deserialize($content, EventCommentInput::class, 'json');
        } catch (NotEncodableValueException $e) {
            throw new BadRequestHttpException('Invalid JSON format');
        }

        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new BadRequestHttpException($errors[0]->getMessage());
        }

        $this->writeEventRepository->update($input, $eventId);
    }
}
