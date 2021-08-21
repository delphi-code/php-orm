<?php

namespace delphi\ORM\Util;

use ReflectionProperty;

class PropertyGetter {
    public function getPropertyValue(object $obj, string $propertyName)
    {
        // Try direct first
        $methodName = 'get' . ucfirst($propertyName);
        if (method_exists($obj, $methodName)) {
            return $obj->$methodName();
        }

        // Deal with underscores
        if (strpos($propertyName, '_') !== false) {
            $parts      = explode('_', $propertyName);
            $partsU     = array_map('ucfirst', $parts);
            $methodName = 'get' . implode('', $partsU);
            if (method_exists($obj, $methodName)) {
                return $obj->$methodName();
            }
        }

        // Last ditch effort to try reflection
        $actualProp = new ReflectionProperty($obj, $propertyName);
        $actualProp->setAccessible(true);
        return $actualProp->getValue($obj);
    }
}
