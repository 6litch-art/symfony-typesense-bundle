<?php

namespace Symfony\UX\Typesense\Manager;
use Doctrine\ORM\EntityManagerInterface;
use DoctrineExtensions\Query\Mysql\Field;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\UX\Typesense\Client\TypesenseClient;
use Symfony\UX\Typesense\Finder\CollectionFinder;
use Symfony\UX\Typesense\Transformer\DoctrineToTypesenseTransformer;

class TypesenseManager
{
    protected array $clients = [];
    protected $parameterBag;
    protected $entityManager;

    protected $documentManagers;
    protected $collectionManagers;
    protected $doctrineTransformers;

    public function __construct(ParameterBagInterface $parameterBag, EntityManagerInterface $entityManager)
    {
        $this->parameterBag = $parameterBag;
        $this->entityManager = $entityManager;
    }

    public function getEntityManager(): EntityManagerInterface { return $this->entityManager; }
    public function addClient(TypesenseClient $client)
    {
        $this->clients[$client->getConnectionName()] = $client;

        $this->documentManagers[$client->getConnectionName()] = new DocumentManager($client);
        $this->collectionManagers[$client->getConnectionName()] = new CollectionManager($this, $client->getConnectionName());

        $this->doctrineTransformers[$client->getConnectionName()] = new DoctrineToTypesenseTransformer($this->entityManager, $client->getCollectionDefinitions());
    }


    public function getManagedClassNames(?string $connectionName = null) :array
    {
        $connectionName ??= $this->getDefaultConnectionName();
        return $this->getCollectionManager($connectionName)?->getManagedClassNames() ?? [];
    }

    public function getCollectionManager(?string $connectionName = null) : ?CollectionManager
    {
        $connectionName ??= $this->getDefaultConnectionName();
        return $this->collectionManagers[$connectionName] ?? null;
    }

    public function getDocumentManager(?string $connectionName = null) : ?DocumentManager
    {
        $connectionName ??= $this->getDefaultConnectionName();
        return $this->documentManagers[$connectionName] ?? null;
    }

    public function getDoctrineTransformer(?string $connectionName = null) :?DoctrineToTypesenseTransformer
    {
        $connectionName ??= $this->getDefaultConnectionName();
        return $this->doctrineTransformers[$connectionName] ?? null;
    }

    public function addFinder(string $connectionName, CollectionFinder $collectionFinder)
    {
        $client = $this->getConnection($connectionName);
        $client->addFinder($collectionFinder);
    }

    public function getDefaultConnectionName() { return $this->parameterBag->get("typesense.default_connection"); }
    public function getDefaultConnection(): ?TypesenseClient { return $this->getConnection(); }
    public function getConnections(?string $connectionName = null): array
    {
        return $this->clients;
    }
    public function getConnection(?string $connectionName = null): ?TypesenseClient
    {
        $connectionName ??= $this->getDefaultConnectionName();
        return $this->clients[$connectionName];
    }

    public function getFinders(?string $connectionName = null): ?array { return $this->getConnection($connectionName)?->getFinders(); }
    public function getFinder(string $collectionName, ?string $connectionName = null): ?CollectionFinder
    {
        return $this->getConnection($connectionName)?->getFinder($collectionName);
    }
}
