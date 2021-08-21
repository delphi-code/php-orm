<?php

namespace delphi\ORM\Tests;

use delphi\ORM\Client\ClientInterface;
use delphi\ORM\Driver\AnnotationDriver;
use delphi\ORM\EntityManager;
use delphi\ORM\Metadata\Entity;
use delphi\ORM\QueryBuilder;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \delphi\ORM\EntityManager
 * @covers \delphi\ORM\EntityManager::__construct
 * @uses   \delphi\ORM\UnitOfWork
 */
class EntityManagerTest extends TestCase {
    use MockeryPHPUnitIntegration;

    /**
     * @var ClientInterface|\Mockery\Mock
     */
    protected $client;

    /**
     * @var QueryBuilder|\Mockery\Mock
     */
    protected $builder;

    protected EntityManager $sut;

    public function setUp(): void
    {
        /** @var \Mockery\Mock|ClientInterface $client */
        $client       = \Mockery::mock(ClientInterface::class);
        $this->client = $client;

        /** @var \Mockery\Mock|QueryBuilder $builder */
        $builder       = \Mockery::mock(QueryBuilder::class);
        $this->builder = $builder;

        $this->sut = new EntityManager($client, $builder);
    }

    /**
     * @covers ::getClassMetadata
     * @uses \delphi\ORM\Metadata\Entity
     */
    public function testGetClassMetadata()
    {
        $className = __CLASS__;
        $nonce     = new Entity(__METHOD__);

        /** @var \Mockery\Mock|AnnotationDriver $driver */
        $driver = \Mockery::mock(AnnotationDriver::class);
        $driver->shouldReceive('getMetadataForEntity')->withArgs(fn(\ReflectionClass $r) => $r->name === $className)->andReturn($nonce);

        $this->builder->shouldReceive('getMetadataDriver')->andReturn($driver);

        $out = $this->sut->getClassMetadata($className);
        $this->assertSame($nonce, $out);

    }
}
