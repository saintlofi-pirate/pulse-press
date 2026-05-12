<?php
declare(strict_types=1);

namespace PulsePress\Core;

class Application extends Container
{
    protected static ?Application $instance = null;

    /** @var list<ServiceProvider> */
    protected array $providers = [];

    protected string $pluginFile;

    final private function __construct(string $pluginFile)
    {
        $this->pluginFile = $pluginFile;

        $this->instance('app', $this);
        $this->instance(self::class, $this);
        $this->instance(Container::class, $this);
    }

    public static function boot(string $pluginFile): self
    {
        if (self::$instance === null) {
            self::$instance = new self($pluginFile);
            self::$instance->loadProviders();
            self::$instance->register();
            self::$instance->bootProviders();
        }

        return self::$instance;
    }

    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    public function pluginFile(): string
    {
        return $this->pluginFile;
    }

    public function pluginDir(): string
    {
        return defined('PULSEPRESS_DIR') ? PULSEPRESS_DIR : dirname($this->pluginFile) . '/';
    }

    protected function loadProviders(): void
    {
        $bootstrap = require $this->pluginDir() . 'app/bootstrap.php';
        $providers = $bootstrap['providers'] ?? [];

        foreach ($providers as $providerClass) {
            $this->providers[] = new $providerClass($this);
        }
    }

    protected function register(): void
    {
        foreach ($this->providers as $provider) {
            $provider->register();
        }
    }

    protected function bootProviders(): void
    {
        foreach ($this->providers as $provider) {
            $provider->boot();
        }
    }
}
