<?php

declare(strict_types=1);

namespace B7s\FluentCut\Support;

final class Platform
{
    private static ?string $os = null;

    private function __construct() {}

    public static function os(): string
    {
        if (self::$os !== null) {
            return self::$os;
        }

        return self::$os = PHP_OS_FAMILY;
    }

    public static function isWindows(): bool
    {
        return self::os() === 'Windows';
    }

    public static function isMacOS(): bool
    {
        return self::os() === 'Darwin';
    }

    public static function isLinux(): bool
    {
        return self::os() === 'Linux';
    }

    public static function ffmpegBinaryName(): string
    {
        return self::isWindows() ? 'ffmpeg.exe' : 'ffmpeg';
    }

    public static function ffprobeBinaryName(): string
    {
        return self::isWindows() ? 'ffprobe.exe' : 'ffprobe';
    }

    public static function reset(): void
    {
        self::$os = null;
    }
}
