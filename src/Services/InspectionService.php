<?php

namespace Survos\InspectionBundle\Services;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

class InspectionService
{
    public function __construct(
        private readonly ?ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory=null,
        private readonly ?RouterInterface $router=null,
        private readonly ?UrlGeneratorInterface $urlGenerator=null
    ) {
    }

    public function getAllUrlsForResource(string $resourceClass): array
    {
        if (!$this->resourceMetadataCollectionFactory) {
            throw new \RuntimeException("MetadataFactory not set, run\ncomposer install api-platform/core");
        }

        $urls = [];
        $routes = [];
        // yikes!  Now the route is here.


        foreach ($this->resourceMetadataCollectionFactory->create($resourceClass) as $resourceMetadata) {
            /** @var HttpOperation $operation */
            foreach ($resourceMetadata->getOperations() as $operation) {
                if ($operation::class == GetCollection::class) {
                    // we want the route, not the path
//                    $urls[$operation->getProvider()] = $operation->getName();
                    $route = $this->router->getRouteCollection()->get($operation->getName());
                    // need to slugify routes
                    $routeSlug = (new AsciiSlugger())->slug($operation->getName())->toString();
                    $routes[$routeSlug] = [
                        'opName' => $operation->getName(),
                        'uriVaraibles' => $operation->getUriVariables()
                    ];
                    $urls[$operation->getProvider()] = $operation->getName(); // $route;
//                    continue;

                    $params = [];
                    $provider = $operation->getProvider();
                    $url = $this->urlGenerator->generate($operation->getName(), $params);
                    $urls[$operation->getProvider()] = $url;
//                    dd($url, $operation, $route);

//                    $urls[$operation->getProvider()] = ->getPath();
//                } else {
//                    dump($operation::class);
                }

            }
        }
        return $routes;
//        return $urls;
    }
}
