<?php

declare(strict_types=1);

namespace B7s\FluentCut\Enums;

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
            self::SoftZoom, self::ZoomCenter => $this->zoomPanFilter($width, $height, $duration, $fps, $isVideo, 'iw/2-(iw/zoom/2)', 'ih/2-(ih/zoom/2)'),
            self::ZoomTopLeft => $this->zoomPanFilter($width, $height, $duration, $fps, $isVideo, '0', '0'),
            self::ZoomTopCenter => $this->zoomPanFilter($width, $height, $duration, $fps, $isVideo, 'iw/2-(iw/zoom/2)', '0'),
            self::ZoomTopRight => $this->zoomPanFilter($width, $height, $duration, $fps, $isVideo, 'iw-iw/zoom', '0'),
            self::ZoomCenterLeft => $this->zoomPanFilter($width, $height, $duration, $fps, $isVideo, '0', 'ih/2-(ih/zoom/2)'),
            self::ZoomCenterRight => $this->zoomPanFilter($width, $height, $duration, $fps, $isVideo, 'iw-iw/zoom', 'ih/2-(ih/zoom/2)'),
            self::ZoomBottomLeft => $this->zoomPanFilter($width, $height, $duration, $fps, $isVideo, '0', 'ih-ih/zoom'),
            self::ZoomBottomCenter => $this->zoomPanFilter($width, $height, $duration, $fps, $isVideo, 'iw/2-(iw/zoom/2)', 'ih-ih/zoom'),
            self::ZoomBottomRight => $this->zoomPanFilter($width, $height, $duration, $fps, $isVideo, 'iw-iw/zoom', 'ih-ih/zoom'),
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

    private function zoomPanFilter(int $width, int $height, float $duration, int $fps, bool $isVideo, string $xExpr, string $yExpr, float $zoomAmount = 0.15): string
    {
        if ($duration <= 0 || $width <= 0 || $height <= 0) {
            return '';
        }

        $totalFrames = (int) ceil($duration * $fps);
        $zoom = sprintf('%.2f', $zoomAmount);
        $maxZoom = sprintf('%.2f', 1 + $zoomAmount);

        if ($isVideo) {
            return "zoompan=z='min(max(zoom,pzoom)+{$zoom}/{$totalFrames},{$maxZoom})':d=1:x='{$xExpr}':y='{$yExpr}':s={$width}x{$height}:fps={$fps}";
        }

        return "zoompan=z='1+{$zoom}*(on/{$totalFrames})':d={$totalFrames}:x='{$xExpr}':y='{$yExpr}':s={$width}x{$height}:fps={$fps}";
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
