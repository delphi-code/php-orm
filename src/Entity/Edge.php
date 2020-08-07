<?php
declare(strict_types=1);

namespace delphi\ORM\Entity;

class Edge
{
    public object $fromObj;

    public string $type;

    public object $toObj;

    /**
     * Edge constructor.
     *
     * @param object $fromObj
     * @param string $type
     * @param object $toObj
     */
    public function __construct(object $fromObj, string $type, object $toObj)
    {
        $this->fromObj = $fromObj;
        $this->type    = $type;
        $this->toObj   = $toObj;
    }
}
