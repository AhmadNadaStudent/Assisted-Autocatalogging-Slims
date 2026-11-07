<?php
namespace AssistedAutocataloguing\Core;

/**
 * Main Application Bootstrap & Service Container
 */
class Application
{
    private static ?Application $instance = null;
    private array $config = [];
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
        $this->loadConfiguration();
    }

    public static function getInstance(?string $basePath = null): Application
    {
        if (self::$instance === null) {
            $path = $basePath ?? dirname(__DIR__, 2);
            self::$instance = new self($path);
        }
        return self::$instance;
    }

    /**
     * Register PSR-4 autoloader for AssistedAutocataloguing namespace
     */
    public static function registerAutoloader(string $baseDir): void
    {
        spl_autoload_register(function ($class) use ($baseDir) {
            $prefix = 'AssistedAutocataloguing\\';
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }
            $relativeClass = substr($class, $len);
            $file = $baseDir . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        });
    }

    private function loadConfiguration(): void
    {
        $defaultConfig = [];
        $defaultConfigFile = $this->basePath . '/config/config.default.php';
        if (file_exists($defaultConfigFile)) {
            $defaultConfig = require $defaultConfigFile;
        }

        $userConfig = [];
        $userConfigFile = $this->basePath . '/config/config.php';
        if (file_exists($userConfigFile)) {
            $userConfig = require $userConfigFile;
        }

        $this->config = array_replace_recursive($defaultConfig, $userConfig);
    }

    public function getConfig(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        $segments = explode('.', $key);
        $value = $this->config;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function getStoragePath(string $subPath = ''): string
    {
        $path = $this->basePath . '/storage';
        if (!empty($subPath)) {
            $path .= '/' . ltrim($subPath, '/\\');
        }
        return $path;
    }
}
