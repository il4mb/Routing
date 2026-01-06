<?php

namespace Il4mb\Routing\Binding\Resolvers;

use Il4mb\Routing\Binding\ParameterResolution;
use Il4mb\Routing\Binding\ParameterResolver;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

/**
 * Default resolver that can convert a string capture into a value object.
 *
 * It activates only when:
 * - the parameter is class-typed (or union containing a class)
 * - and a capture exists with the same name as the parameter
 *
 * Resolution strategy (first match wins):
 * - static ::fromString(string): self
 * - static ::from(string): self
 * - constructor with 1 parameter (string/int/float/bool) using best-effort casting
 */
final class ValueObjectResolver implements ParameterResolver
{
    public function resolve(ReflectionParameter $parameter, array $captures, array $runtimeArgs): ParameterResolution
    {
        $name = $parameter->getName();
        if (!array_key_exists($name, $captures)) {
            return ParameterResolution::notMatched('no capture');
        }

        $raw = $captures[$name];
        if ($raw === null) {
            return ParameterResolution::notMatched('null capture');
        }

        $classNames = $this->extractClassTypes($parameter);
        if (count($classNames) === 0) {
            return ParameterResolution::notMatched('not class-typed');
        }

        foreach ($classNames as $className) {
            if (!class_exists($className)) {
                continue;
            }

            $ref = new ReflectionClass($className);
            // static fromString(string)
            if ($ref->hasMethod('fromString')) {
                $m = $ref->getMethod('fromString');
                if ($m->isStatic() && $m->isPublic() && $m->getNumberOfParameters() === 1) {
                    return ParameterResolution::matched($className::fromString((string)$raw));
                }
            }

            // static from(string)
            if ($ref->hasMethod('from')) {
                $m = $ref->getMethod('from');
                if ($m->isStatic() && $m->isPublic() && $m->getNumberOfParameters() === 1) {
                    return ParameterResolution::matched($className::from((string)$raw));
                }
            }

            // __construct(1 arg)
            if (!$ref->isInstantiable()) {
                continue;
            }
            $ctor = $ref->getConstructor();
            if ($ctor !== null && $ctor->isPublic() && $ctor->getNumberOfParameters() === 1) {
                $p = $ctor->getParameters()[0];
                $v = (string)$raw;
                $t = $p->getType();

                if ($t instanceof ReflectionNamedType) {
                    $tn = $t->getName();
                    if ($tn === 'int' && preg_match('/^-?\\d+$/', $v)) {
                        return ParameterResolution::matched($ref->newInstance((int)$v));
                    }
                    if ($tn === 'float' && is_numeric($v)) {
                        return ParameterResolution::matched($ref->newInstance((float)$v));
                    }
                    if ($tn === 'bool') {
                        $vv = strtolower(trim($v));
                        if (in_array($vv, ['1', 'true', 'yes', 'on'], true)) {
                            return ParameterResolution::matched($ref->newInstance(true));
                        }
                        if (in_array($vv, ['0', 'false', 'no', 'off'], true)) {
                            return ParameterResolution::matched($ref->newInstance(false));
                        }
                    }
                    // string or unknown: pass as string
                    return ParameterResolution::matched($ref->newInstance((string)$raw));
                }

                // Unknown type: pass as string
                return ParameterResolution::matched($ref->newInstance((string)$raw));
            }
        }

        return ParameterResolution::notMatched('no compatible constructor/from*');
    }

    /**
     * @return list<class-string>
     */
    private function extractClassTypes(ReflectionParameter $parameter): array
    {
        $type = $parameter->getType();
        if ($type === null) {
            return [];
        }

        $names = [];
        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $t) {
                if ($t instanceof ReflectionNamedType && !$t->isBuiltin()) {
                    $names[] = $t->getName();
                }
            }
            return $names;
        }

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return [$type->getName()];
        }

        return [];
    }
}
