<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Core;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use RuntimeException;


if (!defined('ABSPATH')) {
    exit;
}

class Container
{
    /** @var array<string, array{concrete: mixed, shared: bool}> */
    protected array $bindings = [];

    /** @var array<string, mixed> */
    protected array $instances = [];

    public function bind(string $abstract, $concrete = null, bool $shared = false): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'shared'   => $shared,
        ];
    }

    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function instance(string $abstract, $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    public function get(string $id)
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $concrete = $this->bindings[$id]['concrete'] ?? $id;
        $object   = $this->build($concrete);

        if (($this->bindings[$id]['shared'] ?? false) === true) {
            $this->instances[$id] = $object;
        }

        return $object;
    }

    public function make(string $abstract)
    {
        return $this->get($abstract);
    }

    protected function build($concrete)
    {
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }

        if (!is_string($concrete) || !class_exists($concrete)) {
            throw new RuntimeException('Container target is not buildable.');
        }

        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            unset($e);
            throw new RuntimeException('Container target cannot be reflected.');
        }

        if (!$reflector->isInstantiable()) {
            throw new RuntimeException('Container target is not instantiable.');
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $concrete();
        }

        $dependencies = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->get($type->getName());
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            throw new RuntimeException('Container target dependency cannot be resolved.');
        }

        return $reflector->newInstanceArgs($dependencies);
    }
}
