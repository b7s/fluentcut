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
            self::SoftZoom, self::ZoomCenter => $this->zoomFilter($width, $height, $duration, "(iw-{$width})/2", "(ih-{$height})/2"),
            self::ZoomTopLeft => $this->zoomFilter($width, $height, $duration, '0', '0'),
            self::ZoomTopCenter => $this->zoomFilter($width, $height, $duration, "(iw-{$width})/2", '0'),
            self::ZoomTopRight => $this->zoomFilter($width, $height, $duration, "iw-{$width}", '0'),
            self::ZoomCenterLeft => $this->zoomFilter($width, $height, $duration, '0', "(ih-{$height})/2"),
            self::ZoomCenterRight => $this->zoomFilter($width, $height, $duration, "iw-{$width}", "(ih-{$height})/2"),
            self::ZoomBottomLeft => $this->zoomFilter($width, $height, $duration, '0', "ih-{$height}"),
            self::ZoomBottomCenter => $this->zoomFilter($width, $height, $duration, "(iw-{$width})/2", "ih-{$height}"),
            self::ZoomBottomRight => $this->zoomFilter($width, $height, $duration, "iw-{$width}", "ih-{$height}"),
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

    private function zoomFilter(int $width, int $height, float $duration, string $cropX, string $cropY, float $zoomAmount = 0.3): string
    {
        if ($duration <= 0 || $width <= 0 || $height <= 0) {
            return '';
        }

        $zoom = sprintf('%.2f', $zoomAmount);
        $dur = sprintf('%.6f', $duration);
        
        // Smooth progression from 0 to 1 using cosine easing
        $progression = "0.5-0.5*cos(t*PI/{$dur})";
        
        // Zoom factor: starts at 1.0, ends at (1.0 + zoomAmount)
        $zoomFactor = "1+{$zoom}*({$progression})";
        
        // For a smooth zoom without X/Y drift, we need to ensure the crop window
        // stays centered on the same relative point in the image.
        // 
        // The key insight: after scaling by z, to maintain the same relative position,
        // we need to scale the crop coordinates proportionally.
        // 
        // For expressions like "(iw-W)/2" (center), after scaling:
        // - Scaled image width: iw*z
        // - To keep centered: (iw*z - W)/2 = (iw-W)/2 * z + W/2 * (z-1)
        // 
        // But this causes drift because the expression is evaluated with the original iw.
        // Solution: Use zoompan filter approach - scale and crop in one smooth operation.
        // 
        // Alternative: Rewrite crop expressions to work with scaled dimensions
        // by replacing 'iw' with 'iw*z' and 'ih' with 'ih*z' in the expressions.
        
        // Replace iw and ih in the crop expressions with scaled versions
        $scaledCropX = str_replace(['iw', 'ih'], ["(iw*({$zoomFactor}))", "(ih*({$zoomFactor}))"], $cropX);
        $scaledCropY = str_replace(['iw', 'ih'], ["(iw*({$zoomFactor}))", "(ih*({$zoomFactor}))"], $cropY);

        return "scale='iw*({$zoomFactor})':'ih*({$zoomFactor})':eval=frame,crop={$width}:{$height}:{$scaledCropX}:{$scaledCropY}";
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
