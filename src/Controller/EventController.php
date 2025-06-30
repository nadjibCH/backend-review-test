<?php

namespace App\Controller;

use App\Service\EventCommentService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class EventController
{
    public function __construct(
        private EventCommentService $eventCommentService
    ) {}

    /**
     * Update an event's comment
     * 
     * @Route(path="/api/events/{id}/comment", name="api_event_comment", methods={"PATCH"})
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $this->eventCommentService->processEventCommentUpdate($request->getContent(), $id);
            return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(['message' => $e->getMessage()], JsonResponse::HTTP_NOT_FOUND);
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(['message' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        } catch (\Doctrine\DBAL\Exception $e) {
            return new JsonResponse(['message' => 'Service unavailable'], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}
