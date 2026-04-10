<?php

declare(strict_types=1);

namespace B7s\FluentCut\Enums;

enum Format: string
{
    case Mp4 = 'mp4';
    case Mkv = 'mkv';
    case Mov = 'mov';
    case Webm = 'webm';
    case Gif = 'gif';

    public function defaultCodec(): Codec
    {
        return match ($this) {
            self::Mp4,
            self::Mkv,
            self::Mov => Codec::H264,
            self::Webm => Codec::Vp9,
            self::Gif => Codec::Gif,
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::Mp4 => 'video/mp4',
            self::Mkv => 'video/x-matroska',
            self::Mov => 'video/quicktime',
            self::Webm => 'video/webm',
            self::Gif => 'image/gif',
        };
    }

    public static function fromPath(string $path): self
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'mkv' => self::Mkv,
            'mov' => self::Mov,
            'webm' => self::Webm,
            'gif' => self::Gif,
            default => self::Mp4,
        };
    }
}
