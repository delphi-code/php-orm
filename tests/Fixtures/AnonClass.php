<?php

namespace delphi\ORM\Tests\Fixtures;

class AnonClass
{
    public function __construct($props = [])
    {
        foreach ($props as $k => $v) {
            $this->$k = $v;
        }
    }
}
