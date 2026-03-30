<?php

declare(strict_types=1);

namespace Survos\InspectionBundle\Twig;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface as ApiUrlGeneratorInterface;
use ApiPlatform\Symfony\Routing\IriConverter;
use Survos\CoreBundle\Entity\RouteParametersInterface;
use Survos\InspectionBundle\Services\InspectionService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class TwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly InspectionService $inspectionService,
        private readonly ?IriConverter $iriConverter = null,
        private readonly ?ApiUrlGeneratorInterface $apiUrlGenerator = null,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('api_route', [$this, 'apiCollectionRoute']),
            new TwigFunction('api_item_route', [$this, 'apiItemRoute']),
            new TwigFunction('api_subresource_route', [$this, 'apiCollectionSubresourceRoute']),
            new TwigFunction('sortable_fields', [$this, 'sortableFields']),
            new TwigFunction('searchable_fields', [$this, 'searchableFields']),
            new TwigFunction('api_columns', [$this, 'apiColumns']),
            new TwigFunction('search_builder_fields', [$this, 'searchBuilderFields']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('is_array', static fn (mixed $value): bool => is_array($value)),
            new TwigFilter('array_is_list', static fn (mixed $value): bool => is_array($value) && array_is_list($value)),
        ];
    }

    /**
     * @return list<string>
     */
    public function sortableFields(object|string $class): array
    {
        return $this->inspectionService->sortableFields($class);
    }

    /**
     * @return list<string>
     */
    public function searchableFields(object|string $class): array
    {
        return $this->inspectionService->searchableFields($class);
    }

    /**
     * @return array<string, array{name: string, searchable: bool, sortable: bool}>
     */
    public function apiColumns(object|string $class): array
    {
        return $this->inspectionService->defaultColumns($class);
    }

    public function apiCollectionRoute(object|string $entityOrClass, array $context = []): ?string
    {
        $urls = $this->inspectionService->getAllUrlsForResource($entityOrClass);
        if (!$urls || !$this->apiUrlGenerator) {
            return null;
        }

        $operationName = $urls[array_key_first($urls)]['opName'] ?? null;

        return $operationName
            ? $this->apiUrlGenerator->generate($operationName, $context, ApiUrlGeneratorInterface::ABS_PATH)
            : null;
    }

    public function apiItemRoute(object $entity): ?string
    {
        if (!$this->iriConverter instanceof IriConverterInterface) {
            return null;
        }

        return $this->iriConverter->getIriFromResource($entity);
    }

    public function apiCollectionSubresourceRoute(object|string $entityOrClass, RouteParametersInterface $parent): ?string
    {
        if (!$this->iriConverter instanceof IriConverterInterface) {
            return null;
        }

        return $this->iriConverter->getIriFromResource($entityOrClass, operation: new GetCollection(), context: [
            'uri_variables' => $parent->getrp(),
        ]);
    }

    /**
     * @param list<object> $normalizedColumns
     *
     * @return list<int>
     */
    public function searchBuilderFields(string $class, array $normalizedColumns): array
    {
        $columnNumbers = [];
        foreach ($normalizedColumns as $index => $normalizedColumn) {
            if ($normalizedColumn->searchable) {
                $columnNumbers[] = $index;
            }
        }

        return $columnNumbers;
    }
}
