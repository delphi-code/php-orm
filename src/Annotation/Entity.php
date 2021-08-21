<?php

namespace delphi\ORM\Annotation;

use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target("CLASS")
 */
class Entity
{
    /**
     * @Required
     */
    public string $label;
}
