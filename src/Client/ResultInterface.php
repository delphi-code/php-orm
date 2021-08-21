<?php

namespace delphi\ORM\Client;

interface ResultInterface
{
    /**
     * @return RecordInterface[]
     */
    public function records(): array;
}
