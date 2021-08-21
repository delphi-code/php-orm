<?php

namespace delphi\ORM\Tests\Fixtures;

use delphi\ORM\Driver\AnnotationDriver;
use delphi\ORM\QueryBuilder;
use Doctrine\Common\Annotations\AnnotationReader;

class FakeQueryBuilder extends QueryBuilder {
    public function __construct()
    {
        $driver = new AnnotationDriver(new AnnotationReader());
        parent::__construct($driver);
    }

    public function makeStatementForType($obj, string $woundParamName): string
    {
        return 'STMT for ' . get_class($obj) . ' using ' . $woundParamName;
    }

    public function makeParamsForEntity($entity): array
    {
        $makeParamsForEntity         = parent::makeParamsForEntity($entity);
        $makeParamsForEntity['uuid'] = spl_object_id($entity);
        return $makeParamsForEntity;
    }
}
