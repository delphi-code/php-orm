<?php

namespace delphi\ORM\Tests\Fixtures;

use delphi\ORM\Annotation as OGM;

/**
 * @OGM\Entity(label="testLabel")
 */
class DummyModel
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

    public function __construct($nonUniqueProperty = null, $uniqueProperty = null, $nonProperty = null, DummyModel $relationship = null)
    {
        $this->protectedNonUniquePropertyStephen = $nonUniqueProperty;
        $this->protectedUniqueProperty = $uniqueProperty;
        $this->protectedNonProperty = $nonProperty;
        $this->protectedRelationshipPropertyWithMultiple = $relationship;

    }
}
