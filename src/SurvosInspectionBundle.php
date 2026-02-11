<?php

namespace Survos\InspectionBundle;

use Survos\InspectionBundle\Controller\InspectionController;
use Survos\InspectionBundle\Services\InspectionService;
use Survos\InspectionBundle\Services\ResourceInspector;
use Survos\InspectionBundle\Twig\TwigExtension;
use Survos\WorkflowBundle\Service\WorkflowHelperService;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class SurvosInspectionBundle extends AbstractBundle
{
    protected string $extensionAlias = 'survos_inspection';

    /**
     * @param array<mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->autowire( InspectionService::class)
            ->setPublic(true)
            ->setArgument('$resourceMetadataCollectionFactory',
                new Reference('api_platform.metadata.resource.metadata_collection_factory.cached', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$router', new Reference('router'))
            ->setAutoconfigured(true)
        ;

        $builder->autowire( ResourceInspector::class)
            ->setPublic(true)
            ->setAutoconfigured(true)
//            ->setArgument('$resourceMetadataCollectionFactory', new Reference('api_platform.metadata.resource.metadata_collection_factory.cached'))
//            ->setArgument('$router', new Reference('router'))
            ->setAutoconfigured(true)
        ;

        // idea: extend SurvosAbstractBundle
        array_map(fn(string $controllerClass) => $builder->autowire($controllerClass)
            ->setAutoconfigured(true)
            ->addTag('container.service_subscriber')
            ->addTag('controller.service_arguments'),
        [
            InspectionController::class
        ]);

        $definition = $builder
            ->setDefinition('survos.inspection_twig', new Definition(TwigExtension::class))
            ->addTag('twig.extension')
            ->setArgument('$inspectionService', new Reference(InspectionService::class))
            ->setPublic(false);

        $definition
            ->setArgument('$iriConverter',
                new Reference('api_platform.symfony.iri_converter', ContainerInterface::NULL_ON_INVALID_REFERENCE)
            );
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        // since the configuration is short, we can add it here
        $definition->rootNode()
            ->children()
            ->booleanNode('debug')->defaultValue(false)->end()
            ->end();
    }
}
