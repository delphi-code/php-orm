<?php

namespace delphi\ORM\Metadata;

class Relationship
{
    /**
     * @example DEFINED_IN
     * @var string
     */
    public $type;

    /**
     * @example \delphi\ORM\Metadata\Relationship
     * @var string FQDN for class
     */
    public $targetEntity;

    /**
     * @example defining_file
     * @var string
     */
    public $propertyOnObject;

    /**
     * Whether multiple values are allowed
     * @var bool
     */
    public $multiple = false;

    public function __construct($type = '', $targetEntity = '', $propertyOnObject = '', $multiple = false)
    {
        $this->type = $type;
        $this->targetEntity = $targetEntity;
        $this->propertyOnObject = $propertyOnObject;
        $this->multiple = $multiple;
    }
}
