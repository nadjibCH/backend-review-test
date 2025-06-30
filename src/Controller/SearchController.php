<?php

namespace App\Controller;

use App\Service\SearchService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\SerializerInterface;

readonly class SearchController
{
    public function __construct(
        private SearchService $searchService,
        private SerializerInterface $serializer
    ) {}

    /**
     * @Route(path="/api/search", name="api_search", methods={"GET"})
     */
    public function searchCommits(Request $request): JsonResponse
    {
        try {
            $output = $this->searchService->processSearch($request->query->all());
            
            return new JsonResponse(
                $this->serializer->serialize($output, 'json'),
                Response::HTTP_OK,
                [],
                true
            );
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Throwable $e) {
            return new JsonResponse(
                ['error' => 'Internal server error'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
