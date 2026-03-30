<?php

declare(strict_types=1);

namespace Survos\InspectionBundle\Services;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class InspectionService
{
    public function __construct(
        private readonly ?ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory = null,
        private readonly ?RouterInterface $router = null,
    ) {
    }

    /**
     * @return array<string, array{opName: string, uriVariables: array<mixed>, uriTemplate: ?string, method: ?string}>
     */
    public function getAllUrlsForResource(object|string $resourceClass): array
    {
        $resourceClass = $this->normalizeClassName($resourceClass);
        if (!$resourceClass || !$this->resourceMetadataCollectionFactory) {
            return [];
        }

        $routes = [];
        foreach ($this->resourceMetadataCollectionFactory->create($resourceClass) as $resourceMetadata) {
            foreach ($resourceMetadata->getOperations() as $operation) {
                if (!$this->isCollectionOperation($operation)) {
                    continue;
                }

                $operationName = $operation->getName();
                if (!$operationName) {
                    continue;
                }

                if ($this->router && !$this->router->getRouteCollection()->get($operationName)) {
                    continue;
                }

                $routeSlug = (new AsciiSlugger())->slug($operationName)->toString();
                $routes[$routeSlug] = [
                    'opName' => $operationName,
                    'uriVariables' => $operation->getUriVariables() ?? [],
                    'uriTemplate' => $operation->getUriTemplate(),
                    'method' => $operation->getMethod(),
                ];
            }
        }

        return $routes;
    }

    /**
     * @return list<string>
     */
    public function searchableFields(object|string $resourceClass): array
    {
        return $this->extractFields($resourceClass, false);
    }

    /**
     * @return list<string>
     */
    public function sortableFields(object|string $resourceClass): array
    {
        return $this->extractFields($resourceClass, true);
    }

    /**
     * @return array<string, array{name: string, searchable: bool, sortable: bool}>
     */
    public function defaultColumns(object|string $resourceClass): array
    {
        $resourceClass = $this->normalizeClassName($resourceClass);
        if (!$resourceClass) {
            return [];
        }

        $searchable = $this->searchableFields($resourceClass);
        $sortable = $this->sortableFields($resourceClass);

        $reflection = new \ReflectionClass($resourceClass);
        $propertyNames = array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            $reflection->getProperties()
        );

        $columns = [];
        foreach (array_unique([...$propertyNames, ...$searchable, ...$sortable]) as $name) {
            $columns[$name] = [
                'name' => $name,
                'searchable' => in_array($name, $searchable, true),
                'sortable' => in_array($name, $sortable, true),
            ];
        }

        return $columns;
    }

    private function normalizeClassName(object|string $resourceClass): ?string
    {
        $className = is_object($resourceClass) ? $resourceClass::class : $resourceClass;

        return class_exists($className) ? $className : null;
    }

    private function isCollectionOperation(Operation $operation): bool
    {
        if ($operation instanceof GetCollection) {
            return true;
        }

        $method = strtoupper((string) $operation->getMethod());
        if ('GET' !== $method) {
            return false;
        }

        $uriTemplate = (string) $operation->getUriTemplate();

        return !preg_match('/\{[^}]+\}/', $uriTemplate);
    }

    /**
     * @return list<string>
     */
    private function extractFields(object|string $resourceClass, bool $sortable): array
    {
        $resourceClass = $this->normalizeClassName($resourceClass);
        if (!$resourceClass) {
            return [];
        }

        $metadataFields = $this->extractFieldsFromMetadata($resourceClass, $sortable);
        $legacyFields = $this->extractFieldsFromLegacyApiFilters($resourceClass, $sortable);

        return array_values(array_unique([...$metadataFields, ...$legacyFields]));
    }

    /**
     * @return list<string>
     */
    private function extractFieldsFromMetadata(string $resourceClass, bool $sortable): array
    {
        if (!$this->resourceMetadataCollectionFactory) {
            return [];
        }

        $fields = [];
        foreach ($this->resourceMetadataCollectionFactory->create($resourceClass) as $resourceMetadata) {
            foreach ($resourceMetadata->getOperations() as $operation) {
                $parameters = $operation->getParameters();
                if (!$parameters) {
                    continue;
                }

                foreach ($parameters as $parameterName => $parameter) {
                    if (!$parameter instanceof Parameter) {
                        continue;
                    }

                    if (!$this->isMatchingFilter($parameter->getFilter(), $sortable)) {
                        continue;
                    }

                    $fields = [...$fields, ...$this->parameterProperties($parameterName, $parameter)];
                }

                if ($sortable) {
                    foreach (array_keys($operation->getOrder() ?? []) as $fieldName) {
                        $fields[] = (string) $fieldName;
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($fields)));
    }

    /**
     * @return list<string>
     */
    private function extractFieldsFromLegacyApiFilters(string $resourceClass, bool $sortable): array
    {
        $reflection = new \ReflectionClass($resourceClass);
        $fields = [];

        foreach ($reflection->getAttributes() as $attribute) {
            if (!str_ends_with($attribute->getName(), 'ApiFilter')) {
                continue;
            }

            $arguments = $attribute->getArguments();
            $filterClass = (string) ($arguments[0] ?? $arguments['filterClass'] ?? '');
            if (!$this->isMatchingFilter($filterClass, $sortable)) {
                continue;
            }

            $fields = [...$fields, ...$this->normalizeProperties($arguments['properties'] ?? [])];
        }

        return array_values(array_unique(array_filter($fields)));
    }

    private function isMatchingFilter(mixed $filter, bool $sortable): bool
    {
        $filterClass = is_string($filter)
            ? $filter
            : (is_object($filter) ? $filter::class : '');

        if (!$filterClass) {
            return false;
        }

        if ($sortable) {
            return str_contains($filterClass, 'OrderFilter');
        }

        return str_contains($filterClass, 'SearchFilter')
            || str_contains($filterClass, 'MultiFieldSearchFilter');
    }

    /**
     * @return list<string>
     */
    private function parameterProperties(string $parameterName, Parameter $parameter): array
    {
        $properties = $parameter->getProperties();
        if (is_array($properties) && count($properties)) {
            return $this->normalizeProperties($properties);
        }

        if ($parameter->getProperty()) {
            return [$parameter->getProperty()];
        }

        if (!str_contains($parameterName, ':property')) {
            return [];
        }

        return [];
    }

    /**
     * @param array<mixed> $properties
     *
     * @return list<string>
     */
    private function normalizeProperties(array $properties): array
    {
        if (array_is_list($properties)) {
            return array_values(array_filter(array_map('strval', $properties)));
        }

        return array_values(array_filter(array_map('strval', array_keys($properties))));
    }
}
