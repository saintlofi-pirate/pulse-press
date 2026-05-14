<?php
declare(strict_types=1);

if (!class_exists('WP_Error', false)) {
    class WP_Error
    {
        private array $errors = [];
        private array $errorData = [];

        public function __construct(string $code = '', string $message = '', mixed $data = '')
        {
            if ($code !== '') {
                $this->errors[$code] = [$message];
                if ($data !== '') {
                    $this->errorData[$code] = $data;
                }
            }
        }

        public function get_error_code(): string
        {
            $codes = array_keys($this->errors);
            return $codes[0] ?? '';
        }

        public function get_error_message(string $code = ''): string
        {
            if ($code === '') {
                $code = $this->get_error_code();
            }
            return $this->errors[$code][0] ?? '';
        }

        public function get_error_data(string $code = ''): mixed
        {
            if ($code === '') {
                $code = $this->get_error_code();
            }
            return $this->errorData[$code] ?? null;
        }
    }
}

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
