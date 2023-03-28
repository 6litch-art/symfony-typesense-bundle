<?php

namespace Typesense\Bundle\DependencyInjection\Compiler;

use Base\Annotations\AnnotationReader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Workflow\Registry;
use Typesense\Bundle\Client\TypesenseClient;
use Typesense\Bundle\Manager\TypesenseManager;
use Typesense\Bundle\Typesense;

class FinderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // always first check if the primary service is defined
        if (!$container->has(TypesenseManager::class)) {
            return;
        }

        $definition     = $container->findDefinition(TypesenseManager::class);
        $taggedServices = $container->findTaggedServiceIds("typesense.finder");
        foreach ($taggedServices as $serviceName => $tags) {

            $reference = new Reference($serviceName);
            $connectionName = explode(".", $serviceName)[2] ?? null;

            $definition->addMethodCall("addFinder", [$connectionName, $reference]);
        }
    }
}
