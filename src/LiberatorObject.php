<?php

/*
 * This file is part of the Liberator package.
 *
 * Copyright © 2014 Erin Millard
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Eloquent\Liberator;

use Eloquent\Pops\Exception\InvalidTypeException;
use Eloquent\Pops\ProxyObject;
use ReflectionObject;
use ReflectionProperty;

/**
 * An object proxy that circumvents access modifier restrictions.
 */
class LiberatorObject extends ProxyObject implements LiberatorProxyInterface
{
    /**
     * Call a method on the wrapped object with support for by-reference
     * arguments.
     *
     * @param string $method     The name of the method to call.
     * @param array  &$arguments The arguments.
     *
     * @return mixed The result of the method call.
     */
    public function liberatorCall(string $method, array &$arguments): mixed
    {
        return $this->popsCall($method, $arguments);
    }

    /**
     * Set the wrapped object.
     *
     * @param string $object The object to wrap.
     *
     * @throws InvalidTypeException If the supplied value is not the correct type.
     */
    public function setPopsValue(mixed $object): void
    {
        parent::setPopsValue($object);

        $this->liberatorReflector = new ReflectionObject($object);
    }

    /**
     * Call a method on the wrapped object with support for by-reference
     * arguments.
     *
     * @param string $method     The name of the method to call.
     * @param array  &$arguments The arguments.
     *
     * @return mixed                The result of the method call.
     * @throws \ReflectionException
     */
    public function popsCall(string $method, array &$arguments): mixed
    {
        if ($this->liberatorReflector->hasMethod($method)) {
            $method = $this->liberatorReflector->getMethod($method);
            if ($this->liberatorUseSetAccessible()) {
                $method->setAccessible(true);

                return $this->popsProxySubValue(
                    $method->invokeArgs($this->popsValue(), $arguments)
                );
            }

            $declaringClass = $method->getDeclaringClass()->getName();
            $methodInvoker = \Closure::bind(
                function (string $method, array &$arguments) {
                    return $this->{$method}(...$arguments);
                },
                $this->popsValue(),
                $declaringClass
            );

            return $this->popsProxySubValue($methodInvoker($method, $arguments));
        }

        return parent::popsCall($method, $arguments);
    }

    /**
     * Set the value of a property on the wrapped object.
     *
     * @param string $property The property name.
     * @param mixed  $value    The new value.
     */
    public function __set(string $property, mixed $value): void
    {
        if ($propertyReflector = $this->liberatorPropertyReflector($property)) {
            if ($this->liberatorUseSetAccessible()) {
                $propertyReflector->setValue($this->popsValue(), $value);
            } else {
                $declaringClass = $propertyReflector->getDeclaringClass()->getName();
                $propertyAccessor = \Closure::bind(
                    function &(string $property) {
                        return $this->{$property};
                    },
                    $this->popsValue(),
                    $declaringClass
                );
                $targetProperty = &$propertyAccessor($property);
                $targetProperty = $value;
            }

            return;
        }

        parent::__set($property, $value);
    }

    /**
     * Get the value of a property from the wrapped object.
     *
     * @param string $property The property name.
     *
     * @return mixed The property value.
     */
    public function __get(string $property): mixed
    {
        if ($propertyReflector = $this->liberatorPropertyReflector($property)) {
            if (!$this->liberatorUseSetAccessible()) {
                $declaringClass = $propertyReflector->getDeclaringClass()->getName();
                $propertyAccessor = \Closure::bind(
                    function &(string $property) {
                        return $this->{$property};
                    },
                    $this->popsValue(),
                    $declaringClass
                );
                $propertyValue = $propertyAccessor($property);

                return $this->popsProxySubValue($propertyValue);
            }

            return $this->popsProxySubValue(
                $propertyReflector->getValue($this->popsValue())
            );
        }

        return parent::__get($property);
    }

    /**
     * Returns true if the property exists on the wrapped object.
     *
     * @param string $property The name of the property to search for.
     *
     * @return bool True if the property exists.
     */
    public function __isset(string $property): bool
    {
        if ($propertyReflector = $this->liberatorPropertyReflector($property)) {
            if ($this->liberatorUseSetAccessible()) {
                return null !== $propertyReflector->getValue($this->popsValue());
            }

            $declaringClass = $propertyReflector->getDeclaringClass()->getName();
            $propertyAccessor = \Closure::bind(
                function &(string $property) {
                    return $this->{$property};
                },
                $this->popsValue(),
                $declaringClass
            );
            $propertyValue = $propertyAccessor($property);

            return null !== $propertyValue;
        }

        return parent::__isset($property);
    }

    /**
     * Unset a property from the wrapped object.
     *
     * @param string $property The property name.
     */
    public function __unset(string $property): void
    {
        if ($propertyReflector = $this->liberatorPropertyReflector($property)) {
            if ($this->liberatorUseSetAccessible()) {
                $propertyReflector->setValue($this->popsValue(), null);
            } else {
                $declaringClass = $propertyReflector->getDeclaringClass()->getName();
                $propertyAccessor = \Closure::bind(
                    function &(string $property) {
                        return $this->{$property};
                    },
                    $this->popsValue(),
                    $declaringClass
                );
                $propertyValue = &$propertyAccessor($property);
                $propertyValue = null;
            }

            return;
        }

        parent::__unset($property);
    }

    /**
     * Get the proxy class.
     *
     * @return string The proxy class.
     */
    protected static function popsProxyClass(): string
    {
        return 'Eloquent\Liberator\Liberator';
    }

    /**
     * Get the class reflector.
     *
     * @return ReflectionObject The class reflector.
     */
    protected function liberatorReflector(): ReflectionObject
    {
        return $this->liberatorReflector;
    }

    /**
     * Get a property reflector.
     *
     * @param string $property The property name.
     *
     * @return ReflectionProperty|null The property reflector, or null if no such property exists.
     */
    protected function liberatorPropertyReflector(string $property): ?ReflectionProperty
    {
        $classReflector = $this->liberatorReflector();

        while ($classReflector) {
            if ($classReflector->hasProperty($property)) {
                $propertyReflector = $classReflector->getProperty($property);
                if ($this->liberatorUseSetAccessible()) {
                    $propertyReflector->setAccessible(true);
                }

                return $propertyReflector;
            }

            $classReflector = $classReflector->getParentClass();
        }

        return null;
    }

    private ReflectionObject $liberatorReflector;

    private function liberatorUseSetAccessible(): bool
    {
        return \PHP_VERSION_ID < 80500
            && ('1' !== getenv('LIBERATOR_FORCE_BOUND_ACCESS'));
    }
}
