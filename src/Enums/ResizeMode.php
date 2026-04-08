<?php

declare(strict_types=1);

namespace B7s\FluentCut\Enums;

use function implode;

enum ResizeMode: string
{
    case Contain = 'contain';
    case ContainBlur = 'contain-blur';
    case Cover = 'cover';
    case Stretch = 'stretch';

    public function toFFmpegFilter(int $canvasW, int $canvasH): string
    {
        return match ($this) {
            self::Contain => "scale={$canvasW}:{$canvasH}:force_original_aspect_ratio=decrease,pad={$canvasW}:{$canvasH}:(ow-iw)/2:(oh-ih)/2:color=black",
            self::ContainBlur => implode(',', [
                "split[original][bg]",
                "[bg]scale={$canvasW}:{$canvasH}:force_original_aspect_ratio=increase,crop={$canvasW}:{$canvasH},boxblur=10:1[blurred]",
                "[original]scale={$canvasW}:{$canvasH}:force_original_aspect_ratio=decrease[fg]",
                "[blurred][fg]overlay=(W-w)/2:(H-h)/2",
            ]),
            self::Cover => "scale={$canvasW}:{$canvasH}:force_original_aspect_ratio=increase,crop={$canvasW}:{$canvasH}",
            self::Stretch => "scale={$canvasW}:{$canvasH}",
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Contain => 'Fit within frame with letterboxing',
            self::ContainBlur => 'Fit with blurred background letterbox',
            self::Cover => 'Crop to fill frame (aspect ratio preserved)',
            self::Stretch => 'Stretch to fill (aspect ratio distorted)',
        };
    }
}
