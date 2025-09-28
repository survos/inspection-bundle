<?php

namespace Survos\InspectionBundle\Services;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;

use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;

    class ResourceInspector
{
    public function __construct(
        private ?ResourceNameCollectionFactoryInterface      $resourceNames=null,
        private ?ResourceMetadataCollectionFactoryInterface $metadataFactory=null
    ) {}

    public function inspect(string $class): void
    {
        if (!$this->metadataFactory) {
            throw new \RuntimeException("MetadataFactory not set, run\ncomposer install api-platform/core");
        }
        // 1. Get all resource classes known to API Platform
        $allResources = $this->resourceNames->create();

//        if (!in_array($class, $allResources, true)) {
//            throw new \InvalidArgumentException("$class is not an API Resource");
//        }

        // 2. Fetch the merged metadata collection for that class
        /** @var ResourceMetadataCollection $metadatas */
        $metadatas = $this->metadataFactory->create($class);
//        dd($metadatas);

        // 3. Iterate each ResourceMetadata (one per “operation type”)
        foreach ($metadatas as $resourceMetadata) {
            // Common getters on ResourceMetadata:
            $shortName            = $resourceMetadata->getShortName();
            $description          = $resourceMetadata->getDescription();
            $uriTemplate          = $resourceMetadata->getUriTemplate();
            $input                = $resourceMetadata->getInput();  // DTO/input class
            $output               = $resourceMetadata->getOutput(); // DTO/output class
            $normalizationContext = $resourceMetadata->getNormalizationContext();
            // …plus:
            foreach ($resourceMetadata->getOperations() as $operation) {
                if ($operation::class === GetCollection::class) {
//                    dd($operation->getNormalizationContext());
                }
                // e.g. HttpOperation with ->getMethod(), ->getName(), ->getSecurity(), etc.
            }
        }
    }
}
