<?php

declare(strict_types=1);

namespace B7s\FluentCut\Exceptions;

class FFmpegNotFoundException extends FluentCutException
{
    public static function neitherFound(): self
    {
        return new self(
            'FFmpeg and FFprobe were not found on your system. '
            . 'Please install FFmpeg: https://ffmpeg.org/download.php'
        );
    }

    public static function ffmpegNotFound(): self
    {
        return new self(
            'FFmpeg not found. Please install FFmpeg and ensure it is in your PATH. '
            . 'Download: https://ffmpeg.org/download.php'
        );
    }

    public static function ffprobeNotFound(): self
    {
        return new self(
            'FFprobe not found. Please install FFmpeg (includes FFprobe) and ensure it is in your PATH. '
            . 'Download: https://ffmpeg.org/download.php'
        );
    }
}
