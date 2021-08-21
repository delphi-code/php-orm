<?php

namespace delphi\ORM\Tests\Fixtures;

use delphi\ORM\Metadata\Entity;
use delphi\ORM\Metadata\Property;
use delphi\ORM\Metadata\Relationship;

class MetadataEntity extends Entity
{
    /**
     * @var callable
     */
    public $register;

    /**
     * @var
     */
    public $class;

    /**
     * @return mixed
     */
    public function getClass()
    {
        return $this->class;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function setProperties(array $properties): self
    {
        $this->properties = $properties;
        return $this;
    }

    public function addProperty($name = '', $propertyOnObject = ''): self
    {
        $prop               = new Property($name, false, $propertyOnObject);
        $this->properties[] = $prop;
        return $this;
    }

    public function addUniqueProperty($name = '', $propertyOnObject = ''): self
    {
        $prop                     = new Property($name, true, $propertyOnObject);
        $this->uniqueProperties[] = $prop;
        return $this;
    }

    public function setUniqueProperties(array $uniqueProperties): self
    {
        $this->uniqueProperties = $uniqueProperties;
        return $this;
    }

    public function setRelationships(array $relationships): self
    {
        $this->relationships = $relationships;
        return $this;
    }

    public function addRelationship($type, $propOnObject, $multiple = false, $target = null): self
    {
        if (null === $target) {
            $target = new class
            {
            };
            ($this->register)($target);
        }

        $r                     = new Relationship($type, $target, $propOnObject, $multiple);
        $this->relationships[] = $r;
        return $this;
    }

}
