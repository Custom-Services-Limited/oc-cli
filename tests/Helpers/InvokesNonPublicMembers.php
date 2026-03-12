<?php

namespace OpenCart\CLI\Tests\Helpers;

trait InvokesNonPublicMembers
{
    /**
     * @param object $object
     * @param string $method
     * @param mixed ...$arguments
     * @return mixed
     */
    protected function invokeMethod($object, $method, ...$arguments)
    {
        $callable = \Closure::bind(
            function (...$arguments) use ($method) {
                return $this->{$method}(...$arguments);
            },
            $object,
            get_class($object)
        );

        return $callable(...$arguments);
    }

    /**
     * @param object $object
     * @param string $property
     * @param mixed $value
     * @return void
     */
    protected function setProperty($object, $property, $value)
    {
        $setter = \Closure::bind(
            function ($value) use ($property) {
                $this->{$property} = $value;
            },
            $object,
            get_class($object)
        );

        $setter($value);
    }
}
