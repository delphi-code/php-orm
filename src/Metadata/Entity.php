<?php

namespace delphi\ORM\Metadata;

class Entity
{
    /**
     * @var string
     */
    public $label;

    /**
     * @var Property[]
     */
    public array $properties = [];

    /**
     * @var Property[]
     */
    public array $uniqueProperties = [];

    /**
     * @var Relationship[]
     */
    public array $relationships = [];

    public function __construct($label = '')
    {
        $this->label = $label;
    }
}
