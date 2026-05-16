<?php
declare(strict_types=1);

namespace PulsePress\Core;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use RuntimeException;

class Container
{
    /** @var array<string, array{concrete: mixed, shared: bool}> */
    protected array $bindings = [];

    /** @var array<string, mixed> */
    protected array $instances = [];

    public function bind(string $abstract, mixed $concrete = null, bool $shared = false): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'shared'   => $shared,
        ];
    }

    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    public function get(string $id): mixed
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

    public function make(string $abstract): mixed
    {
        return $this->get($abstract);
    }

    protected function build(mixed $concrete): mixed
    {
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }

        // phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not rendered output.
        if (!is_string($concrete) || !class_exists($concrete)) {
            throw new RuntimeException(sprintf('Target [%s] is not buildable.', $this->exceptionFragment(is_string($concrete) ? $concrete : gettype($concrete))));
        }

        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            throw new RuntimeException(sprintf('Cannot reflect [%s]: %s', $this->exceptionFragment($concrete), $this->exceptionFragment($e->getMessage())), 0, $e);
        }

        if (!$reflector->isInstantiable()) {
            throw new RuntimeException(sprintf('Class [%s] is not instantiable.', $this->exceptionFragment($concrete)));
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

            throw new RuntimeException(sprintf(
                'Cannot resolve parameter [$%s] of [%s::__construct()].',
                $this->exceptionFragment($parameter->getName()),
                $this->exceptionFragment($concrete)
            ));
        }
        // phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped

        return $reflector->newInstanceArgs($dependencies);
    }

    private function exceptionFragment(string $value): string
    {
        return function_exists('esc_html') ? esc_html($value) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
