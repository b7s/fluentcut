<?php

declare(strict_types=1);

namespace B7s\FluentCut\Enums;

enum Codec: string
{
    case H264 = 'libx264';
    case H265 = 'libx265';
    case Vp9 = 'libvpx-vp9';
    case Copy = 'copy';
    case Gif = 'gif';

    public function description(): string
    {
        return match ($this) {
            self::H264 => 'H.264 / AVC (most compatible)',
            self::H265 => 'H.265 / HEVC (better compression)',
            self::Vp9 => 'VP9 (WebM)',
            self::Copy => 'Stream copy (no re-encode)',
            self::Gif => 'GIF palette',
        };
    }

    public function isReEncode(): bool
    {
        return $this !== self::Copy;
    }

    /**
     * @return string[]
     */
    public function defaultOutputArgs(): array
    {
        return match ($this) {
            self::H264 => ['-c:v', 'libx264', '-preset', 'medium', '-crf', '23', '-pix_fmt', 'yuv420p'],
            self::H265 => ['-c:v', 'libx265', '-preset', 'medium', '-crf', '28', '-pix_fmt', 'yuv420p'],
            self::Vp9 => ['-c:v', 'libvpx-vp9', '-crf', '30', '-b:v', '0', '-pix_fmt', 'yuv420p'],
            self::Copy => ['-c:v', 'copy'],
            self::Gif => [],
        };
    }

    public function supportsGpu(HardwareAccel $accel): bool
    {
        return $accel->supportsCodec($this);
    }
}
