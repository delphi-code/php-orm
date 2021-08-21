<?php

namespace delphi\ORM\Tests\Fixtures;

use delphi\ORM\Client\ResultInterface;

class TestResult implements ResultInterface {
    protected array $recordObjects = [];

    public function __construct(array $records)
    {
        $this->recordObjects = $records;
    }

    public function records(): array
    {
        return $this->recordObjects;
    }

}
