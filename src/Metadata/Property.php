<?php

namespace delphi\ORM\Metadata;

class Property
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $propertyOnObject;

    /**
     * @var bool
     */
    public $unique;

    public function __construct($name = '', $unique = false, $propertyOnObject = '')
    {
        $this->name = $name;
        $this->unique = $unique;
        $this->propertyOnObject = $propertyOnObject;
    }
}
