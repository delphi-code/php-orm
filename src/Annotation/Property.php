<?php

namespace delphi\ORM\Annotation;

use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
class Property
{
    /**
     * @Required
     */
    public string $name;

    public bool $unique = false;
}
