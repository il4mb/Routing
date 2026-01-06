<?php

namespace Il4mb\Routing;

use Closure;
use Il4mb\Routing\Binding\ParameterResolver;
use Il4mb\Routing\Binding\Resolvers\ValueObjectResolver;
use Il4mb\Routing\Map\RouteParam;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use ReflectionClass;

class Callback
{
    private string $method;
    private object $object;
    /** @var list<ParameterResolver> */
    private array $parameterResolvers;

    /**
     * @param list<ParameterResolver> $parameterResolvers
     */
    private function __construct(string $method, object $object, array $parameterResolvers = [])
    {
        $this->method = $method;
        $this->object = $object;
        $this->parameterResolvers = $parameterResolvers;
    }

    public function __invoke()
    {
        $payload = [];
        $arguments = func_get_args();
        $parameters = $this->getParameters();

        $routeParamValues = [];
        foreach ($arguments as $argument) {
            if ($argument instanceof RouteParam && $argument->name !== null) {
                // Values are already decoded at the router/path-matching layer.
                $routeParamValues[$argument->name] = $argument->value;
            }
        }

        foreach ($parameters as $i => $parameter) {
            $matched = false;

            if ($parameter->hasType()) {
                $name = $parameter->getName();
                $types = $this->flattenTypes($parameter->getType());

                // Prefer binding from named RouteParam values when possible.
                if (array_key_exists($name, $routeParamValues) && $this->typesAcceptScalarCapture($types)) {
                    $payload[] = $this->castRouteParamValue($routeParamValues[$name], $types, $parameter);
                    $matched = true;
                } else {
                    // Custom resolvers (e.g. value objects from capture/header).
                    foreach ($this->parameterResolvers as $resolver) {
                        $resolution = $resolver->resolve($parameter, $routeParamValues, $arguments);
                        if ($resolution->matched) {
                            $payload[] = $resolution->value;
                            $matched = true;
                            break;
                        }
                    }

                    if ($matched) {
                        continue;
                    }

                    foreach ($arguments as $key => $argument) {
                        if ($this->argumentMatchesAnyType($argument, $types)) {
                            $payload[] = $argument;
                            unset($arguments[$key]);
                            $matched = true;
                            break;
                        }
                    }
                }
            } else {
                $name = $parameter->getName();
                if (array_key_exists($name, $routeParamValues)) {
                    $payload[] = $routeParamValues[$name];
                    $matched = true;
                }
            }

            if (!$matched) {
                if ($parameter->isDefaultValueAvailable()) {
                    $payload[] = $parameter->getDefaultValue();
                } elseif ($parameter->allowsNull()) {
                    $payload[] = null;
                } else {
                    $payload[] = null;
                }
            }
        }

        return call_user_func_array([$this->object, $this->method], $payload);
    }

    /**
     * @return array<string>
     */
    private function flattenTypes(?ReflectionType $type): array
    {
        if ($type === null) {
            return [];
        }
        if ($type instanceof ReflectionUnionType) {
            $types = [];
            foreach ($type->getTypes() as $t) {
                if ($t instanceof ReflectionNamedType) {
                    $types[] = (string)$t->getName();
                }
            }
            return $types;
        }
        if ($type instanceof ReflectionNamedType) {
            return [(string)$type->getName()];
        }
        return [(string)$type];
    }

    private function argumentMatchesAnyType(mixed $argument, array $types): bool
    {
        foreach ($types as $expectedType) {
            if ($this->argumentMatchesType($argument, $expectedType)) {
                return true;
            }
        }
        return false;
    }

    private function argumentMatchesType(mixed $argument, string $expectedType): bool
    {
        $expectedType = ltrim($expectedType, '\\');

        if ($expectedType === 'mixed') {
            return true;
        }
        if ($expectedType === 'callable') {
            return is_callable($argument);
        }
        if ($expectedType === 'Closure') {
            return $argument instanceof Closure;
        }
        if ($expectedType === 'int') {
            return is_int($argument);
        }
        if ($expectedType === 'string') {
            return is_string($argument);
        }
        if ($expectedType === 'bool') {
            return is_bool($argument);
        }
        if ($expectedType === 'float') {
            return is_float($argument);
        }
        if ($expectedType === 'array') {
            return is_array($argument);
        }

        if (is_object($argument) && is_a($argument, $expectedType)) {
            return true;
        }

        return false;
    }

    private function typesAcceptScalarCapture(array $types): bool
    {
        // Only bind path captures into scalar-ish controller params.
        // Class/interface typed params should be injected from arguments (Request/Response/etc),
        // not from route captures.
        foreach ($types as $t) {
            $t = ltrim((string)$t, '\\');
            if ($t === 'mixed' || $t === 'string' || $t === 'int' || $t === 'float' || $t === 'bool') {
                return true;
            }
        }
        return false;
    }

    private function castRouteParamValue(mixed $value, array $types, ReflectionParameter $parameter): mixed
    {
        if ($value === null) {
            return null;
        }

        $types = array_values(array_filter(array_map(fn($t) => ltrim((string)$t, '\\'), $types), fn($t) => $t !== '' && $t !== 'null'));

        if (in_array('mixed', $types, true)) {
            return $value;
        }

        // Route params come from paths and are typically strings.
        $stringValue = is_string($value) ? $value : (string)$value;

        // Heuristic casting for union types: prefer numeric/bool when clearly representable.
        if (in_array('int', $types, true) && preg_match('/^-?\d+$/', $stringValue)) {
            return (int)$stringValue;
        }

        if (in_array('float', $types, true) && is_numeric($stringValue)) {
            return (float)$stringValue;
        }

        if (in_array('bool', $types, true)) {
            $v = strtolower(trim($stringValue));
            if (in_array($v, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($v, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        if (in_array('string', $types, true) || count($types) > 0) {
            return (string)$stringValue;
        }

        // Fallback: if typed but not a builtin, return as-is.
        return $value;
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
        return new Callback($method, $object, [new ValueObjectResolver()]);
    }

    /**
     * @param list<ParameterResolver> $resolvers
     */
    public static function createWithResolvers(string $method, object $object, array $resolvers): self
    {
        // Always include the default resolver last, so custom resolvers can override behavior.
        $resolvers[] = new ValueObjectResolver();
        return new Callback($method, $object, $resolvers);
    }
}
