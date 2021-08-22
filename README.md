# ORM
Basic structure to handle persisting objects in a GraphDB

⚠ Does not handle any sort of data extraction yet. This is effectively write-only for now.

# Usage

## Setup
```php
// Create a new entity manager, but first we need...

// ... a DB client object (See delphi/client-laudis for an *actual* implementation) ...
$client = new class implements \delphi\ORM\Client\ClientInterface {};

// ... a QueryBuilder (with its own dependencies) ...
$annotationReader = new \Doctrine\Common\Annotations\AnnotationReader();
$annotationDriver = new \delphi\ORM\Driver\AnnotationDriver($annotationReader);
$queryBuilder = new \delphi\ORM\QueryBuilder($annotationDriver);

$em = new \delphi\ORM\EntityManager($client, $queryBuilder);
```

## Configuration
```php
namespace App;
use delphi\ORM\Annotation as OGM;

/**
 * @OGM\Entity(label="Foo") 
 */
class Foo {
    /**
     * @OGM\Property(name="name") 
     */
    public string $name = '';
    
    /**
     * @OGM\Relationship(type="CHILD_OF", targetEntity="\App\Bar") 
     */
    public Bar $parentBar;
}
```
* Set the object as an `Entity(label="LabelVal")`
  * `label` is the string to be used as the label inside of the GraphDB
* Set individual properties as a `Property(name="propertyName", unique=true)`
  * `name` is the name to be given to the property
  * `unique` is the optional flag to say a particular property is unique amongst all entities with this label. Defaults to `false`.
* Set relationships as `Relationship(type="RELATIONSHIP_TYPE", targetEntity="\App\Bar", multiple=false)`
  * `type` is the name given to the edge (relationship) inside of the GraphDB
  * `targetEntity` is the PHP class name odf the entity on the other end of the relationship
  * `multiple` is the optional flag to say whether a relationship is 1-to-1 or 1-to-many

Otherwise, entities are plain-old-PHP-objects, and you can do with them what you want.

If you want to have the properties _not_ be public, you can add getter methods for individual properties. See `\delphi\ORM\Util\PropertyGetter`.


## Execution
```php
// Create a new entity...
$entity = new Foo();
$entity->name = 'Nombre';
$entity->parentBar = new Bar();

// ... and set it to be persisted
$em->persist($entity);

// There won't be any DB interactions performed until...

// ...actually save the entity off to the DB
$em->flush();
```

This will result in 2 nodes with 1 edge between them.

```
+---------------+
|      Foo      |
| name="Nombre" |
+---------------+
  |
  | CHILD_OF
  v
+---------------+
|      Bar      |
+---------------+
```
