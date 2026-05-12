<?php
declare(strict_types=1);

namespace {
    if (!function_exists('dbDelta')) {
        function dbDelta(string $sql): array
        {
            return [];
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
}
