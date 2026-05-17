<?php
declare(strict_types=1);

namespace PulsePress\Core;


if (!defined('ABSPATH')) {
    exit;
}

class Router
{
    /** @var list<array{method:string, uri:string, action:mixed, permissions:mixed}> */
    protected static array $routes = [];

    protected static string $namespace = 'pulsepress/v1';

    public static function get(string $uri, $action, $permissions = 'manage_options'): void
    {
        self::add('GET', $uri, $action, $permissions);
    }

    public static function post(string $uri, $action, $permissions = 'manage_options'): void
    {
        self::add('POST', $uri, $action, $permissions);
    }

    public static function put(string $uri, $action, $permissions = 'manage_options'): void
    {
        self::add('PUT', $uri, $action, $permissions);
    }

    public static function delete(string $uri, $action, $permissions = 'manage_options'): void
    {
        self::add('DELETE', $uri, $action, $permissions);
    }

    public static function namespace(string $namespace): void
    {
        self::$namespace = $namespace;
    }

    protected static function add(string $method, string $uri, $action, $permissions): void
    {
        self::$routes[] = compact('method', 'uri', 'action', 'permissions');
    }

    public static function registerAll(): void
    {
        add_action('rest_api_init', static function (): void {
            foreach (self::$routes as $route) {
                register_rest_route(self::$namespace, $route['uri'], [
                    'methods'             => $route['method'],
                    'callback'            => self::resolveCallback($route['action']),
                    'permission_callback' => self::resolvePermission($route['permissions']),
                ]);
            }
        });
    }

    protected static function resolveCallback($action): callable
    {
        if (is_array($action) && count($action) === 2) {
            [$class, $method] = $action;
            return static fn ($request) => (new $class())->{$method}($request);
        }

        if (is_string($action) && strpos($action, '@') !== false) {
            [$class, $method] = explode('@', $action, 2);
            return static fn ($request) => (new $class())->{$method}($request);
        }

        return $action;
    }

    protected static function resolvePermission($permissions): callable
    {
        if (is_callable($permissions)) {
            return $permissions;
        }
        $caps = (array) $permissions;
        return static function () use ($caps): bool {
            foreach ($caps as $cap) {
                if (!current_user_can($cap)) {
                    return false;
                }
            }
            return true;
        };
    }
}
