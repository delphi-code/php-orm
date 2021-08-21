<?php

namespace delphi\ORM\Client;

interface RecordInterface
{
    /**
     * Retrieve the value for the given <code>key</code>.
     *
     * @param string $key          The identifier key
     * @param mixed  $defaultValue A default value to return in case the record doesn't contains the given <code>key</code>.
     *
     * @return mixed
     */
    public function get(string $key, $defaultValue = null);
}
