<?php

namespace delphi\ORM;

use delphi\ORM\Metadata\Relationship;

class IncorrectRelationshipType extends \Exception {
    public function __construct(
        Relationship $relationship,
                     $actualValue,
        object       $entity
    )
    {
        parent::__construct(sprintf(
            'Incorrect type for %s returned from %s (expected %s, received %s)',
            $relationship->propertyOnObject,
            get_class($entity),
            $relationship->targetEntity,
            is_object($actualValue) ? get_class($actualValue) : gettype($actualValue)
        ));
    }
}
