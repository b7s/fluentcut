<?php

declare(strict_types=1);

namespace B7s\FluentCut\Exceptions;

class RenderException extends FluentCutException
{
    public static function failed(string $reason): self
    {
        return new self("Render failed: {$reason}");
    }

    public static function ffmpegError(string $output): self
    {
        return new self("FFmpeg error: {$output}");
    }

    public static function noOutput(): self
    {
        return new self('No output path specified. Use saveTo() before render().');
    }

    public static function noClips(): self
    {
        return new self('No clips added to the timeline. Use addImage(), addVideo(), or fromVideo() first.');
    }

    public static function invalidDuration(float $duration): self
    {
        return new self("Invalid duration: {$duration}s. Duration must be positive.");
    }

    public static function fileNotFound(string $path): self
    {
        return new self("File not found: {$path}");
    }

    public static function invalidDimensions(int $width, int $height): self
    {
        return new self("Invalid dimensions: {$width}x{$height}. Both must be positive integers.");
    }

    public static function unsupportedFormat(string $format): self
    {
        return new self("Unsupported output format: {$format}");
    }
}
