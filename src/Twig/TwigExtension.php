<?php

namespace Survos\InspectionBundle\Twig;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Symfony\Routing\IriConverter;
use Survos\CoreBundle\Entity\RouteParametersInterface;
use Survos\InspectionBundle\Services\InspectionService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigFilter;

use function Symfony\Component\String\u;

//use ApiPlatform\Symfony\Routing\IriConverter

class TwigExtension extends AbstractExtension
{
    public function __construct(
        private InspectionService $inspectionService,
        private IriConverter|null $iriConverter = null,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('api_route', [$this, 'apiCollectionRoute']),
            new TwigFunction('api_item_route', [$this, 'apiItemRoute']),
            new TwigFunction('api_subresource_route', [$this, 'apiCollectionSubresourceRoute']),
//            new TwigFunction('sortable_fields', [$this, 'sortableFields']),
            new TwigFunction('searchable_fields', [$this, 'searchableFields']),
            new TwigFunction('search_builder_fields', [$this, 'searchBuilderFields']),

        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('is_array', fn (mixed $s) => is_array($s)),
            new TwigFilter('array_is_list', fn (mixed $s) => is_array($s) && array_is_list($s)),
        ];
    }


    public function sortableFields(string $class): array
    {
        assert(class_exists($class), $class);
        $reflector = new \ReflectionClass($class);
        foreach ($reflector->getAttributes() as $attribute) {
            if (!u($attribute->getName())->endsWith('ApiFilter')) {
                continue;
            }
            $filter = $attribute->getArguments()[0];
            if (u($filter)->endsWith('OrderFilter')) {
                $orderProperties = $attribute->getArguments()['properties'];
                return $orderProperties;
            }
        }
        return [];
    }

    public function searchableFields(string $class): array
    {
        $reflector = new \ReflectionClass($class);
        foreach ($reflector->getAttributes() as $attribute) {
            if (!u($attribute->getName())->endsWith('ApiFilter')) {
                continue;
            }
            $filter = $attribute->getArguments()[0];
            if (u($filter)->endsWith('MultiFieldSearchFilter')) {
                return $attribute->getArguments()['properties'];
            }
        }

        return [];
    }

    private function getIriConverter(): IriConverterInterface
    {
        if (!$this->iriConverter) {
            throw new \LogicException("Install api-platform/core >= 2.7");
        }
        return $this->iriConverter;
    }

    public function apiCollectionRoute($entityOrClass, array $context = []): ?string
    {

        $urls = $this->inspectionService->getAllUrlsForResource($entityOrClass);
        if (count($urls)) {
            $x = $urls[array_key_first($urls)]['opName'];
        } else {
            $x = null;
        }
//        try {
//            // this won't work if there are multiple GetCollection routes
////            $x = $this->inspectionService->getAllUrlsForResource($entityOrClass)[CollectionProvider::class];
////            $x = $this->iriConverter->getIriFromResource($entityOrClass, operation: new GetCollection(), context: $context);
//        } catch (InvalidArgumentException $exception) {
//            dd($exception);
//        }
        return $x;
    }

    public function apiItemRoute($entity)
    {
        $x = $this->getIriConverter()->getIriFromResource($entity);
        return $x;
    }

    public function apiCollectionSubresourceRoute($entityOrClass, RouteParametersInterface $parent): ?string
    {
        //        #[ApiResource(
        //            uriTemplate: '/companies/{companyId}/employees',
        //            uriVariables: [
        //                'companyId' => new Link(fromClass: Company::class, toProperty: 'company'),
        //            ],
        //            operations: [ new GetCollection() ]
        //        )]
        $iri = $this->iriConverter->getIriFromResource($entityOrClass, operation: new GetCollection(), context: $context = [
            'uri_variables' => $parent->getrp(),
        ]);
        return $iri;
    }

    public function searchBuilderFields(string $class, array $normalizedColumns): array
    {
        $columnNumbers = [];
        foreach ($normalizedColumns as $idx => $normalizedColumn) {
            if ($normalizedColumn->searchable) {
                $columnNumbers[] = $idx;
            }
        }
        return $columnNumbers;
    }
}
