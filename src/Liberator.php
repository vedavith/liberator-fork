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
use Eloquent\Pops\Proxy;
use Eloquent\Pops\ProxyClassInterface;
use Eloquent\Pops\ProxyInterface;

/**
 * A proxy that circumvents access modifier restrictions.
 */
class Liberator extends Proxy
{
    /**
     * Wrap the supplied value in a liberator proxy.
     *
     * @param mixed     $value       The value to wrap.
     * @param bool|null $isRecursive True if the value should be recursively proxied.
     *
     * @return ProxyInterface The proxied value.
     */
    public static function liberate(mixed $value, ?bool $isRecursive = null): ProxyInterface
    {
        return static::proxy($value, $isRecursive);
    }

    /**
     * Wrap the supplied class in a non-static liberator proxy.
     *
     * @param string    $class       The name of the class to wrap.
     * @param bool|null $isRecursive True if the class should be recursively proxied.
     *
     * @return ProxyClassInterface  The non-static class proxy.
     * @throws InvalidTypeException If the supplied value is not the correct type.
     */
    public static function liberateClass(string $class, ?bool $isRecursive = null): ProxyClassInterface
    {
        return static::proxyClass($class, $isRecursive);
    }

    /**
     * Wrap the supplied class in a static liberator proxy.
     *
     * @param string      $class       The name of the class to wrap.
     * @param bool|null   $isRecursive True if the class should be recursively proxied.
     * @param string|null $proxyClass  The class name to use for the proxy class.
     *
     * @return string The static class proxy.
     */
    public static function liberateClassStatic(
        string $class,
        ?bool $isRecursive = null,
        ?string $proxyClass = null
    ): string {
        return static::proxyClassStatic($class, $isRecursive, $proxyClass);
    }

    /**
     * Get the array proxy class.
     *
     * @return string The array proxy class.
     */
    protected static function proxyArrayClass(): string
    {
        return 'Eloquent\Liberator\LiberatorArray';
    }

    /**
     * Get the class proxy class.
     *
     * @return string The class proxy class.
     */
    protected static function proxyClassClass(): string
    {
        return 'Eloquent\Liberator\LiberatorClass';
    }

    /**
     * Get the object proxy class.
     *
     * @return string The object proxy class.
     */
    protected static function proxyObjectClass(): string
    {
        return 'Eloquent\Liberator\LiberatorObject';
    }
}
