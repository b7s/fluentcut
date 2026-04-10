<?php

declare(strict_types=1);

namespace B7s\FluentCut\Enums;

enum VideoEffect: string
{
    case None = 'none';
    case SoftZoom = 'soft-zoom';
    case Grayscale = 'grayscale';
    case Sepia = 'sepia';
    case Blur = 'blur';
    case Sharpen = 'sharpen';
    case Vignette = 'vignette';
    case Brightness = 'brightness';
    case Contrast = 'contrast';
    case Saturate = 'saturate';
    case Desaturate = 'desaturate';
    case Negate = 'negate';
    case EdgeDetect = 'edge-detect';
    case Pixelate = 'pixelate';

    public function toFFmpegFilter(int $width = 0, int $height = 0, float $duration = 0.0, int $fps = 30): string
    {
        return match ($this) {
            self::None => '',
            self::SoftZoom => $this->softZoomFilter($width, $height, $duration, $fps),
            self::Grayscale => 'format=gray',
            self::Sepia => 'colorchannelmixer=.393:.769:.189:0:.349:.686:.168:0:.272:.534:.131',
            self::Blur => 'boxblur=5:1',
            self::Sharpen => 'unsharp=5:5:1.5',
            self::Vignette => 'vignette=angle=PI/4',
            self::Brightness => 'eq=brightness=0.15',
            self::Contrast => 'eq=contrast=1.4',
            self::Saturate => 'eq=saturation=2.0',
            self::Desaturate => 'eq=saturation=0.4',
            self::Negate => 'negate',
            self::EdgeDetect => 'edgedetect',
            self::Pixelate => "scale=iw/10:ih/10,scale=iw*10:ih*10:flags=neighbor",
        };
    }

    private function softZoomFilter(int $width, int $height, float $duration, int $fps): string
    {
        if ($duration <= 0 || $width <= 0 || $height <= 0) {
            return '';
        }

        $totalFrames = (int) ceil($duration * $fps);

        return "zoompan=z='1+0.1*(on/{$totalFrames})*(on/{$totalFrames})':d={$totalFrames}:s={$width}x{$height}:fps={$fps}";
    }

    public function description(): string
    {
        return match ($this) {
            self::None => 'No effect',
            self::SoftZoom => 'Slow zoom in (Ken Burns effect)',
            self::Grayscale => 'Convert to grayscale',
            self::Sepia => 'Sepia tone (vintage warm look)',
            self::Blur => 'Gaussian blur',
            self::Sharpen => 'Sharpen details',
            self::Vignette => 'Dark edges vignette',
            self::Brightness => 'Increase brightness',
            self::Contrast => 'Increase contrast',
            self::Saturate => 'Boost color saturation',
            self::Desaturate => 'Reduce color saturation',
            self::Negate => 'Invert colors',
            self::EdgeDetect => 'Edge detection outline',
            self::Pixelate => 'Pixelation mosaic',
        };
    }
}
