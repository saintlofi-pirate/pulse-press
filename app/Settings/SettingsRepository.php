<?php
declare(strict_types=1);

namespace PulsePress\Settings;

final class SettingsRepository
{
    private ?array $cached = null;

    /** @return array<string, mixed> */
    public function get(): array
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        $stored   = get_option(Settings::OPTION_NAME, null);
        $base     = Settings::DEFAULTS;

        if (is_array($stored)) {
            foreach ($stored as $key => $value) {
                if ($key === '_version') {
                    continue;
                }
                if (array_key_exists($key, $base)) {
                    $base[$key] = $value;
                }
            }
        } else {
            $base['delete_on_uninstall'] = get_option('pulsepress_delete_on_uninstall', '0') === '1';
            $base['retention_days']      = (int) get_option('pulsepress_retention_days', '0');
        }

        $filtered = apply_filters('pulsepress_settings', $base);
        $this->cached = is_array($filtered) ? $filtered : $base;
        return $this->cached;
    }

    /**
     * @param array<string, mixed> $partial
     * @return array<string, mixed>
     */
    public function save(array $partial): array
    {
        $previous  = $this->get();
        $clean     = Settings::sanitise($partial);
        $merged    = array_merge($previous, $clean);
        $persisted = $merged + ['_version' => Settings::SCHEMA_VERSION];

        update_option(Settings::OPTION_NAME, $persisted, true);

        $this->cached = $merged;
        do_action('pulsepress_settings_saved', $merged, $previous);

        return $merged;
    }

    public function resetMemo(): void
    {
        $this->cached = null;
    }
}
