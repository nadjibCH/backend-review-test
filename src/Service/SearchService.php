<?php

namespace App\Service;

use App\Dto\Input\SearchInput;
use App\Dto\Output\SearchDataOutput;
use App\Dto\Output\SearchMetaOutput;
use App\Dto\Output\SearchOutput;
use App\Entity\EventType;
use App\Repository\ReadEventRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class SearchService
{
    public function __construct(
        private ReadEventRepository $repository,
        private DenormalizerInterface $denormalizer,
        private ValidatorInterface $validator
    ) {}

    public function processSearch(array $queryParams): SearchOutput
    {
        try {
            /** @var SearchInput $searchInput */
            $searchInput = $this->denormalizer->denormalize(
                $queryParams,
                SearchInput::class,
                'array'
            );

            $errors = $this->validator->validate($searchInput);
            if (count($errors) > 0) {
                throw new BadRequestHttpException($errors[0]->getMessage());
            }

            $countByType = $this->repository->countByType($searchInput);

            $meta = new SearchMetaOutput(
                $this->repository->countAll($searchInput),
                $countByType[EventType::PULL_REQUEST] ?? 0,
                $countByType[EventType::COMMIT] ?? 0,
                $countByType[EventType::COMMENT] ?? 0
            );

            $data = new SearchDataOutput(
                $this->repository->getLatest($searchInput),
                $this->repository->statsByTypePerHour($searchInput)
            );

            return new SearchOutput($meta, $data);
        } catch (NotNormalizableValueException $e) {
            throw new BadRequestHttpException('Invalid date format: ' . $e->getMessage());
        }
    }
}
