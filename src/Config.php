<?php

declare(strict_types=1);

namespace B7s\FluentCut;

final class Config
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;
    private static ?string $configPath = null;

    private function __construct() {}

    /**
     * @return array<string, mixed>
     */
    public static function load(?string $configPath = null): array
    {
        if (self::$config !== null && $configPath === null) {
            return self::$config;
        }

        $paths = array_filter([
            $configPath,
            self::findProjectRoot() . '/fluentcut-config.php',
            getcwd() . '/fluentcut-config.php',
            dirname(__DIR__) . '/fluentcut-config.php',
        ]);

        foreach ($paths as $path) {
            if (file_exists($path)) {
                self::$config = require $path;
                self::$configPath = realpath($path);
                return self::$config;
            }
        }

        self::$config = self::defaults();
        return self::$config;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $config = self::load();

        if (str_contains($key, '.')) {
            $keys = explode('.', $key);
            $value = $config;

            foreach ($keys as $k) {
                if (!is_array($value) || !array_key_exists($k, $value)) {
                    return $default;
                }
                $value = $value[$k];
            }

            return $value;
        }

        return $config[$key] ?? $default;
    }

    public static function reset(): void
    {
        self::$config = null;
        self::$configPath = null;
    }

    public static function getConfigDirectory(): ?string
    {
        self::load();

        return self::$configPath !== null ? dirname(self::$configPath) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'ffmpeg_path' => null,
            'ffprobe_path' => null,
            'default_width' => 1920,
            'default_height' => 1080,
            'default_fps' => 30,
            'default_codec' => 'libx264',
            'default_duration' => 1.0,
            'timeout' => 0,
            'verbose' => false,
            'max_concurrent_segments' => 0,
        ];
    }

    private static function findProjectRoot(): string
    {
        $autoloadPaths = [
            __DIR__ . '/../../autoload.php',
            __DIR__ . '/../../../autoload.php',
            getcwd() . '/vendor/autoload.php',
        ];

        foreach ($autoloadPaths as $autoloadPath) {
            $realPath = realpath($autoloadPath);
            if ($realPath !== false && file_exists($realPath)) {
                $vendorDir = dirname($realPath);
                if (basename($vendorDir) === 'vendor') {
                    $projectRoot = dirname($vendorDir);
                    if (file_exists($projectRoot . '/composer.json')) {
                        return $projectRoot;
                    }
                }
            }
        }

        $dir = getcwd();
        for ($i = 0; $i < 10; $i++) {
            if (file_exists($dir . '/composer.json')) {
                $normalized = str_replace('\\', '/', $dir);
                if (!str_contains($normalized, '/vendor/') && !str_ends_with($normalized, '/vendor')) {
                    return $dir;
                }
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        return getcwd();
    }
}
