<?php

namespace delphi\ORM;

use delphi\ORM\Driver\AnnotationDriver;
use delphi\ORM\Metadata\Property;
use delphi\ORM\Util\PropertyGetter;
use Exception;
use ReflectionClass;
use ReflectionProperty;

class QueryBuilder {
    protected AnnotationDriver $metadataDriver;

    protected PropertyGetter $getter;

    public function __construct(AnnotationDriver $driver, PropertyGetter $getter = null)
    {
        $this->metadataDriver = $driver;
        $this->getter = $getter ?? new PropertyGetter();
    }

    public function getSavedUUIDFieldName(): string
    {
        return 'saved_uuid';
    }

    public function getAttemptedUUIDFieldName(): string
    {
        return 'attempted_uuid';
    }

    public function getMetadataDriver(): AnnotationDriver
    {
        return $this->metadataDriver;
    }

    /**
     * @param object|string $obj
     * @param string        $woundParamName
     */
    public function makeStatementForType($obj, string $woundParamName): string
    {
        // Get info from object
        $metadata = $this->metadataDriver->getMetadataForEntity(new ReflectionClass($obj));

        // Arbitrary
        $nonUniqueMapRef = 'props'; // Should be common for all, since its just a bucket of properties
        $unwoundItemRef  = 'item'; // Completely self contained
        $innerRef        = 'ref'; // Completely self contained

        // Specific
        $pieces    = array_map(fn(Property $k) => $k->name . ':' . $unwoundItemRef . '.' . $k->name, $metadata->uniqueProperties);
        $uniqueMap = '{' . implode(', ', $pieces) . '}';

        // Make the statement
        $stmt = sprintf('UNWIND {%s} as %s', $woundParamName, $unwoundItemRef) . PHP_EOL;
        $stmt .= $this->makeMergeStmt($innerRef, $metadata->label, $uniqueMap, $unwoundItemRef, $nonUniqueMapRef) . PHP_EOL;
        $stmt .= sprintf(' RETURN %s.uuid as %s, %s.uuid as %s', $innerRef, $this->getSavedUUIDFieldName(), $unwoundItemRef, $this->getAttemptedUUIDFieldName());

        return trim($stmt);
    }

    public function makeParamsForEntity($entity): array
    {
        $metadata = $this->metadataDriver->getMetadataForEntity(new ReflectionClass($entity));

        $props = [];
        foreach ($metadata->properties as $property) {
            $props[$property->propertyOnObject] = $this->getter->getPropertyValue($entity, $property->propertyOnObject);
        }

        $out = [];
        foreach ($metadata->uniqueProperties as $u) {
            // Set directly to object
            $out[$u->name] = $this->getter->getPropertyValue($entity, $u->propertyOnObject);
        }

        // Relationships stuff
        foreach ($metadata->relationships as $relationship) {
            $out[$relationship->propertyOnObject] = $this->getRelationshipValue($relationship, $entity);

            // Remove from the props array
            unset($props[$relationship->propertyOnObject]);
        }

        $out['props'] = $props;

        // TODO: Replace with an actual UUID implementation
        $out['uuid'] = 'uuid_' . spl_object_hash($entity) . date('Y-m-d H:i:s');
        return $out;
    }

    private function makeMergeStmt(string $ref, $label, string $uniqueMap, string $unwoundItemRef, string $nonUniqueMap): string
    {
        $stmt = '';
        if ($label) {
            $stmt .= sprintf('MERGE (%s:%s %s)', $ref, $label, $uniqueMap) . PHP_EOL;
        } else {
            $stmt .= sprintf('MERGE (%s %s)', $ref, $uniqueMap) . PHP_EOL;
        }
        $stmt .= sprintf(' ON MATCH SET %s += %s.%s', $ref, $unwoundItemRef, $nonUniqueMap) . PHP_EOL;
        $stmt .= sprintf(' ON CREATE SET %s += %s.%s, %s.uuid = %s.uuid', $ref, $unwoundItemRef, $nonUniqueMap, $ref, $unwoundItemRef) . PHP_EOL;
        return trim($stmt);
    }

    public function makeStatementForEdge(string $type, object $from, object $to)
    {
        $fromMetadata = $this->metadataDriver->getMetadataForEntity(new ReflectionClass($from));
        $toMetadata   = $this->metadataDriver->getMetadataForEntity(new ReflectionClass($to));

        $stmt = sprintf('MATCH (fromObj:%s {uuid: $fromUUID}), (toObj:%s {uuid: $toUUID}) MERGE (fromObj)-[r:%s]->(toObj)',
            $fromMetadata->label,
            $toMetadata->label,
            $type,
        );
        $stmt = str_replace(PHP_EOL, ' ', $stmt);
        return $stmt;
    }

    public function makeParamsForEdge(object $from, object $to): array {
        if (!isset($from->uuid) || !isset($to->uuid)) {
            throw new Exception('Entities must be saved before edges can be created');
        }
        return [
            'fromUUID' => $from->uuid,
            'toUUID'   => $to->uuid,
        ];
    }

    protected function getRelationshipValue(Metadata\Relationship $relationship, $entity): ?array
    {
        // Set parsed version directly to object (if it exists)
        $related = $this->getter->getPropertyValue($entity, $relationship->propertyOnObject);

        if (!$relationship->multiple) {
            if (is_array($related)) {
                throw new Exception(sprintf(
                    'Tried to set multiple values (%d) to a single relationship field %s::%s',
                    count($related),
                    get_class($entity),
                    $relationship->propertyOnObject
                ));
            }

            if ($related === null) {
                return null;
            }

            return $this->makeParamsForEntity($related);
        }

        // Allows for multiple

        if ($related === null) {
            $related = [];
        }

        if (!is_array($related)) {
            $related = [$related];
        }

        return array_map(fn($r) => $this->makeParamsForEntity($r), $related);
    }

}
