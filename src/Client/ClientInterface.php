<?php

namespace delphi\ORM\Client;

interface ClientInterface
{
    /**
     * Run a Cypher statement against the default database or the database specified.
     *
     * @param $query
     * @param null|array  $parameters
     * @param null|string $tag
     * @param null|string $connectionAlias
     */
    public function run($query, array $parameters = null, string $tag = null, string $connectionAlias = null): ResultInterface;
}
