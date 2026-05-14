<?php
declare(strict_types=1);

namespace PulsePress\Captures;

use PulsePress\Database\Schema;
use PulsePress\Http\RestException;
use wpdb;
use WP_REST_Request;

final class CaptureExporter
{
    public const DEFAULT_CHUNK_SIZE = 500;

    public function __construct(private wpdb $wpdb)
    {
    }

    /** @return array<string, array{label: string, render: callable}> */
    public static function defaultColumns(): array
    {
        return [
            'consent_at' => [
                'label'  => __('Consent timestamp', 'pulsepress'),
                'render' => static fn (array $row): string => (string) ($row['consent_at'] ?? ''),
            ],
            'email' => [
                'label'  => __('Email', 'pulsepress'),
                'render' => static fn (array $row): string => (string) ($row['email'] ?? ''),
            ],
            'post_id' => [
                'label'  => __('Post ID', 'pulsepress'),
                'render' => static fn (array $row): string => (string) ($row['post_id'] ?? ''),
            ],
            'post_title' => [
                'label'  => __('Post title', 'pulsepress'),
                'render' => static fn (array $row): string => (string) ($row['_post_title'] ?? ''),
            ],
            'reaction_type' => [
                'label'  => __('Reaction', 'pulsepress'),
                'render' => static fn (array $row): string => (string) ($row['reaction_type'] ?? ''),
            ],
            'consent_text_version' => [
                'label'  => __('Consent version', 'pulsepress'),
                'render' => static fn (array $row): string => (string) ($row['consent_text_version'] ?? ''),
            ],
            'source' => [
                'label'  => __('Source', 'pulsepress'),
                'render' => static fn (array $row): string => (string) ($row['source'] ?? ''),
            ],
            'created_at' => [
                'label'  => __('Captured at', 'pulsepress'),
                'render' => static fn (array $row): string => (string) ($row['created_at'] ?? ''),
            ],
        ];
    }

    /**
     * @param callable(string): void $emit
     * @param array{chunk_size?: int, request?: WP_REST_Request} $options
     */
    public function stream(callable $emit, array $options = []): int
    {
        $columns = $this->resolveColumns();
        if ($columns === []) {
            return 0;
        }

        $request = $options['request'] ?? null;
        if ($request instanceof WP_REST_Request) {
            do_action('pulsepress_before_export', $request);
        } else {
            do_action('pulsepress_before_export');
        }

        $emit($this->headerLine($columns));

        $chunk      = max(50, (int) ($options['chunk_size'] ?? self::DEFAULT_CHUNK_SIZE));
        $offset     = 0;
        $emitted    = 0;
        $titleCache = [];
        $table      = Schema::tableName($this->wpdb, Schema::TABLE_CAPTURES);

        while (true) {
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY id ASC LIMIT %d OFFSET %d",
                $chunk,
                $offset
            );
            $rows = $this->wpdb->get_results($sql, ARRAY_A);
            if (!is_array($rows) || $rows === []) {
                break;
            }

            foreach ($rows as $row) {
                $row['_post_title'] = $this->resolveTitle((int) ($row['post_id'] ?? 0), $titleCache);
                $emit($this->rowLine($columns, $row));
                $emitted++;
            }

            if (count($rows) < $chunk) {
                break;
            }
            $offset += $chunk;
        }

        return $emitted;
    }

    /** @return array<string, array{label: string, render: callable}> */
    private function resolveColumns(): array
    {
        $defaults = self::defaultColumns();
        $filtered = apply_filters('pulsepress_export_columns', $defaults);

        if (!is_array($filtered)) {
            return $defaults;
        }

        $clean = [];
        foreach ($filtered as $key => $entry) {
            if (!is_string($key) || $key === '' || !is_array($entry)) {
                continue;
            }
            $label  = $entry['label']  ?? null;
            $render = $entry['render'] ?? null;
            if (!is_string($label) || !is_callable($render)) {
                error_log(sprintf('[PulsePress] export column "%s" skipped: invalid label or render.', $key));
                continue;
            }
            $clean[$key] = ['label' => $label, 'render' => $render];
        }
        return $clean === [] ? $defaults : $clean;
    }

    /** @param array<string, array{label: string, render: callable}> $columns */
    private function headerLine(array $columns): string
    {
        return $this->packLine(array_map(static fn ($c) => $c['label'], $columns));
    }

    /** @param array<string, array{label: string, render: callable}> $columns */
    private function rowLine(array $columns, array $row): string
    {
        $cells = [];
        foreach ($columns as $column) {
            $value = ($column['render'])($row);
            $cells[] = is_string($value) ? $value : (string) $value;
        }
        return $this->packLine($cells);
    }

    /** @param string[] $cells */
    private function packLine(array $cells): string
    {
        return implode(',', array_map([$this, 'csvEscape'], $cells)) . "\r\n";
    }

    private function csvEscape(string $value): string
    {
        if (preg_match('/[",\r\n]/', $value) === 1) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }

    /** @param array<int, string> $cache */
    private function resolveTitle(int $postId, array &$cache): string
    {
        if ($postId <= 0) {
            return '';
        }
        if (array_key_exists($postId, $cache)) {
            return $cache[$postId];
        }
        $title = get_the_title($postId);
        $resolved = is_string($title) && $title !== '' ? $title : __('(deleted post)', 'pulsepress');
        $cache[$postId] = $resolved;
        return $resolved;
    }
}
