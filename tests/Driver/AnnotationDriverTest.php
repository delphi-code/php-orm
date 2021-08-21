<?php

namespace delphi\ORM\Tests\Driver;

use delphi\ORM\Annotation as OGM;
use delphi\ORM\Driver\AnnotationDriver;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \delphi\ORM\Driver\AnnotationDriver
 * @uses \delphi\ORM\Driver\AnnotationDriver
 * @uses \delphi\ORM\Metadata\Property
 * @uses \delphi\ORM\Metadata\Relationship
 */
class AnnotationDriverTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** @var AnnotationDriver */
    private $sut;

    public function setUp(): void
    {
        parent::setUp();

        AnnotationRegistry::registerLoader('class_exists');
        $this->sut = new AnnotationDriver(new AnnotationReader());
    }

    /**
     * @covers ::getPropertiesForProperty
     */
    public function testGetPropertiesForProperty()
    {
        /**
         * @OGM\Entity(label="testLabel")
         */
        $class            = new class
        {
            /**
             * @OGM\Property(name="Stephen", unique=false)
             */
            protected $test;
        };
        $propertyOnObject = 'test';
        $propertyName     = 'Stephen';
        $isUnique         = false;

        $prop = new \ReflectionProperty($class, $propertyOnObject);
        $out  = $this->sut->getPropertiesForProperty($prop);

        $this->assertEquals($propertyName, $out->name);
        $this->assertEquals($isUnique, $out->unique);
        $this->assertEquals($propertyOnObject, $out->propertyOnObject);
    }

    /**
     * @covers ::getPropertiesForProperty
     */
    public function testGetPropertiesForPropertyReturnsNullForNoProperty()
    {
        /**
         * @OGM\Entity(label="testLabel")
         */
        $class            = new class
        {
            /**
             * @OGM\Property(name="Stephen", unique=false)
             */
            protected $protectedNonUniquePropertyStephen;

            /**
             * @OGM\Property(name="Esteban", unique=true)
             */
            protected $protectedUniqueProperty;

            /**
             * Does *not* have a property set on it
             */
            protected $protectedNonProperty;

            /**
             * @OGM\Relationship(type="relationshipType", targetEntity="\delphi\ORM\Tests\Fixtures\DummyModel", multiple=true)
             */
            protected $protectedRelationshipPropertyWithMultiple;
        };
        $propertyOnObject = 'protectedNonProperty';

        $prop = new \ReflectionProperty($class, $propertyOnObject);
        $out  = $this->sut->getPropertiesForProperty($prop);

        $this->assertNull($out);
    }

    /**
     * @covers ::getRelationshipsForProperty
     */
    public function testGetRelationshipsForProperty()
    {
        /**
         * @OGM\Entity(label="testLabel")
         */
        $class = new class
        {
            /**
             * @OGM\Property(name="Stephen", unique=false)
             */
            protected $protectedNonUniquePropertyStephen;

            /**
             * @OGM\Property(name="Esteban", unique=true)
             */
            protected $protectedUniqueProperty;

            /**
             * Does *not* have a property set on it
             */
            protected $protectedNonProperty;

            /**
             * @OGM\Relationship(type="relationshipType", targetEntity="\stdClass", multiple=true)
             */
            protected $protectedRelationshipPropertyWithMultiple;
        };

        $propertyOnObject = 'protectedRelationshipPropertyWithMultiple';
        $isMultiple       = true;
        $targetEntity     = '\\' . \stdClass::class;
        $type             = 'relationshipType';

        $prop = new \ReflectionProperty($class, $propertyOnObject);
        $out  = $this->sut->getRelationshipsForProperty($prop);

        $this->assertNotNull($out);

        $this->assertEquals($isMultiple, $out->multiple);
        $this->assertEquals($targetEntity, $out->targetEntity);
        $this->assertEquals($type, $out->type);
        $this->assertEquals($propertyOnObject, $out->propertyOnObject);
    }

    /**
     * @covers ::getRelationshipsForProperty
     */
    public function testGetRelationshipsForPropertyWithNonExistingClass()
    {
        /**
         * @OGM\Entity(label="testLabel")
         */
        $class = new class
        {
            /**
             * @OGM\Relationship(type="relationshipType", targetEntity="shrug", multiple=true)
             */
            protected $protectedRelationshipPropertyWithNonExistingClass;
        };

        $propertyOnObject = 'protectedRelationshipPropertyWithNonExistingClass';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Class defined for relationship on');

        $prop = new \ReflectionProperty($class, $propertyOnObject);
        $this->sut->getRelationshipsForProperty($prop);
    }

    /**
     * @covers ::getRelationshipsForProperty
     */
    public function testGetRelationshipsForPropertyThatIsNotAProperty()
    {
        /**
         * @OGM\Entity(label="testLabel")
         */
        $class            = new class
        {
            /**
             * @OGM\Property(name="Stephen", unique=false)
             */
            protected $protectedNonUniquePropertyStephen;

            /**
             * @OGM\Property(name="Esteban", unique=true)
             */
            protected $protectedUniqueProperty;

            /**
             * Does *not* have a property set on it
             */
            protected $protectedNonProperty;

            /**
             * @OGM\Relationship(type="relationshipType", targetEntity="\stdClass", multiple=true)
             */
            protected $protectedRelationshipPropertyWithMultiple;
        };
        $propertyOnObject = 'protectedNonProperty';

        $prop = new \ReflectionProperty($class, $propertyOnObject);
        $out  = $this->sut->getRelationshipsForProperty($prop);
        $this->assertNull($out);
    }

    /**
     * @covers ::getLabel
     * @covers ::__construct
     */
    public function testGetLabel()
    {
        /**
         * @OGM\Entity(label="testLabel")
         */
        $class = new class
        {
            /**
             * @OGM\Property(name="Stephen", unique=false)
             */
            protected $protectedNonUniquePropertyStephen;

            /**
             * @OGM\Property(name="Esteban", unique=true)
             */
            protected $protectedUniqueProperty;

            /**
             * Does *not* have a property set on it
             */
            protected $protectedNonProperty;

            /**
             * @OGM\Relationship(type="relationshipType", targetEntity="\stdClass", multiple=true)
             */
            protected $protectedRelationshipPropertyWithMultiple;
        };

        $refClass = new \ReflectionClass($class);
        $out      = $this->sut->getLabel($refClass);

        $this->assertNotNull($out);
        $this->assertEquals('testLabel', $out);
    }

    /**
     * @covers ::getLabel
     */
    public function testGetLabelWithMissingAnnotation()
    {
        $refClass = new \ReflectionClass(AnnotationDriver::class);
        $out      = $this->sut->getLabel($refClass);

        $this->assertNull($out);
    }

    /**
     * @covers ::getMetadataForEntity
     * @uses \delphi\ORM\Metadata\Entity::__construct
     */
    public function testGetMetadataForEntity()
    {
        /**
         * @OGM\Entity(label="testLabel")
         */
        $class    = new class
        {
            /**
             * @OGM\Property(name="Stephen", unique=false)
             */
            protected $protectedNonUniquePropertyStephen;

            /**
             * @OGM\Property(name="Esteban", unique=true)
             */
            protected $protectedUniqueProperty;

            /**
             * Does *not* have a property set on it
             */
            protected $protectedNonProperty;

            /**
             * @OGM\Relationship(type="relationshipType", targetEntity="\stdClass", multiple=true)
             */
            protected $protectedRelationshipPropertyWithMultiple;
        };
        $refClass = new \ReflectionClass($class);

        $out = $this->sut->getMetadataForEntity($refClass);

        $this->assertEquals('testLabel', $out->label);

        $this->assertCount(1, $out->relationships);
        $this->assertEquals('protectedRelationshipPropertyWithMultiple', $out->relationships[0]->propertyOnObject);

        $this->assertCount(1, $out->properties);
        $this->assertEquals('protectedNonUniquePropertyStephen', $out->properties[0]->propertyOnObject);

        $this->assertCount(1, $out->uniqueProperties);
        $this->assertEquals('protectedUniqueProperty', $out->uniqueProperties[0]->propertyOnObject);
    }

    /**
     * This will likely change when we rework the behavior for non-entities
     *
     * @covers ::getMetadataForEntity
     * @uses \delphi\ORM\Metadata\Entity::__construct
     */
    public function testGetMetadataForEntityWithNonEntity()
    {
        $refClass = new \ReflectionClass(AnnotationDriver::class);

        $out = $this->sut->getMetadataForEntity($refClass);

        $this->assertEquals(null, $out->label);

        $this->assertCount(0, $out->relationships);

        $this->assertCount(0, $out->properties);

        $this->assertCount(0, $out->uniqueProperties);
    }

}
