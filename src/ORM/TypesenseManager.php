<?php

namespace Typesense\Bundle\ORM;
use Doctrine\ORM\ObjectManagerInterface;
use Doctrine\Persistence\ObjectManager;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Typesense\Bundle\DBAL\Connection;
use Typesense\Bundle\ORM\CollectionFinder;
use Typesense\Bundle\ORM\Mapping\TypesenseCollection;
use Typesense\Bundle\ORM\Mapping\TypesenseMetadata;
use Typesense\Bundle\ORM\TypesenseFinder;
use Typesense\Bundle\Transformer\DoctrineTransformer;

class TypesenseManager
{
    protected string $defaultConnection;
    protected array $connections = [];

    protected array $collections = [];
    protected array $finders = [];

    protected $metadata;

    public function __construct(?string $defaultConnection)
    {
        $this->defaultConnection = $defaultConnection;
    }

    //
    // Default connection
    public function getDefaultConnection(): ?Connection { return $this->getConnection(); }
    public function getDefaultConnectionName(): string  { return $this->defaultConnection; }

    //
    // Connection instances
    public function getConnections(): array { return $this->connections; }
    public function getConnection(?string $connectionName = null): ?Connection { return $this->connections[$connectionName ?? $this->getDefaultConnectionName()]; }
    public function addConnection(Connection $connection)
    {
        $this->connections[$connection->getName()] = $connection;
        return $this;
    }

    //
    // Metadata instances
    public function getMetadata(string $collectionName): ?TypesenseMetadata { return $this->getCollection($collectionName)?->metadata(); }
    public function addMetadata(TypesenseMetadata $metadata)
    {
        $this->metadata[$metadata->getName()] = $metadata;
        foreach($metadata->getSubMetadata() as $submetadata) {
            $this->metadata[$metadata->getName()."_".$submetadata->getName()] = $submetadata;
        }

        return $this;
    }

    //
    // Collection instances
    public function getCollections():array { return $this->collections; }
    public function getCollection(string $collectionName): ?TypesenseCollection { return $this->collections[$collectionName]; }
    public function addCollection(TypesenseCollection $collection)
    {
        $this->collections[$collection->name()] = $collection;
        foreach($collection->metadata()->getSubMetadata() as $submetadata) {
            $this->collections[$submetadata->getName()] = new TypesenseCollection($submetadata, $collection->connection());
        }

        foreach($this->collections as $name => $collection) {

            $metadata = $collection->metadata();
            $metadata->getTransformer()->addMapping($metadata);
        }

        return $this;
    }

    //
    // Additional instances for autowiring..
    public function getFinder (string $collectionName): ?TypesenseFinder { return $this->finders[$collectionName]; }
    public function addFinder(TypesenseFinder $finder): self
    {
        $this->finders[$finder->name()] = $finder;
        return $this;
    }
}
