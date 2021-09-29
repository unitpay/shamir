<?php

declare(strict_types=1);

namespace Unitpay\Shamir\Tests;

use ReflectionClass;
use ReflectionException;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @param string $class Class name
     * @param string $methodName Method name
     * @param array $params Method params to be passed
     *
     * @throws ReflectionException
     *
     * @return mixed
     */
    public function invokeStaticMethod(string $class, string $methodName, array $params = [])
    {
        $method = (new ReflectionClass($class))->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs(null, $params);
    }
}
