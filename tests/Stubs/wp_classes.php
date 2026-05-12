<?php
declare(strict_types=1);

if (!class_exists('wpdb', false)) {
    class wpdb
    {
        public string $prefix = '';
        public string $options = '';

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

        public function esc_like(string $text): string
        {
            return $text;
        }
    }
}
