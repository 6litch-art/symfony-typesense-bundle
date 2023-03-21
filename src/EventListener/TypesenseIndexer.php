<?php

declare(strict_types=1);

namespace Symfony\UX\Typesense\EventListener;

use Symfony\UX\Typesense\Manager\CollectionManager;
use Symfony\UX\Typesense\Manager\DocumentManager;
use Symfony\UX\Typesense\Transformer\DoctrineToTypesenseTransformer;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class TypesenseIndexer
{
    private $documentManager;
    private $collectionManager;
    private $transformer;
    private $managedClassNames;

    private $objetsIdThatCanBeDeletedByObjectHash = [];

    private $documentsToIndex                     = [];
    private $documentsToUpdate                    = [];
    private $documentsToDelete                    = [];

    public function __construct(
        CollectionManager $collectionManager,
        DocumentManager $documentManager,
        DoctrineToTypesenseTransformer $transformer
    ) {
        $this->collectionManager = $collectionManager;
        $this->documentManager   = $documentManager;
        $this->transformer       = $transformer;

        $this->managedClassNames  = $this->collectionManager->getManagedClassNames();
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        if ($this->entityIsNotManaged($entity)) {
            return;
        }

        $collections = $this->getCollectionNames($entity);
        $data       = $this->transformer->convert($entity);

        foreach ($collections as $collection) {
            $this->documentsToIndex[] = [$collection, $data];
        }
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        if ($this->entityIsNotManaged($entity)) {
            return;
        }

        $collections = $this->getCollectionNames($entity);
        foreach ($collections as $collection) {
            $collectionConfig = $this->collectionManager->getCollectionDefinitions()[$collection];
            $this->checkPrimaryKeyExists($collectionConfig);

            $data = $this->transformer->convert($entity);

            $primaryField = array_search_by($collectionConfig["fields"], "type", "primary");
            $entityId = $data[$primaryField["name"] ?? "id"] ?? null;
            if ($entityId) {
                $this->documentsToUpdate[] = [$collection, $entityId, $data];
            }
        }
    }

    private function checkPrimaryKeyExists($collectionConfig)
    {
        foreach ($collectionConfig['fields'] as $config) {
            if ($config['type'] === 'primary') {
                return;
            }
        }

        throw new \Exception(sprintf('Primary key info have not been found for Typesense collection %s', $collectionConfig['typesense_name']));
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        if ($this->entityIsNotManaged($entity)) {
            return;
        }

        $data = $this->transformer->convert($entity);

        $this->objetsIdThatCanBeDeletedByObjectHash[spl_object_hash($entity)] = $data['id'];
    }

    public function postRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        $entityHash = spl_object_hash($entity);

        if (!isset($this->objetsIdThatCanBeDeletedByObjectHash[$entityHash])) {
            return;
        }

        $collections = $this->getCollectionNames($entity);
        foreach ($collections as $collection) {
            $this->documentsToDelete[] = [$collection, $this->objetsIdThatCanBeDeletedByObjectHash[$entityHash]];
        }
    }

    public function postFlush()
    {
        $this->indexDocuments();
        $this->updateDocuments();
        $this->deleteDocuments();

        $this->resetDocuments();
    }

    private function indexDocuments()
    {
        foreach ($this->documentsToIndex as $documentToIndex) {
            $this->documentManager->index(...$documentToIndex);
        }
    }

    private function updateDocuments()
    {
        foreach ($this->documentsToUpdate as $documentToUpdate) {
            try {
                $this->documentManager->delete($documentToUpdate[0], $documentToUpdate[1]);
            } catch(\Typesense\Exceptions\ObjectNotFound $e) {
            }

            $this->documentManager->index($documentToUpdate[0], $documentToUpdate[2]);
        }
    }

    private function deleteDocuments()
    {
        foreach ($this->documentsToDelete as $documentToDelete) {
            $this->documentManager->delete(...$documentToDelete);
        }
    }

    private function resetDocuments()
    {
        $this->documentsToIndex  = [];
        $this->documentsToUpdate = [];
        $this->documentsToDelete = [];
    }

    private function entityIsNotManaged($entity)
    {
        $entityClassname = ClassUtils::getClass($entity);

        return !in_array($entityClassname, array_values($this->managedClassNames), true);
    }

    private function getCollectionNames($entity): array
    {
        $entityClassname = ClassUtils::getClass($entity);

        $collectionNames = [];
        foreach ($this->managedClassNames as $key => $managedClassName) {
            if (is_instanceof($entityClassname, $managedClassName)) {
                $collectionNames[] = $key;
            }
        }

        return $collectionNames;
    }
}
