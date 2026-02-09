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

use Eloquent\Pops\ProxyClass;
use LogicException;
use ReflectionClass;
use ReflectionProperty;

/**
 * A class proxy that circumvents access modifier restrictions.
 */
class LiberatorClass extends ProxyClass implements LiberatorProxyInterface
{
    /**
     * Get the non-static class proxy for this class.
     *
     * @return LiberatorClass The non-static class proxy.
     */
    public static function liberator(): LiberatorClass
    {
        return static::popsProxy();
    }

    /**
     * Call a static method on the proxied class with support for by-reference
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
     * Set the wrapped class.
     *
     * @param string $class The class to wrap.
     *
     * @throws InvalidTypeException If the supplied value is not the correct type.
     */
    public function setPopsValue(mixed $class): void
    {
        parent::setPopsValue($class);

        $this->liberatorReflector = new ReflectionClass($class);
    }

    /**
     * Call a static method on the proxied class with support for by-reference
     * arguments.
     *
     * @param string $method     The name of the method to call.
     * @param array  &$arguments The arguments.
     *
     * @return mixed The result of the method call.
     */
    public function popsCall(string $method, array &$arguments): mixed
    {
        if ($this->liberatorReflector()->hasMethod($method)) {
            $method = $this->liberatorReflector()->getMethod($method);
            if ($this->liberatorUseSetAccessible()) {
                $method->setAccessible(true);
                return static::popsProxySubValue(
                    $method->invokeArgs(null, $arguments),
                    $this->isPopsRecursive()
                );
            }

            $declaringClass = $method->getDeclaringClass()->getName();
            $methodName = $method->getName();
            $methodInvoker = \Closure::bind(
                static function (string $method, array &$arguments) {
                    return self::{$method}(...$arguments);
                },
                null,
                $declaringClass
            );

            return static::popsProxySubValue(
                $methodInvoker($methodName, $arguments),
                $this->isPopsRecursive()
            );
        }

        return parent::popsCall($method, $arguments);
    }

    /**
     * Set the value of a static property on the proxied class.
     *
     * @param string $property The name of the property to set.
     * @param mixed  $value    The new value.
     */
    public function __set(string $property, mixed $value): void
    {
        if ($propertyReflector = $this->liberatorPropertyReflector($property)) {
            if ($this->liberatorUseSetAccessible()) {
                $propertyReflector->setValue(null, $value);
            } else {
                $declaringClass = $propertyReflector->getDeclaringClass()->getName();
                $propertyAccessor = \Closure::bind(
                    static function & (string $property) {
                        return self::${$property};
                    },
                    null,
                    $declaringClass
                );
                $targetProperty = &$propertyAccessor($property);
                $targetProperty = $value;
            }

            return;
        }

        throw new LogicException(
            sprintf(
                'Access to undeclared static property: %s::$%s',
                $this->popsValue(),
                $property
            )
        );
    }

    /**
     * Get the value of a static property on the proxied class.
     *
     * @param string $property The name of the property to get.
     *
     * @return mixed The value of the property.
     */
    public function __get(string $property): mixed
    {
        if ($propertyReflector = $this->liberatorPropertyReflector($property)) {
            if (!$this->liberatorUseSetAccessible()) {
                $declaringClass = $propertyReflector->getDeclaringClass()->getName();
                $propertyAccessor = \Closure::bind(
                    static function & (string $property) {
                        return self::${$property};
                    },
                    null,
                    $declaringClass
                );
                $propertyValue = $propertyAccessor($property);

                return static::popsProxySubValue(
                    $propertyValue,
                    $this->isPopsRecursive()
                );
            }

            return static::popsProxySubValue(
                $propertyReflector->getValue(null),
                $this->isPopsRecursive()
            );
        }

        throw new LogicException(
            sprintf(
                'Access to undeclared static property: %s::$%s',
                $this->popsValue(),
                $property
            )
        );
    }

    /**
     * Returns true if the supplied static property exists on the proxied class.
     *
     * @param string $property The name of the property to search for.
     *
     * @return bool True if the property exists.
     */
    public function __isset(string $property): bool
    {
        if ($propertyReflector = $this->liberatorPropertyReflector($property)) {
            if ($this->liberatorUseSetAccessible()) {
                return null !== $propertyReflector->getValue(null);
            }

            $declaringClass = $propertyReflector->getDeclaringClass()->getName();
            $propertyAccessor = \Closure::bind(
                static function & (string $property) {
                    return self::${$property};
                },
                null,
                $declaringClass
            );
            $propertyValue = $propertyAccessor($property);

            return null !== $propertyValue;
        }

        return parent::__isset($property);
    }

    /**
     * Set the value of a static property on the proxied class to null.
     *
     * @param string $property The name of the property to set.
     */
    public function __unset(string $property): void
    {
        if ($propertyReflector = $this->liberatorPropertyReflector($property)) {
            if ($this->liberatorUseSetAccessible()) {
                $propertyReflector->setValue(null, null);
            } else {
                $declaringClass = $propertyReflector->getDeclaringClass()->getName();
                $propertyAccessor = \Closure::bind(
                    static function & (string $property) {
                        return self::${$property};
                    },
                    null,
                    $declaringClass
                );
                $propertyValue = &$propertyAccessor($property);
                $propertyValue = null;
            }

            return;
        }

        throw new LogicException(
            sprintf(
                'Access to undeclared static property: %s::$%s',
                $this->popsValue(),
                $property
            )
        );
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
     * @return ReflectionClass The class reflector.
     */
    protected function liberatorReflector(): ReflectionClass
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

    private ReflectionClass $liberatorReflector;

    private function liberatorUseSetAccessible(): bool
    {
        return \PHP_VERSION_ID < 80500
            && ('1' !== getenv('LIBERATOR_FORCE_BOUND_ACCESS'));
    }
}
