<?php

declare(strict_types=1);

namespace Typesense\Bundle\DependencyInjection;

use Base\Service\Model\IconProvider\AbstractIconAdapter;
use Doctrine\ORM\ObjectManagerInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Typesense\Bundle\connection\Connection;
use Typesense\Bundle\ORM\TypesenseManager;

class TypesenseExtension extends Extension
{
    private string $defaultConnection;

    private array $metadata = [];

    public function load(array $configs, ContainerBuilder $container)
    {
        // Format XML
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        // Configuration file: ./config/package/base.yaml
        $processor = new Processor();
        $configuration = new Configuration();
        $typesense = $processor->processConfiguration($configuration, $configs);

        $this->setConfiguration($container, $typesense, $configuration->getTreeBuilder()->getRootNode()->getNode()->getName());

        $this->defaultConnection = $config["default_connection"] ?? "default";
        $this->initialize($container);

        foreach($typesense["connections"] ?? [] as $connectionName => $configuration)
        {
            $this->loadConnection($connectionName, $configuration, $container);
        }

        foreach($typesense["mappings"] ?? [] as $collectionName => $configuration)
        {
            $this->loadMetadata($collectionName, $configuration ?? [], $container);
            $this->loadCollections($collectionName, $configuration ?? [], $container);
            $this->loadFinders($collectionName, $configuration ?? [], $container);
        }
    }

    public function setConfiguration(ContainerBuilder $container, array $config, $globalKey = "")
    {
        foreach ($config as $key => $value) {
            if (!empty($globalKey)) {
                $key = $globalKey . "." . $key;
            }

            if (is_array($value)) {
                $this->setConfiguration($container, $value, $key);
            } else {
                $container->setParameter($key, $value);
            }
        }

        return $this;
    }

    public function initialize(ContainerBuilder $container)
    {
        $definition = $container->getDefinition(TypesenseManager::class);
        $definition->replaceArgument(0, $this->defaultConnection);

        $container->setDefinition(TypesenseManager::class, $definition);

        return $this;
    }

    /**
     * Loads the configured connections.
     *
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    private function loadConnection(string $connectionName, array $connection, ContainerBuilder $container)
    {
        $id  = sprintf('typesense.connection.%s', $connectionName);
        $definition = new ChildDefinition('typesense.connection');
        $definition->replaceArgument(0, $connectionName);

        $container->setDefinition($id, $definition);
        $definition->addTag("typesense.connection");

        return $this;
    }

    /**
     * Loads the configured collection.
     *
     * @param array            $mappings An array of collection configurations
     * @param ContainerBuilder $container   A ContainerBuilder instance
     *
     * @throws \InvalidArgumentException
     */
    private function loadMetadata(string $name, array $collection, ContainerBuilder $container)
    {
        $id  = sprintf('typesense.metadata.%s', $name);
        $definition = new ChildDefinition('typesense.metadata');
        $definition->replaceArgument(0, $name);
        $definition->replaceArgument(1, $collection);

        $container->setDefinition($id, $definition);
        $definition->addTag("typesense.metadata");

        return $this;
    }

    /**
     * Loads the configured collection.
     *
     * @param array            $mappings An array of collection configurations
     * @param ContainerBuilder $container   A ContainerBuilder instance
     *
     * @throws \InvalidArgumentException
     */
    private function loadCollections(string $name, array $collection, ContainerBuilder $container)
    {
        $connectionName = $collection["connection"] ?? $this->defaultConnection;

        $id  = sprintf('typesense.collection.%s', $name);
        $definition = new ChildDefinition('typesense.collection');
        $definition->replaceArgument(0, new Reference(sprintf('typesense.metadata.%s', $name)));
        $definition->replaceArgument(1, new Reference(sprintf('typesense.connection.%s', $connectionName)));

        $container->setDefinition($id, $definition);
        $definition->addTag("typesense.collection");

        return $this;
    }

    /**
     * Loads the configured index finders.
     */
    private function loadFinders(string $name, array $collection, ContainerBuilder $container)
    {
        $id  = sprintf('typesense.finder.%s', $name);
        $definition = new ChildDefinition('typesense.finder');
        $definition->replaceArgument(0, new Reference(sprintf('typesense.collection.%s', $name)));

        $definition->addTag("typesense.finder");
        $container->setDefinition($id, $definition);

        return $this;
    }
}
