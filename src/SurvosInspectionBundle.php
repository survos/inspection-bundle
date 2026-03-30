<?php

declare(strict_types=1);

namespace Survos\InspectionBundle;

use ApiPlatform\Metadata\UrlGeneratorInterface;
use Survos\InspectionBundle\Controller\InspectionController;
use Survos\InspectionBundle\Services\InspectionService;
use Survos\InspectionBundle\Services\ResourceInspector;
use Survos\InspectionBundle\Twig\TwigExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class SurvosInspectionBundle extends AbstractBundle
{
    protected string $extensionAlias = 'survos_inspection';

    /**
     * @param array<mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->autowire(InspectionService::class)
            ->setPublic(true)
            ->setArgument(
                '$resourceMetadataCollectionFactory',
                new Reference('api_platform.metadata.resource.metadata_collection_factory.cached', ContainerInterface::NULL_ON_INVALID_REFERENCE)
            )
            ->setArgument('$router', new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setAutoconfigured(true);

        $builder->autowire(ResourceInspector::class)
            ->setPublic(true)
            ->setAutoconfigured(true);

        $builder->autowire(InspectionController::class)
            ->setAutoconfigured(true)
            ->addTag('container.service_subscriber')
            ->addTag('controller.service_arguments');

        $builder
            ->setDefinition('survos.inspection_twig', new Definition(TwigExtension::class))
            ->setAutowired(true)
            ->setArgument('$inspectionService', new Reference(InspectionService::class))
            ->setArgument('$iriConverter', new Reference('api_platform.symfony.iri_converter', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$apiUrlGenerator', new Reference(UrlGeneratorInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->addTag('twig.extension')
            ->setPublic(false);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
            ->booleanNode('debug')->defaultValue(false)->end()
            ->end();
    }
}
