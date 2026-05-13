<?php
declare(strict_types=1);

namespace Tests\Stubs;

require_once __DIR__ . '/wp_classes.php';

class WpdbStub extends \wpdb
{
    /** @var list<string> */
    public array $queries = [];

    /** @var array<string, string|null> */
    public array $existingTables = [];

    /** @var array<string, array> */
    public array $resultsByQuery = [];

    public int $rows_affected = 0;

    public string $last_query = '';

    public string $last_error = '';

    public function __construct()
    {
        $this->prefix  = 'wp_';
        $this->options = 'wp_options';
    }

    public function get_charset_collate(): string
    {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
    }

    public function prepare(string $sql, mixed ...$args): string
    {
        $args = array_map(static fn ($a) => is_int($a) || is_float($a) ? (string) $a : "'" . str_replace("'", "''", (string) $a) . "'", $args);
        return vsprintf(str_replace(['%s', '%d', '%f'], '%s', $sql), $args);
    }

    public function get_var(string $sql): ?string
    {
        $this->queries[] = $sql;
        foreach (array_keys($this->existingTables) as $table) {
            if (str_contains($sql, "'{$table}'") || str_contains($sql, $table)) {
                return $table;
            }
        }
        return null;
    }

    public function query(string $sql): int
    {
        $this->queries[]  = $sql;
        $this->last_query = $sql;
        return $this->rows_affected;
    }

    public function get_col(string $sql): array
    {
        $this->queries[] = $sql;
        return [];
    }

    public function get_results(string $sql, string $output = 'OBJECT'): array
    {
        $this->queries[]  = $sql;
        $this->last_query = $sql;
        foreach ($this->resultsByQuery as $fragment => $rows) {
            if (str_contains($sql, $fragment)) {
                return $rows;
            }
        }
        return [];
    }

    public function esc_like(string $text): string
    {
        return addcslashes($text, '_%\\');
    }
}
