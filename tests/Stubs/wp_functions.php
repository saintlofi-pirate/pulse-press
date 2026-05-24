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

namespace Moonfarmer\ReactionsLeadCapture\Database {

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

namespace Moonfarmer\ReactionsLeadCapture\Reactions {

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

namespace Moonfarmer\ReactionsLeadCapture\Http\Controllers {

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

namespace Moonfarmer\ReactionsLeadCapture\Providers {

    if (!function_exists(__NAMESPACE__ . '\add_action')) {
        function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
        {
            \Tests\Stubs\FilterRegistry::addAction($hook, $callback, $priority, $acceptedArgs);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\add_filter')) {
        function add_filter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
        {
            \Tests\Stubs\FilterRegistry::addFilter($hook, $callback);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\apply_filters')) {
        function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
        {
            return \Tests\Stubs\FilterRegistry::apply($hook, $value, $args);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\do_action')) {
        function do_action(string $hook, mixed ...$args): void
        {
            \Tests\Stubs\FilterRegistry::doAction($hook, $args);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\wp_timezone')) {
        function wp_timezone(): \DateTimeZone
        {
            return new \DateTimeZone('UTC');
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

    if (!function_exists(__NAMESPACE__ . '\is_admin')) {
        function is_admin(): bool
        {
            return \Tests\Stubs\WpEnv::isAdmin();
        }
    }

    if (!function_exists(__NAMESPACE__ . '\is_singular')) {
        function is_singular(string $postType = ''): bool
        {
            return \Tests\Stubs\WpEnv::isSingular($postType);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\get_the_ID')) {
        function get_the_ID(): int|false
        {
            return \Tests\Stubs\WpEnv::currentPostId();
        }
    }

    if (!function_exists(__NAMESPACE__ . '\get_post_type')) {
        function get_post_type(): string|false
        {
            return \Tests\Stubs\WpEnv::currentPostType();
        }
    }

    if (!function_exists(__NAMESPACE__ . '\get_post')) {
        function get_post(int|\WP_Post|null $post = null): ?object
        {
            return (object) ['ID' => \Tests\Stubs\WpEnv::currentPostId() ?: 0, 'post_content' => \Tests\Stubs\WpEnv::currentPostContent()];
        }
    }

    if (!function_exists(__NAMESPACE__ . '\has_block')) {
        function has_block(string $blockName, mixed $post = null): bool
        {
            $content = is_object($post) && isset($post->post_content) ? (string) $post->post_content : \Tests\Stubs\WpEnv::currentPostContent();
            return str_contains($content, '<!-- wp:' . $blockName);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\has_shortcode')) {
        function has_shortcode(string $content, string $tag): bool
        {
            return str_contains($content, '[' . $tag) || str_contains($content, '[/' . $tag . ']');
        }
    }

    if (!function_exists(__NAMESPACE__ . '\rest_url')) {
        function rest_url(string $path = ''): string
        {
            return 'https://example.test/wp-json/' . ltrim($path, '/');
        }
    }

    if (!function_exists(__NAMESPACE__ . '\esc_url_raw')) {
        function esc_url_raw(string $url): string
        {
            return $url;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\wp_create_nonce')) {
        function wp_create_nonce(string $action): string
        {
            return 'nonce-' . $action;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\wp_register_script')) {
        function wp_register_script(string $handle, string $src, array $deps = [], string|bool $ver = false, bool $inFooter = false): bool
        {
            \Tests\Stubs\AssetSpy::record('register_script', compact('handle', 'src', 'deps', 'ver', 'inFooter'));
            return true;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\wp_enqueue_script')) {
        function wp_enqueue_script(string $handle): void
        {
            \Tests\Stubs\AssetSpy::record('enqueue_script', ['handle' => $handle]);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\wp_script_add_data')) {
        function wp_script_add_data(string $handle, string $key, mixed $value): bool
        {
            \Tests\Stubs\AssetSpy::record('script_add_data', compact('handle', 'key', 'value'));
            return true;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\wp_register_style')) {
        function wp_register_style(string $handle, string $src, array $deps = [], string|bool $ver = false): bool
        {
            \Tests\Stubs\AssetSpy::record('register_style', compact('handle', 'src', 'deps', 'ver'));
            return true;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\wp_enqueue_style')) {
        function wp_enqueue_style(string $handle): void
        {
            \Tests\Stubs\AssetSpy::record('enqueue_style', ['handle' => $handle]);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\wp_localize_script')) {
        function wp_localize_script(string $handle, string $objectName, array $data): bool
        {
            \Tests\Stubs\AssetSpy::record('localize', compact('handle', 'objectName', 'data'));
            return true;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\wp_add_inline_script')) {
        function wp_add_inline_script(string $handle, string $data, string $position = 'after'): bool
        {
            \Tests\Stubs\AssetSpy::record('inline_script', compact('handle', 'data', 'position'));
            return true;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\wp_json_encode')) {
        function wp_json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false
        {
            return json_encode($value, $flags, $depth);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\__')) {
        function __(string $text, string $domain = 'default'): string
        {
            return $text;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\is_email')) {
        function is_email(string $email): string|false
        {
            return filter_var($email, FILTER_VALIDATE_EMAIL) === false ? false : $email;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\apply_filters')) {
        function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
        {
            return \Tests\Stubs\FilterRegistry::apply($hook, $value, $args);
        }
    }
}

namespace Moonfarmer\ReactionsLeadCapture\View {

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
}

namespace Moonfarmer\ReactionsLeadCapture\Captures {

    if (!function_exists(__NAMESPACE__ . '\apply_filters')) {
        function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
        {
            return \Tests\Stubs\FilterRegistry::apply($hook, $value, $args);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\do_action')) {
        function do_action(string $hook, mixed ...$args): void
        {
            \Tests\Stubs\FilterRegistry::doAction($hook, $args);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\get_the_title')) {
        function get_the_title(int $postId): string|false
        {
            return \Tests\Stubs\PostRegistry::title($postId);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\__')) {
        function __(string $text, string $domain = 'default'): string
        {
            return $text;
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

namespace Moonfarmer\ReactionsLeadCapture\Visibility {

    if (!function_exists(__NAMESPACE__ . '\apply_filters')) {
        function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
        {
            return \Tests\Stubs\FilterRegistry::apply($hook, $value, $args);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\get_post_meta')) {
        function get_post_meta(int $postId, string $key, bool $single = false): mixed
        {
            return \Tests\Stubs\PostMetaStore::get($postId, $key);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\get_post_type')) {
        function get_post_type(int $postId = 0): string|false
        {
            $type = \Tests\Stubs\PostRegistry::postType($postId);
            return $type !== null ? $type : false;
        }
    }
}

namespace Moonfarmer\ReactionsLeadCapture\Analytics {

    if (!defined(__NAMESPACE__ . '\HOUR_IN_SECONDS')) {
        define(__NAMESPACE__ . '\HOUR_IN_SECONDS', 3600);
    }

    if (!function_exists(__NAMESPACE__ . '\do_action')) {
        function do_action(string $hook, mixed ...$args): void
        {
            \Tests\Stubs\FilterRegistry::doAction($hook, $args);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\error_log')) {
        function error_log(string $message): bool
        {
            \Tests\Stubs\ErrorLogSpy::record($message);
            return true;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\apply_filters')) {
        function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
        {
            return \Tests\Stubs\FilterRegistry::apply($hook, $value, $args);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\get_the_title')) {
        function get_the_title(int $postId): string|false
        {
            return \Tests\Stubs\PostRegistry::title($postId);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\__')) {
        function __(string $text, string $domain = 'default'): string
        {
            return $text;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\wp_schedule_event')) {
        function wp_schedule_event(int $timestamp, string $recurrence, string $hook): bool
        {
            \Tests\Stubs\CronSpy::schedule($hook, $timestamp, $recurrence);
            return true;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\wp_unschedule_event')) {
        function wp_unschedule_event(int $timestamp, string $hook): bool
        {
            \Tests\Stubs\CronSpy::unschedule($hook);
            return true;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\wp_next_scheduled')) {
        function wp_next_scheduled(string $hook): int|false
        {
            return \Tests\Stubs\CronSpy::next($hook);
        }
    }
}

namespace Moonfarmer\ReactionsLeadCapture\Blocks {

    if (!function_exists(__NAMESPACE__ . '\apply_filters')) {
        function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
        {
            return \Tests\Stubs\FilterRegistry::apply($hook, $value, $args);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\esc_attr')) {
        function esc_attr(string $value): string
        {
            return htmlspecialchars($value, ENT_QUOTES);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\get_the_ID')) {
        function get_the_ID(): int|false
        {
            return \Tests\Stubs\WpEnv::currentPostId();
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

    if (!function_exists(__NAMESPACE__ . '\shortcode_atts')) {
        function shortcode_atts(array $defaults, array $atts, string $tag = ''): array
        {
            $out = $defaults;
            foreach ($atts as $key => $value) {
                if (array_key_exists($key, $defaults)) {
                    $out[$key] = $value;
                }
            }
            return $out;
        }
    }
}

namespace Moonfarmer\ReactionsLeadCapture\Settings {

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

    if (!function_exists(__NAMESPACE__ . '\apply_filters')) {
        function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
        {
            return \Tests\Stubs\FilterRegistry::apply($hook, $value, $args);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\do_action')) {
        function do_action(string $hook, mixed ...$args): void
        {
            \Tests\Stubs\FilterRegistry::doAction($hook, $args);
        }
    }
}

namespace Moonfarmer\ReactionsLeadCapture\Reactions {

    if (!function_exists(__NAMESPACE__ . '\sanitize_email')) {
        function sanitize_email(string $value): string
        {
            return strtolower(trim($value));
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
        /** @var array<int, array{status: string, public: bool, type: string}> */
        private static array $posts = [];

        public static function reset(): void
        {
            self::$posts = [];
        }

        public static function register(int $postId, string $status = 'publish', bool $public = true, string $type = 'post'): void
        {
            self::$posts[$postId] = ['status' => $status, 'public' => $public, 'type' => $type];
        }

        public static function setPostType(int $postId, string $type): void
        {
            if (isset(self::$posts[$postId])) {
                self::$posts[$postId]['type'] = $type;
            }
        }

        public static function status(int $postId): string|false
        {
            return self::$posts[$postId]['status'] ?? false;
        }

        public static function isPublic(int $postId): bool
        {
            return self::$posts[$postId]['public'] ?? false;
        }

        public static function postType(int $postId): ?string
        {
            return self::$posts[$postId]['type'] ?? null;
        }

        public static function title(int $postId): string|false
        {
            return self::$posts[$postId]['title'] ?? false;
        }
    }

    final class PostMetaStore
    {
        /** @var array<string, mixed> */
        private static array $meta = [];

        public static function reset(): void
        {
            self::$meta = [];
        }

        public static function key(int $postId, string $key): string
        {
            return $postId . ':' . $key;
        }

        public static function set(int $postId, string $key, mixed $value): void
        {
            self::$meta[self::key($postId, $key)] = $value;
        }

        public static function get(int $postId, string $key): mixed
        {
            return self::$meta[self::key($postId, $key)] ?? '';
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

    final class WpEnv
    {
        private static bool $isAdmin = false;
        private static ?string $singularType = null;
        private static int $postId = 0;
        private static string $postType = 'post';
        private static string $postContent = '';

        public static function reset(): void
        {
            self::$isAdmin      = false;
            self::$singularType = null;
            self::$postId       = 0;
            self::$postType     = 'post';
            self::$postContent  = '';
        }

        public static function setAdmin(bool $value): void
        {
            self::$isAdmin = $value;
        }

        public static function setSingular(?string $postType, int $postId = 0): void
        {
            self::$singularType = $postType;
            self::$postId       = $postId;
            if ($postType !== null) {
                self::$postType = $postType;
            }
        }

        public static function setPostContent(string $content): void
        {
            self::$postContent = $content;
        }

        public static function isAdmin(): bool
        {
            return self::$isAdmin;
        }

        public static function isSingular(string $type = ''): bool
        {
            if (self::$singularType === null) {
                return false;
            }
            return $type === '' || $type === self::$singularType;
        }

        public static function currentPostId(): int|false
        {
            return self::$postId > 0 ? self::$postId : false;
        }

        public static function currentPostType(): string|false
        {
            return self::$singularType === null ? false : self::$postType;
        }

        public static function currentPostContent(): string
        {
            return self::$postContent;
        }
    }

    final class CronSpy
    {
        /** @var array<string, array{timestamp: int, recurrence: string}> */
        private static array $events = [];

        public static function reset(): void
        {
            self::$events = [];
        }

        public static function schedule(string $hook, int $timestamp, string $recurrence): void
        {
            self::$events[$hook] = ['timestamp' => $timestamp, 'recurrence' => $recurrence];
        }

        public static function unschedule(string $hook): void
        {
            unset(self::$events[$hook]);
        }

        public static function next(string $hook): int|false
        {
            return self::$events[$hook]['timestamp'] ?? false;
        }

        public static function all(): array
        {
            return self::$events;
        }
    }

    final class AssetSpy
    {
        /** @var list<array{action: string, args: array}> */
        private static array $calls = [];

        public static function reset(): void
        {
            self::$calls = [];
        }

        public static function record(string $action, array $args): void
        {
            self::$calls[] = ['action' => $action, 'args' => $args];
        }

        public static function calls(): array
        {
            return self::$calls;
        }

        public static function only(string $action): array
        {
            return array_values(array_filter(self::$calls, fn ($c) => $c['action'] === $action));
        }
    }
}
