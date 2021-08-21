<?php

namespace delphi\ORM;

use delphi\ORM\Client\ClientInterface;
use delphi\ORM\Metadata\Entity;
use ReflectionClass;

class EntityManager {
    protected ClientInterface $client;

    protected QueryBuilder $builder;

    protected UnitOfWork $unitOfWork;

    public function __construct(ClientInterface $client, QueryBuilder $builder)
    {
        $this->client = $client;

        $this->builder = $builder;

        $this->unitOfWork = new UnitOfWork($this, $builder, $client);
    }

    public function flush()
    {
        $this->unitOfWork->commit();
    }

    public function getClassMetadata(string $className): Entity
    {
        return $this->builder->getMetadataDriver()->getMetadataForEntity(new ReflectionClass($className));
    }

    public function persist($entity)
    {
        $this->unitOfWork->persist($entity);
    }
}
