<?php
declare(strict_types=1);

namespace {
    if (!defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }

    if (!function_exists('dbDelta')) {
        function dbDelta(string $sql): array
        {
            return [];
        }
    }

    if (!function_exists('apply_filters')) {
        function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
        {
            return \Tests\Stubs\FilterRegistry::apply($hook, $value, $args);
        }
    }

    if (!function_exists('do_action')) {
        function do_action(string $hook, mixed ...$args): void
        {
            \Tests\Stubs\FilterRegistry::doAction($hook, $args);
        }
    }
}

namespace PulsePress\Database {

    if (!function_exists(__NAMESPACE__ . '\get_option')) {
        function get_option(string $key, mixed $default = false): mixed
        {
            return \Tests\Stubs\OptionStore::get($key, $default);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\update_option')) {
        function update_option(string $key, mixed $value, bool $autoload = true): bool
        {
            \Tests\Stubs\OptionStore::set($key, $value);
            return true;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\dbDelta')) {
        function dbDelta(string $sql): array
        {
            \Tests\Stubs\DbDeltaSpy::record($sql);
            return [];
        }
    }

    if (!function_exists(__NAMESPACE__ . '\error_log')) {
        function error_log(string $message): bool
        {
            \Tests\Stubs\ErrorLogSpy::record($message);
            return true;
        }
    }
}

namespace PulsePress\Reactions {

    if (!function_exists(__NAMESPACE__ . '\wp_salt')) {
        function wp_salt(string $scheme = 'auth'): string
        {
            return \Tests\Stubs\WpSaltStub::for($scheme);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\apply_filters')) {
        function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
        {
            return \Tests\Stubs\FilterRegistry::apply($hook, $value, $args);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\get_transient')) {
        function get_transient(string $key): mixed
        {
            return \Tests\Stubs\TransientStore::get($key);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\set_transient')) {
        function set_transient(string $key, mixed $value, int $ttl = 0): bool
        {
            \Tests\Stubs\TransientStore::set($key, $value, $ttl);
            return true;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\delete_transient')) {
        function delete_transient(string $key): bool
        {
            \Tests\Stubs\TransientStore::delete($key);
            return true;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\sanitize_text_field')) {
        function sanitize_text_field(string $value): string
        {
            return trim(strip_tags($value));
        }
    }

    if (!function_exists(__NAMESPACE__ . '\wp_unslash')) {
        function wp_unslash(mixed $value): mixed
        {
            if (is_string($value)) {
                return stripslashes($value);
            }
            return $value;
        }
    }
}

namespace PulsePress\Http\Controllers {

    if (!function_exists(__NAMESPACE__ . '\do_action')) {
        function do_action(string $hook, mixed ...$args): void
        {
            \Tests\Stubs\FilterRegistry::doAction($hook, $args);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\get_post_status')) {
        function get_post_status(int $postId): string|false
        {
            return \Tests\Stubs\PostRegistry::status($postId);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\is_post_publicly_viewable')) {
        function is_post_publicly_viewable(int $postId): bool
        {
            return \Tests\Stubs\PostRegistry::isPublic($postId);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\__')) {
        function __(string $text, string $domain = 'default'): string
        {
            return $text;
        }
    }
}

namespace PulsePress\Providers {

    if (!function_exists(__NAMESPACE__ . '\add_action')) {
        function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
        {
            \Tests\Stubs\FilterRegistry::addAction($hook, $callback, $priority, $acceptedArgs);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\register_rest_route')) {
        function register_rest_route(string $namespace, string $route, array $args): void
        {
            \Tests\Stubs\RestRouteSpy::record($namespace, $route, $args);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\wp_verify_nonce')) {
        function wp_verify_nonce(string $nonce, string $action): int|false
        {
            return $nonce === 'valid-' . $action ? 1 : false;
        }
    }
}

namespace Tests\Stubs {

    final class OptionStore
    {
        /** @var array<string, mixed> */
        private static array $store = [];

        public static function reset(): void
        {
            self::$store = [];
        }

        public static function get(string $key, mixed $default = false): mixed
        {
            return self::$store[$key] ?? $default;
        }

        public static function set(string $key, mixed $value): void
        {
            self::$store[$key] = $value;
        }
    }

    final class DbDeltaSpy
    {
        /** @var list<string> */
        private static array $calls = [];

        public static function reset(): void
        {
            self::$calls = [];
        }

        public static function record(string $sql): void
        {
            self::$calls[] = $sql;
        }

        public static function calls(): array
        {
            return self::$calls;
        }
    }

    final class ErrorLogSpy
    {
        /** @var list<string> */
        private static array $messages = [];

        public static function reset(): void
        {
            self::$messages = [];
        }

        public static function record(string $message): void
        {
            self::$messages[] = $message;
        }

        public static function messages(): array
        {
            return self::$messages;
        }
    }

    final class WpSaltStub
    {
        /** @var array<string, string> */
        private static array $values = ['auth' => 'test-auth-salt'];

        public static function reset(): void
        {
            self::$values = ['auth' => 'test-auth-salt'];
        }

        public static function set(string $scheme, string $value): void
        {
            self::$values[$scheme] = $value;
        }

        public static function for(string $scheme): string
        {
            return self::$values[$scheme] ?? 'unknown-' . $scheme;
        }
    }

    final class FilterRegistry
    {
        /** @var array<string, list<callable>> */
        private static array $filters = [];

        /** @var array<string, list<array{callback: callable, priority: int}>> */
        private static array $actions = [];

        /** @var array<string, list<array>> */
        private static array $actionLog = [];

        public static function reset(): void
        {
            self::$filters   = [];
            self::$actions   = [];
            self::$actionLog = [];
        }

        public static function addFilter(string $hook, callable $callback): void
        {
            self::$filters[$hook][] = $callback;
        }

        public static function addAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
        {
            self::$actions[$hook][] = ['callback' => $callback, 'priority' => $priority];
        }

        public static function apply(string $hook, mixed $value, array $args = []): mixed
        {
            foreach (self::$filters[$hook] ?? [] as $callback) {
                $value = $callback($value, ...$args);
            }
            return $value;
        }

        public static function doAction(string $hook, array $args = []): void
        {
            self::$actionLog[$hook][] = $args;
            foreach (self::$actions[$hook] ?? [] as $entry) {
                ($entry['callback'])(...$args);
            }
        }

        public static function actionCalls(string $hook): array
        {
            return self::$actionLog[$hook] ?? [];
        }
    }

    final class TransientStore
    {
        /** @var array<string, mixed> */
        private static array $values = [];

        /** @var array<string, int> */
        private static array $ttls = [];

        public static function reset(): void
        {
            self::$values = [];
            self::$ttls   = [];
        }

        public static function get(string $key): mixed
        {
            return self::$values[$key] ?? false;
        }

        public static function set(string $key, mixed $value, int $ttl = 0): void
        {
            self::$values[$key] = $value;
            self::$ttls[$key]   = $ttl;
        }

        public static function delete(string $key): void
        {
            unset(self::$values[$key], self::$ttls[$key]);
        }

        public static function ttl(string $key): ?int
        {
            return self::$ttls[$key] ?? null;
        }

        public static function exists(string $key): bool
        {
            return array_key_exists($key, self::$values);
        }
    }

    final class PostRegistry
    {
        /** @var array<int, array{status: string, public: bool}> */
        private static array $posts = [];

        public static function reset(): void
        {
            self::$posts = [];
        }

        public static function register(int $postId, string $status = 'publish', bool $public = true): void
        {
            self::$posts[$postId] = ['status' => $status, 'public' => $public];
        }

        public static function status(int $postId): string|false
        {
            return self::$posts[$postId]['status'] ?? false;
        }

        public static function isPublic(int $postId): bool
        {
            return self::$posts[$postId]['public'] ?? false;
        }
    }

    final class RestRouteSpy
    {
        /** @var list<array{namespace: string, route: string, args: array}> */
        private static array $registrations = [];

        public static function reset(): void
        {
            self::$registrations = [];
        }

        public static function record(string $namespace, string $route, array $args): void
        {
            self::$registrations[] = ['namespace' => $namespace, 'route' => $route, 'args' => $args];
        }

        public static function registrations(): array
        {
            return self::$registrations;
        }
    }
}
