<?php

declare(strict_types=1);

namespace B7s\FluentCut\Enums;

use function sprintf;

enum VideoEffect: string
{
    case None = 'none';
    case SoftZoom = 'soft-zoom';
    case ZoomCenter = 'zoom-center';
    case ZoomTopLeft = 'zoom-top-left';
    case ZoomTopCenter = 'zoom-top-center';
    case ZoomTopRight = 'zoom-top-right';
    case ZoomCenterLeft = 'zoom-center-left';
    case ZoomCenterRight = 'zoom-center-right';
    case ZoomBottomLeft = 'zoom-bottom-left';
    case ZoomBottomCenter = 'zoom-bottom-center';
    case ZoomBottomRight = 'zoom-bottom-right';
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

    public function toFFmpegFilter(int $width = 0, int $height = 0, float $duration = 0.0, int $fps = 30, bool $isVideo = false): string
    {
        return match ($this) {
            self::None => '',
            self::SoftZoom, self::ZoomCenter => $this->zoomFilter($width, $height, $duration, $fps, 'iw/2-(iw/zoom/2)', 'ih/2-(ih/zoom/2)'),
            self::ZoomTopLeft => $this->zoomFilter($width, $height, $duration, $fps, '0', '0'),
            self::ZoomTopCenter => $this->zoomFilter($width, $height, $duration, $fps, 'iw/2-(iw/zoom/2)', '0'),
            self::ZoomTopRight => $this->zoomFilter($width, $height, $duration, $fps, 'iw-(iw/zoom)', '0'),
            self::ZoomCenterLeft => $this->zoomFilter($width, $height, $duration, $fps, '0', 'ih/2-(ih/zoom/2)'),
            self::ZoomCenterRight => $this->zoomFilter($width, $height, $duration, $fps, 'iw-(iw/zoom)', 'ih/2-(ih/zoom/2)'),
            self::ZoomBottomLeft => $this->zoomFilter($width, $height, $duration, $fps, '0', 'ih-(ih/zoom)'),
            self::ZoomBottomCenter => $this->zoomFilter($width, $height, $duration, $fps, 'iw/2-(iw/zoom/2)', 'ih-(ih/zoom)'),
            self::ZoomBottomRight => $this->zoomFilter($width, $height, $duration, $fps, 'iw-(iw/zoom)', 'ih-(ih/zoom)'),
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

    public function isZoom(): bool
    {
        return match ($this) {
            self::SoftZoom, self::ZoomCenter, self::ZoomTopLeft,
            self::ZoomTopCenter, self::ZoomTopRight, self::ZoomCenterLeft,
            self::ZoomCenterRight, self::ZoomBottomLeft, self::ZoomBottomCenter,
            self::ZoomBottomRight => true,
            default => false,
        };
    }

    private function zoomFilter(int $width, int $height, float $duration, int $fps, string $cropX, string $cropY, float $zoomAmount = 0.3): string
    {
        if ($duration <= 0 || $width <= 0 || $height <= 0) {
            return '';
        }

        $frames = (int)($duration * $fps);
        
        // Calculate zoom increment per frame for smooth progression
        // Starting at zoom=1.0, ending at zoom=(1.0 + zoomAmount)
        $zoomIncrement = sprintf('%.6f', $zoomAmount / $frames);
        
        // CRITICAL: To eliminate jitter/drift in zoompan:
        // 1. Upscale the image significantly (8000px width) before zoompan
        // 2. Use trunc() on x/y expressions to avoid fractional pixel positions
        //
        // The upscaling provides enough resolution for smooth sub-pixel movements,
        // and trunc() ensures the crop window always lands on whole pixel boundaries.
        //
        // References:
        // - https://superuser.com/questions/1112617/ffmpeg-smooth-zoompan-with-no-jiggle
        // - https://www.bannerbear.com/blog/how-to-do-a-ken-burns-style-effect-with-ffmpeg/
        
        // Wrap x and y expressions with trunc() to eliminate sub-pixel jitter
        $truncX = "trunc({$cropX})";
        $truncY = "trunc({$cropY})";
        
        return "scale=8000:-1,zoompan=z='zoom+{$zoomIncrement}':x={$truncX}:y={$truncY}:d={$frames}:s={$width}x{$height}:fps={$fps}";
    }

    public function description(): string
    {
        return match ($this) {
            self::None => 'No effect',
            self::SoftZoom => 'Slow zoom in to center (Ken Burns effect)',
            self::ZoomCenter => 'Slow zoom in to center',
            self::ZoomTopLeft => 'Slow zoom in to top-left',
            self::ZoomTopCenter => 'Slow zoom in to top-center',
            self::ZoomTopRight => 'Slow zoom in to top-right',
            self::ZoomCenterLeft => 'Slow zoom in to center-left',
            self::ZoomCenterRight => 'Slow zoom in to center-right',
            self::ZoomBottomLeft => 'Slow zoom in to bottom-left',
            self::ZoomBottomCenter => 'Slow zoom in to bottom-center',
            self::ZoomBottomRight => 'Slow zoom in to bottom-right',
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
