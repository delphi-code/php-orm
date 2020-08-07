<?php

namespace delphi\ORM;

use delphi\ORM\Client\ClientInterface;
use delphi\ORM\Entity\Edge;
use delphi\ORM\Util\PropertyGetter;
use ReflectionProperty;
use Throwable;

class UnitOfWork {
    protected QueryBuilder $builder;

    protected ClientInterface $client;

    protected EntityManager $em;

    /** @var object[][] */
    protected array $entityInsertionsByType = [];

    protected PropertyGetter $getter;

    public function __construct(EntityManager $em, QueryBuilder $builder, ClientInterface $client, PropertyGetter $getter = null)
    {
        $this->em      = $em;
        $this->builder = $builder;
        $this->client  = $client;
        $this->getter  = $getter ?? new PropertyGetter();
    }

    public function commit()
    {
        foreach ($this->entityInsertionsByType as $type => $entityInsertions) {
            // Do the Edges last
            if ($type === Edge::class) {
                continue;
            }
            $this->commitType($entityInsertions, $type);
        }

        // Commit any edges last since they rely on other objects being inserted first
        if (array_key_exists(Edge::class, $this->entityInsertionsByType)) {
            foreach ($this->entityInsertionsByType[Edge::class] as $edge) {
                $this->commitEdge($edge);
            }
        }

        // Clear out insertions
        $this->entityInsertionsByType = [];

        // TODO Maybe a postFlush action for models to execute custom commands?
        // TODO Or maybe have some sort of ordering inherent so we can make sure Scope runs last?
        return true;
    }

    /**
     * Generate statement for inserting
     *
     * @todo Pretty sure most of this logic should be in EntityManager
     */
    public function commitType(array $entityInsertions, $type): void
    {
        if (count($entityInsertions) === 0) {
            return;
        }

        $wound        = [];
        $entityByUUID = [];
        foreach ($entityInsertions as $entity) {
            $makeParamsForEntity                        = $this->builder->makeParamsForEntity($entity);
            $entityByUUID[$makeParamsForEntity['uuid']] = $entity;
            $wound[]                                    = $makeParamsForEntity;
        }

        $paramName = 'ENTITIES';
        $stmt      = $this->builder->makeStatementForType($entityInsertions[array_key_first($entityInsertions)], $paramName);
        $stmt      = str_replace(PHP_EOL, ' ', $stmt);

        try {
            $out = $this->client->run($stmt, [$paramName => $wound], $type);

            // Map returned IDs back to original entities

            foreach ($out->records() as $record) {
                $orig   = $record->get($this->builder->getAttemptedUUIDFieldName());
                $actual = $record->get($this->builder->getSavedUUIDFieldName());
                // Set the UUID on the object
                $x                     = $entityByUUID[$orig];
                $x->uuid               = $actual;
                $entityByUUID[$actual] = $x;
            }
        } catch (Throwable $e) {
            // If there's a problem... try running each one individually
            foreach ($wound as $w) {
                $this->client->run($stmt, [$paramName => [$w]], $type);
            }
        }
    }

    public function commitEdge(Edge $edge): void
    {
        $this->client->run(
            $this->builder->makeStatementForEdge($edge->type, $edge->fromObj, $edge->toObj),
            $this->builder->makeParamsForEdge($edge->fromObj, $edge->toObj)
        );
    }

    public function persist($entity)
    {
        $visited = [];
        $this->doPersist($entity, $visited);
    }

    public function scheduleForInsert($entity)
    {
        $this->entityInsertionsByType[get_class($entity)][spl_object_hash($entity)] = $entity;
    }

    protected function doPersist($entity, array &$visited)
    {
        $oid = spl_object_hash($entity);
        if (isset($visited[$oid])) {
            return;
        }
        $visited[$oid] = $entity;

        $this->scheduleForInsert($entity);

        $this->cascadePersist($entity, $visited);
    }

    private function cascadePersist($entity, array &$visited)
    {
        $class = $this->em->getClassMetadata(get_class($entity));

        foreach ($class->relationships as $relationship) {
            $this->cascadePersistRelationship($entity, $relationship, $visited);
        }
    }

    private function cascadePersistRelationship($entity, Metadata\Relationship $relationship, array &$visited): void
    {
        // Get the (possible) relation
        $related = $this->getter->getPropertyValue($entity, $relationship->propertyOnObject);

        if (is_null($related)) {
            return;
        }

        // Handle multiple relations by assuming they're all multiple
        $relatedObjs = $related;
        if (!$relationship->multiple) {
            $relatedObjs = [$related];
        }

        foreach ($relatedObjs as $relatedObj) {
            // Make sure it's correct type
            if (!($relatedObj instanceof $relationship->targetEntity)) {
                throw new IncorrectRelationshipType($relationship, $relatedObj, $entity);
            }

            $this->doPersist(new Edge($entity, $relationship->type, $relatedObj), $visited);

            $this->doPersist($relatedObj, $visited);
        }
    }
}
