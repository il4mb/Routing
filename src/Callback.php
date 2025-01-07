<?php

namespace Il4mb\Routing;

use Il4mb\Routing\Map\RouteParam;
use ReflectionClass;

class Callback
{
    private string $method;
    private object $object;

    private function __construct(string $method, object $object)
    {
        $this->method = $method;
        $this->object = $object;
    }

    public function __invoke()
    {
        $payload = [];
        $arguments = func_get_args();
        $parameters = $this->getParameters();

        foreach ($parameters as $i => $parameter) {
            $matched = false;

            if ($parameter->hasType()) {
                $expectedType = (string)$parameter->getType();

                foreach ($arguments as $key => $argument) {
                    if (
                        ($expectedType === 'int' && is_int($argument)) ||
                        ($expectedType === 'string' && is_string($argument)) ||
                        ($expectedType === 'bool' && is_bool($argument)) ||
                        ($expectedType === 'float' && is_float($argument)) ||
                        (class_exists($expectedType) && is_a($argument, $expectedType, true)) ||
                        (interface_exists($expectedType) && in_array($expectedType, class_implements($argument)))
                    ) {
                        $payload[] = $argument;
                        unset($arguments[$key]);
                        $matched = true;
                        break;
                    }
                }
            } else {

                foreach ($arguments as $argument) {
                    if ($argument instanceof RouteParam && $argument->name == $parameter->getName()) {
                        $payload[] = urldecode($argument->value);
                        $matched = true;
                        break;
                    }
                }
            }

            if (!$matched) {
                if ($parameter->isDefaultValueAvailable()) {
                    $payload[] = $parameter->getDefaultValue();
                } else {
                    $payload[] = null;
                }
            }
        }

        return call_user_func_array([$this->object, $this->method], $payload);
    }


    function getParameters()
    {
        $className = get_class($this->object);
        $reflectionClass = new ReflectionClass($className);
        return $reflectionClass->getMethod($this->method)->getParameters();
    }

    function __debugInfo()
    {
        return [
            "object" => $this->object,
            "method" => $this->method
        ];
    }

    static function create(string $method, object $object)
    {
        return new Callback($method, $object);
    }
}
