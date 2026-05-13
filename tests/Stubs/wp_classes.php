<?php
declare(strict_types=1);

if (!class_exists('wpdb', false)) {
    class wpdb
    {
        public string $prefix = '';
        public string $options = '';
        public int $insert_id = 0;

        public function get_charset_collate(): string
        {
            return '';
        }

        public function prepare(string $sql, mixed ...$args): string
        {
            return $sql;
        }

        public function get_var(string $sql): ?string
        {
            return null;
        }

        public function query(string $sql): int
        {
            return 0;
        }

        public function get_col(string $sql): array
        {
            return [];
        }

        public function get_results(string $sql, string $output = 'OBJECT'): array
        {
            return [];
        }

        public function insert(string $table, array $data, array|string $format = ''): false|int
        {
            return false;
        }

        public function esc_like(string $text): string
        {
            return $text;
        }
    }
}
