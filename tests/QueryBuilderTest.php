<?php

namespace delphi\ORM\Tests;

use delphi\ORM\Driver\AnnotationDriver;
use delphi\ORM\Metadata\Entity;
use delphi\ORM\QueryBuilder;
use delphi\ORM\Tests\Fixtures\MetadataEntity;
use Doctrine\Common\Annotations\AnnotationReader;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\Mock;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \delphi\ORM\QueryBuilder
 * @uses \delphi\ORM\Driver\AnnotationDriver
 * @uses \delphi\ORM\Util\PropertyGetter
 * @uses \delphi\ORM\Metadata\Entity
 * @uses \delphi\ORM\QueryBuilder
 */
class QueryBuilderTest extends TestCase {
    use MockeryPHPUnitIntegration;

    protected QueryBuilder $sut;

    protected MetadataEntity $metadataEntity;

    /**
     * @var Entity[]
     */
    protected $metadata = [];

    public function setUp(): void
    {
        $this->metadataEntity = new MetadataEntity();

        /** @var Mock|AnnotationDriver $driver */
        $driver = \Mockery::mock(AnnotationDriver::class);
        $driver->shouldReceive('getMetadataForEntity')->andReturnUsing(function (\ReflectionClass $x) {
            if (array_key_exists($x->name, $this->metadata)) {
                return $this->metadata[$x->name];
            }
            throw new \Exception('Did not register metadata for Entity: ' . $x->name);
        });

        $this->sut = new QueryBuilder($driver);
    }

    private function registerMetadata($class = null): MetadataEntity
    {
        if (null === $class) {
            $class = new class extends
                Fixtures\AnonClass {
            };
        }

        $entity        = new MetadataEntity();
        $entity->class = $class;

        $entity->register = function ($kls) {
            return $this->registerMetadata($kls);
        };

        $this->metadata[get_class($class)] = $entity;

        return $entity;
    }

    /**
     * @covers ::__construct
     * @covers ::getMetadataDriver
     * @uses \delphi\ORM\Driver\AnnotationDriver
     * @uses \delphi\ORM\Metadata\Entity
     */
    public function testGetMetadataDriver()
    {
        $driver = new AnnotationDriver(new AnnotationReader());
        $sut    = new QueryBuilder($driver);
        $this->assertSame($driver, $sut->getMetadataDriver());
    }

    /**
     * @covers ::makeStatementForType
     * @covers ::makeMergeStmt
     * @uses \delphi\ORM\Metadata\Entity
     * @uses \delphi\ORM\Metadata\Property
     * @uses \delphi\ORM\Metadata\Relationship
     * @uses \delphi\ORM\QueryBuilder::__construct
     * @uses \delphi\ORM\QueryBuilder::getAttemptedUUIDFieldName
     * @uses \delphi\ORM\QueryBuilder::getSavedUUIDFieldName
     */
    public function testMakeStatementForType()
    {
        $anonClass = $this->registerMetadata(new class {
        })
                          ->setLabel('Directory')
                          ->addUniqueProperty('path', 'path')
                          ->getClass();

        $base = $this->registerMetadata()
                     ->setLabel('Directory')
                     ->addUniqueProperty('path')
                     ->addRelationship('CONTAINED_IN', 'parent', false, $anonClass)
                     ->getClass();

        $expect = 'UNWIND {DIRECTORYS} as item
MERGE (ref:Directory {path:item.path})
 ON MATCH SET ref += item.props
 ON CREATE SET ref += item.props, ref.uuid = item.uuid
 RETURN ref.uuid as saved_uuid, item.uuid as attempted_uuid';
        $out    = $this->sut->makeStatementForType(new $base, 'DIRECTORYS');

        $this->assertEquals($expect, $out);
    }

    /**
     * @covers ::makeStatementForType
     * @covers ::makeMergeStmt
     * @uses \delphi\ORM\Driver\AnnotationDriver::__construct
     * @uses \delphi\ORM\Driver\AnnotationDriver::getLabel
     * @uses \delphi\ORM\Driver\AnnotationDriver::getMetadataForEntity
     * @uses \delphi\ORM\Driver\AnnotationDriver::getPropertiesForProperty
     * @uses \delphi\ORM\Driver\AnnotationDriver::getRelationshipsForProperty
     * @uses \delphi\ORM\Metadata\Entity::__construct
     * @uses \delphi\ORM\Metadata\Property::__construct
     * @uses \delphi\ORM\Metadata\Relationship::__construct
     * @uses \delphi\ORM\QueryBuilder::__construct
     * @uses \delphi\ORM\QueryBuilder::getAttemptedUUIDFieldName
     * @uses \delphi\ORM\QueryBuilder::getSavedUUIDFieldName
     */
    public function testMakeStatementForTypeWithMultiRelationship()
    {
        $kls = new class {
        };

        $this->registerMetadata($kls)
             ->setLabel('Directory')
             ->addUniqueProperty('path')
             ->addRelationship('IMPLEMENTS', 'implements', false, $kls)
             ->addRelationship('ALSO_IN', 'friend', false, $kls);

        $expect = 'UNWIND {DIRECTORYS} as item
MERGE (ref:Directory {path:item.path})
 ON MATCH SET ref += item.props
 ON CREATE SET ref += item.props, ref.uuid = item.uuid
 RETURN ref.uuid as saved_uuid, item.uuid as attempted_uuid';

        $out = $this->sut->makeStatementForType(new $kls(), 'DIRECTORYS');

        $this->assertEquals($expect, $out);
    }

    /**
     * @covers ::makeParamsForEntity
     * @uses \delphi\ORM\Driver\AnnotationDriver::__construct
     * @uses \delphi\ORM\Driver\AnnotationDriver::getLabel
     * @uses \delphi\ORM\Driver\AnnotationDriver::getMetadataForEntity
     * @uses \delphi\ORM\Metadata\Entity::__construct
     * @uses \delphi\ORM\Metadata\Property::__construct
     * @uses \delphi\ORM\QueryBuilder::__construct
     */
    public function testMakeParamsForEntity()
    {
        $expect = [
            'path'  => '/tmp',
            'props' => [
                'name' => 'bob',
            ],
            'uuid'  => $this->any(),
        ];

        $kls = $this->registerMetadata()
                    ->addProperty('name', 'name')
                    ->addUniqueProperty('path', 'path')
                    ->getClass();

        $entity = new $kls(['path' => '/tmp', 'name' => 'bob']);

        $out = $this->sut->makeParamsForEntity($entity);
        $this->assertEquals('/tmp', $out['path']);
        $this->assertEquals(['name' => 'bob'], $out['props']);
        $this->assertArrayHasKey('uuid', $out);
    }

    /**
     * @covers ::makeParamsForEntity
     * @covers ::getRelationshipValue
     * @uses \delphi\ORM\Driver\AnnotationDriver::__construct
     * @uses \delphi\ORM\QueryBuilder::__construct
     * @uses \delphi\ORM\Driver\AnnotationDriver::getLabel
     * @uses \delphi\ORM\Driver\AnnotationDriver::getMetadataForEntity
     * @uses \delphi\ORM\Metadata\Entity::__construct
     * @uses \delphi\ORM\Metadata\Property::__construct
     * @uses \delphi\ORM\Metadata\Relationship::__construct
     */
    public function testMakeParamsForEntityWithRelationship()
    {
        $pKls = new class extends
            Fixtures\AnonClass {
        };
        $this->registerMetadata($pKls)->addUniqueProperty('name', 'name');

        $kls = $this->registerMetadata()
                    ->addProperty('name', 'name')
                    ->addUniqueProperty('path', 'path')
                    ->addRelationship('', 'parent', true, $pKls)
                    ->getClass();

        $entity = new $kls ([
            'name'   => 'bob',
            'path'   => '/tmp',
            'parent' => new $pKls(['name' => 'Steven']),
        ]);

        $out = $this->sut->makeParamsForEntity($entity);

        $this->assertArrayHasKey('parent', $out);

        $this->assertEquals('/tmp', $out['path']);
        $this->assertEquals(['name' => 'bob',], $out['props']);
        $parent = $out['parent'][0];
        $this->assertEquals('Steven', $parent['name']);
        $this->assertEquals([], $parent['props']);
    }

    /**
     * @covers ::makeParamsForEntity
     * @covers ::getRelationshipValue
     * @uses \delphi\ORM\Driver\AnnotationDriver::__construct
     * @uses \delphi\ORM\QueryBuilder::__construct
     * @uses \delphi\ORM\Driver\AnnotationDriver::getLabel
     * @uses \delphi\ORM\Driver\AnnotationDriver::getMetadataForEntity
     * @uses \delphi\ORM\Metadata\Entity::__construct
     * @uses \delphi\ORM\Metadata\Property::__construct
     * @uses \delphi\ORM\Metadata\Relationship::__construct
     */
    public function testMakeParamsForEntityWithRelationshipThatIsNull()
    {
        $pKls = new class extends
            Fixtures\AnonClass {
        };
        $this->registerMetadata($pKls)->addUniqueProperty('name', 'name');

        $kls = $this->registerMetadata()
                    ->addProperty('name', 'name')
                    ->addUniqueProperty('path', 'path')
                    ->addRelationship('', 'parent', true, $pKls)
                    ->getClass();

        $entity = new $kls ([
            'name'   => 'bob',
            'path'   => '/tmp',
            'parent' => null,
        ]);

        $out = $this->sut->makeParamsForEntity($entity);

        $this->assertArrayHasKey('parent', $out);

        $this->assertEquals('/tmp', $out['path']);
        $this->assertEquals(['name' => 'bob',], $out['props']);
        $this->assertEquals([], $out['parent']);
    }

    /**
     * @covers ::makeParamsForEntity
     * @covers ::getRelationshipValue
     * @uses \delphi\ORM\Driver\AnnotationDriver::__construct
     * @uses \delphi\ORM\QueryBuilder::__construct
     * @uses \delphi\ORM\Driver\AnnotationDriver::getLabel
     * @uses \delphi\ORM\Driver\AnnotationDriver::getMetadataForEntity
     * @uses \delphi\ORM\Metadata\Entity::__construct
     * @uses \delphi\ORM\Metadata\Property::__construct
     * @uses \delphi\ORM\Metadata\Relationship::__construct
     */
    public function testMakeParamsForEntityWithNonMultipleRelationship()
    {
        $pKls = new class extends Fixtures\AnonClass {
        };
        $this->registerMetadata($pKls)->addUniqueProperty('name', 'name');

        $kls = $this->registerMetadata()
                    ->addProperty('name', 'name')
                    ->addUniqueProperty('path', 'path')
                    ->addRelationship('', 'parent', false, $pKls)
                    ->getClass();

        $entity = new $kls ([
            'name'   => 'bob',
            'path'   => '/tmp',
            'parent' => new $pKls(['name' => 'Steven']),
        ]);

        $out = $this->sut->makeParamsForEntity($entity);

        $this->assertArrayHasKey('parent', $out);

        $this->assertEquals('/tmp', $out['path']);
        $this->assertEquals(['name' => 'bob',], $out['props']);
        $parent = $out['parent'];
        $this->assertEquals('Steven', $parent['name']);
        $this->assertEquals([], $parent['props']);
    }

    /**
     * @covers ::makeParamsForEntity
     * @covers ::getRelationshipValue
     * @uses \delphi\ORM\Driver\AnnotationDriver::__construct
     * @uses \delphi\ORM\QueryBuilder::__construct
     * @uses \delphi\ORM\Driver\AnnotationDriver::getLabel
     * @uses \delphi\ORM\Driver\AnnotationDriver::getMetadataForEntity
     * @uses \delphi\ORM\Metadata\Entity::__construct
     * @uses \delphi\ORM\Metadata\Property::__construct
     * @uses \delphi\ORM\Metadata\Relationship::__construct
     */
    public function testMakeParamsForEntityWithNonMultipleRelationshipProblem()
    {
        $pKls = new class extends Fixtures\AnonClass {
        };
        $this->registerMetadata($pKls)->addUniqueProperty('name', 'name');

        $kls = $this->registerMetadata()
                    ->addProperty('name', 'name')
                    ->addUniqueProperty('path', 'path')
                    ->addRelationship('', 'parent', false, $pKls)
                    ->getClass();

        $entity = new $kls ([
            'name'   => 'bob',
            'path'   => '/tmp',
            'parent' => [new $pKls(['name' => 'Steven'])],
        ]);

        $this->expectExceptionMessage('Tried to set multiple values (1) to a single relationship field');
        $this->sut->makeParamsForEntity($entity);
    }

    /**
     * @covers ::makeParamsForEntity
     * @covers ::getRelationshipValue
     * @uses \delphi\ORM\Driver\AnnotationDriver::__construct
     * @uses \delphi\ORM\QueryBuilder::__construct
     * @uses \delphi\ORM\Driver\AnnotationDriver::getLabel
     * @uses \delphi\ORM\Driver\AnnotationDriver::getMetadataForEntity
     * @uses \delphi\ORM\Metadata\Entity::__construct
     * @uses \delphi\ORM\Metadata\Property::__construct
     * @uses \delphi\ORM\Metadata\Relationship::__construct
     */
    public function testMakeParamsForEntityWithNonMultipleRelationshipWithNull()
    {
        $pKls = new class extends Fixtures\AnonClass {
        };
        $this->registerMetadata($pKls)->addUniqueProperty('name', 'name');

        $kls = $this->registerMetadata()
                    ->addProperty('name', 'name')
                    ->addUniqueProperty('path', 'path')
                    ->addRelationship('', 'parent', false, $pKls)
                    ->getClass();

        $entity = new $kls ([
            'name'   => 'bob',
            'path'   => '/tmp',
            'parent' => null,
        ]);

        $out = $this->sut->makeParamsForEntity($entity);

        $this->assertArrayHasKey('parent', $out);

        $this->assertEquals('/tmp', $out['path']);
        $this->assertEquals(['name' => 'bob',], $out['props']);
        $this->assertNull($out['parent']);
    }

    /**
     * @covers ::makeParamsForEntity
     * @covers ::getRelationshipValue
     * @uses \delphi\ORM\Driver\AnnotationDriver::__construct
     * @uses \delphi\ORM\QueryBuilder::__construct
     * @uses \delphi\ORM\Driver\AnnotationDriver::getLabel
     * @uses \delphi\ORM\Driver\AnnotationDriver::getMetadataForEntity
     * @uses \delphi\ORM\Metadata\Entity::__construct
     * @uses \delphi\ORM\Metadata\Property::__construct
     * @uses \delphi\ORM\Metadata\Relationship::__construct
     */
    public function testMakeParamsForEntityWithNonMultipleRelationshipWithNullAndAMethod()
    {
        $pKls = new class extends Fixtures\AnonClass {
        };
        $this->registerMetadata($pKls)->addUniqueProperty('name', 'name');

        $methodical = new class {
            public string $name = 'nameVal';

            public function getPath()
            {
                return 'pathVal';
            }

            public function getParentThing()
            {
                return 'thing';
            }
        };

        $this->registerMetadata($methodical)
             ->addProperty('name', 'name')
             ->addUniqueProperty('path', 'path')
             ->addProperty('parent', 'parent_thing');

        $out = $this->sut->makeParamsForEntity($methodical);

        $this->assertEquals('pathVal', $out['path']);
        $this->assertEquals(['name' => 'nameVal', 'parent_thing' => 'thing'], $out['props']);
    }

    /**
     * @covers ::makeStatementForEdge
     * @uses \delphi\ORM\QueryBuilder
     * @uses \delphi\ORM\Metadata\Entity
     */
    public function testMakeStatementForEdge()
    {
        $type = 'typewriter';
        $from = (object)['origin' => 'yes'];
        $to   = new class {
        };

        $this->registerMetadata($from)->setLabel('Origin');
        $this->registerMetadata($to)->setLabel('Destination');

        $out = $this->sut->makeStatementForEdge($type, $from, $to);
        $this->assertEquals('MATCH (fromObj:Origin {uuid: $fromUUID}), (toObj:Destination {uuid: $toUUID}) MERGE (fromObj)-[r:typewriter]->(toObj)', $out);
    }

    /**
     * @covers ::makeParamsForEdge
     */
    public function testMakeParamsForEdge()
    {
        $uuidFrom   = 'uuidFROM';
        $uuidTo     = 'uuidTO';
        $from       = (object)['origin' => 'yes'];
        $from->uuid = $uuidFrom;
        $to         = new class {
        };
        $to->uuid   = $uuidTo;

        $this->assertEquals(['fromUUID' => $uuidFrom, 'toUUID' => $uuidTo], $this->sut->makeParamsForEdge($from, $to));
    }

    /**
     * @covers ::makeParamsForEdge
     */
    public function testMakeParamsForEdgeWithUnsaved()
    {
        $from = (object)['origin' => 'yes'];
        $to   = new class {
        };

        $this->expectExceptionMessage('must be saved');
        $this->sut->makeParamsForEdge($from, $to);
    }

    /**
     * @covers ::getSavedUUIDFieldName
     */
    public function testGetSavedUUIDFieldName()
    {
        $this->assertEquals('saved_uuid', $this->sut->getSavedUUIDFieldName());
    }

    /**
     * @covers ::getAttemptedUUIDFieldName
     */
    public function testGetAttemptedUUIDFieldName()
    {
        $this->assertEquals('attempted_uuid', $this->sut->getAttemptedUUIDFieldName());
    }
}
