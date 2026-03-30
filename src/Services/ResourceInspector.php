<?php

declare(strict_types=1);

namespace Survos\InspectionBundle\Services;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;

final class ResourceInspector
{
    public function __construct(
        private readonly ?ResourceNameCollectionFactoryInterface $resourceNames = null,
        private readonly ?ResourceMetadataCollectionFactoryInterface $metadataFactory = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function inspect(string $class): array
    {
        if (!$this->resourceNames || !$this->metadataFactory) {
            return [];
        }

        $resources = $this->resourceNames->create();
        if (!in_array($class, $resources, true)) {
            return [];
        }

        $operations = [];
        foreach ($this->metadataFactory->create($class) as $resourceMetadata) {
            foreach ($resourceMetadata->getOperations() as $operation) {
                $operations[] = [
                    'name' => $operation->getName(),
                    'method' => $operation->getMethod(),
                    'uriTemplate' => $operation->getUriTemplate(),
                ];
            }
        }

        return [
            'class' => $class,
            'operations' => $operations,
        ];
    }
}
