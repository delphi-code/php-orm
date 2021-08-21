<?php

namespace delphi\ORM\Tests\Fixtures;

use delphi\ORM\Client\RecordInterface;

class TestRecord implements RecordInterface {
    protected $map = [];

    public function __construct(array $map)
    {
        $this->map = $map;
    }

    public function get(string $key, $defaultValue = null)
    {
        if (!array_key_exists($key, $this->map)) {
            return $defaultValue;
        }
        return $this->map[$key];
    }

}
