<?php

namespace delphi\ORM\Driver;

use delphi\ORM\Annotation\Entity as EntityAnnotation;
use delphi\ORM\Annotation\Property as PropertyAnnotation;
use delphi\ORM\Annotation\Relationship as RelationshipAnnotation;
use delphi\ORM\Metadata\Entity;
use delphi\ORM\Metadata\Property;
use delphi\ORM\Metadata\Relationship;
use Doctrine\Common\Annotations\AnnotationReader;
use ReflectionClass;
use ReflectionProperty;

class AnnotationDriver
{
    private AnnotationReader $annotationReader;

    public function __construct(AnnotationReader $annotationReader)
    {
        $this->annotationReader = $annotationReader;
    }

    public function getLabel(ReflectionClass $reflectionClass): ?string
    {
        /** @var EntityAnnotation $annotation */
        $annotation = $this->annotationReader->getClassAnnotation($reflectionClass, EntityAnnotation::class);

        if ($annotation === null) {
            return null;
        }
        return $annotation->label;
    }

    public function getMetadataForEntity(ReflectionClass $reflectionClass): Entity
    {
        $md = new Entity();

        $md->label = $this->getLabel($reflectionClass);

        // Get properties
        foreach ($reflectionClass->getProperties() as $prop) {
            // Load up Properties
            $thisProps = $this->getPropertiesForProperty($prop);

            if ($thisProps !== null) {
                if ($thisProps->unique) {
                    $md->uniqueProperties[] = $thisProps;
                } else {
                    $md->properties[] = $thisProps;
                }
            }

            // Load up relationships
            $relInfo = $this->getRelationshipsForProperty($prop);
            if ($relInfo) {
                $md->relationships[] = $relInfo;
            }
        }
        return $md;
    }

    public function getRelationshipsForProperty(ReflectionProperty $prop): ?Relationship
    {
        /** @var RelationshipAnnotation $relationshipAnnotation */
        $relationshipAnnotation = $this->annotationReader->getPropertyAnnotation($prop, RelationshipAnnotation::class);

        if (!$relationshipAnnotation) {
            return null;
        }

        // Ensure target entity exists
        if (!class_exists($relationshipAnnotation->targetEntity)
            && !interface_exists($relationshipAnnotation->targetEntity)
            && !trait_exists($relationshipAnnotation->targetEntity)
        ) {
            throw new DriverException(sprintf('Class defined for relationship on %s::%s does not exist (%s).', $prop->getDeclaringClass()->getName(), $prop->getName(), $relationshipAnnotation->targetEntity));
        }

        return new Relationship(
            $relationshipAnnotation->type,
            $relationshipAnnotation->targetEntity,
            $prop->getName(),
            $relationshipAnnotation->multiple,
        );
    }

    public function getPropertiesForProperty(ReflectionProperty $prop): ?Property
    {
        /** @var PropertyAnnotation $propertyAnnotation */
        $propertyAnnotation = $this->annotationReader->getPropertyAnnotation($prop, PropertyAnnotation::class);

        if (!$propertyAnnotation) {
            return null;
        }

        return new Property(
            $propertyAnnotation->name ?? $prop->getName(),
            $propertyAnnotation->unique,
            $prop->getName()
        );
    }
}
