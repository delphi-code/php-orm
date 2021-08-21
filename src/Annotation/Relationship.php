<?php

namespace delphi\ORM\Annotation;

use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target("PROPERTY")
 */
class Relationship
{
    /**
     * @Required()
     */
    public string $type;

    /**
     * @Required()
     */
    public string $targetEntity;

    /**
     * Whether multiple values are allowed
     */
    public bool $multiple = false;
}
