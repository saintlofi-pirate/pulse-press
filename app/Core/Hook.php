<?php
declare(strict_types=1);

namespace PulsePress\Core;

defined('ABSPATH') || exit;
class Hook
{
    /** @var list<array{hook:string, callback:callable, priority:int, accepted_args:int}> */
    protected static array $actions = [];

    /** @var list<array{hook:string, callback:callable, priority:int, accepted_args:int}> */
    protected static array $filters = [];

    public static function action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        self::$actions[] = compact('hook', 'callback', 'priority') + ['accepted_args' => $acceptedArgs];
    }

    public static function filter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        self::$filters[] = compact('hook', 'callback', 'priority') + ['accepted_args' => $acceptedArgs];
    }

    public static function registerAll(): void
    {
        foreach (self::$actions as $action) {
            add_action($action['hook'], $action['callback'], $action['priority'], $action['accepted_args']);
        }
        foreach (self::$filters as $filter) {
            add_filter($filter['hook'], $filter['callback'], $filter['priority'], $filter['accepted_args']);
        }
    }
}
