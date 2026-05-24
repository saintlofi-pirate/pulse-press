<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Core;


if (!defined('ABSPATH')) {
    exit;
}

class Config
{
    /** @var array<string, mixed> */
    protected array $config = [];

    public function load(string $file): void
    {
        $filePath = MOONFARMER_REACTIONS_LEAD_CAPTURE_DIR . 'app/Config/' . $file . '.php';
        if (!file_exists($filePath)) {
            return;
        }
        $config = require $filePath;
        if (is_array($config)) {
            $this->config[$file] = $config;
        }
    }

    public function get(string $key, $default = null)
    {
        $parts = explode('.', $key);
        $file  = array_shift($parts);

        if (!isset($this->config[$file])) {
            $this->load($file);
        }
        if (!isset($this->config[$file])) {
            return $default;
        }

        $value = $this->config[$file];
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }
}
