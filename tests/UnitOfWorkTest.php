<?php

namespace delphi\ORM\Tests;

use delphi\ORM\Client\ClientInterface;
use delphi\ORM\EntityManager;
use delphi\ORM\Metadata\Entity;
use delphi\ORM\Metadata\Relationship;
use delphi\ORM\QueryBuilder;
use delphi\ORM\Tests\Fixtures\DummyModel;
use delphi\ORM\Tests\Fixtures\FakeQueryBuilder;
use delphi\ORM\Tests\Fixtures\TestRecord;
use delphi\ORM\Tests\Fixtures\TestResult;
use delphi\ORM\UnitOfWork;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \delphi\ORM\UnitOfWork
 * @uses \delphi\ORM\Driver\AnnotationDriver
 * @uses \delphi\ORM\Metadata\Entity
 * @uses \delphi\ORM\QueryBuilder
 * @uses \delphi\ORM\Entity\Edge
 * @uses \delphi\ORM\Metadata\Property
 * @uses \delphi\ORM\Metadata\Relationship
 * @uses \delphi\ORM\Util\PropertyGetter
 */
class UnitOfWorkTest extends TestCase {
    use MockeryPHPUnitIntegration;

    /** @var EntityManager&\PHPUnit\Framework\MockObject\Stub */
    protected $em;

    /** @var QueryBuilder */
    protected $queryBuilder;

    /** @var ClientInterface&\Mockery\Mock */
    protected $client;

    protected UnitOfWork $sut;

    public function setUp(): void
    {
        $this->client       = \Mockery::mock(ClientInterface::class);
        $this->em           = $this->createStub(EntityManager::class);
        $this->queryBuilder = new FakeQueryBuilder();
        $this->sut          = new UnitOfWork($this->em, $this->queryBuilder, $this->client);
    }

    /**
     * @covers ::__construct
     * @covers ::commit
     * @covers ::cascadePersist
     * @covers ::cascadePersistRelationship
     * @covers ::commitType
     * @covers ::doPersist
     * @covers ::persist
     * @covers ::scheduleForInsert
     */
    public function testAll()
    {
        $entity = $this->createStub(\stdClass::class);
        $this->em->method('getClassMetadata')->willReturn(new Entity('entityLabel'));

        $uuid_actual = 'youYouEyeDee';

        $this->client
            ->shouldReceive('run')
            ->with(
                $this->queryBuilder->makeStatementForType($entity, 'ENTITIES'),
                ['ENTITIES' => [['props' => [], 'uuid' => spl_object_id($entity),],],],
                get_class($entity)
            )
            ->andReturn(new TestResult([
                new TestRecord([
                    $this->queryBuilder->getAttemptedUUIDFieldName() => spl_object_id($entity),
                    $this->queryBuilder->getSavedUUIDFieldName()     => $uuid_actual,
                ]),
            ]))
            ->once();

        $this->sut->persist($entity);
        $this->sut->commit();

        $this->assertEquals($uuid_actual, $entity->uuid);
    }

    /**
     * @covers ::__construct
     * @covers ::commit
     * @covers ::cascadePersist
     * @covers ::cascadePersistRelationship
     * @covers ::commitType
     * @covers ::commitEdge
     * @covers ::doPersist
     * @covers ::persist
     * @covers ::scheduleForInsert
     */
    public function testAllWithRelationship()
    {
        $entity = $this->createStub(\stdClass::class);

        $related         = $this->createStub(DummyModel::class);
        $entity->related = $related;

        $this->em->method('getClassMetadata')->willReturnCallback(function (string $cls) use ($entity, $related) {
            if ($cls === get_class($entity)) {
                $md                  = new Entity('entityLabel');
                $md->relationships[] = new Relationship('TESTED_WITH', get_class($related), 'related');
                return $md;
            }
            return new Entity('SomethingElse');
        });

        $uuid_actual = 'charDeeMcDennis';

        $this->client->shouldReceive('run')
                     ->with(
                         $this->queryBuilder->makeStatementForType($entity, 'ENTITIES'),
                         ['ENTITIES' => [['props' => [], 'uuid' => spl_object_id($entity),],]],
                         get_class($entity)
                     )
                     ->andReturn(new TestResult([
                         new TestRecord([
                             $this->queryBuilder->getAttemptedUUIDFieldName() => spl_object_id($entity),
                             $this->queryBuilder->getSavedUUIDFieldName()     => $uuid_actual,
                         ]),
                     ]))
                     ->once();

        $this->client->shouldReceive('run')
                     ->with(
                         $this->queryBuilder->makeStatementForType($related, 'ENTITIES'),
                         \Mockery::on(fn($params) => json_encode([
                                 'ENTITIES' =>
                                     [
                                         [
                                             'Esteban'                                   => null,
                                             'protectedRelationshipPropertyWithMultiple' => [],
                                             'props'                                     => ['protectedNonUniquePropertyStephen' => null,],
                                             'uuid'                                      => spl_object_id($related),
                                         ],
                                     ],
                             ], JSON_THROW_ON_ERROR) === json_encode($params, JSON_THROW_ON_ERROR)),
                         get_class($related)
                     )
                     ->andReturn(new TestResult([
                         new TestRecord([
                             $this->queryBuilder->getAttemptedUUIDFieldName() => spl_object_id($related),
                             $this->queryBuilder->getSavedUUIDFieldName()     => $uuid_actual,
                         ]),
                     ]))
                     ->once();

        $this->client->shouldReceive('run')
                     ->with(
                         $this->queryBuilder->makeStatementForEdge('TESTED_WITH', $entity, $related),
                         [
                             'fromUUID' => $uuid_actual,
                             'toUUID'   => $uuid_actual,
                         ]
                     )
                     ->andReturn(new TestResult([new TestRecord([]),]))
                     ->once();

        $this->sut->persist($entity);
        $this->sut->commit();

        $this->assertEquals($uuid_actual, $entity->uuid);

    }
}
