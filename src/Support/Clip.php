<?php

declare(strict_types=1);

namespace B7s\FluentCut\Support;

use B7s\FluentCut\Enums\ResizeMode;
use B7s\FluentCut\Enums\Transition;
use B7s\FluentCut\Enums\VideoEffect;
use B7s\FluentCut\Exceptions\RenderException;

use function implode;
use function preg_match;
use function serialize;
use function str_contains;

final class Clip
{
    private const array CSS_COLORS = [
        'black', 'white', 'red', 'green', 'blue', 'yellow', 'cyan', 'magenta',
        'gray', 'grey', 'orange', 'pink', 'purple', 'brown', 'silver', 'gold',
        'maroon', 'olive', 'lime', 'aqua', 'teal', 'navy', 'fuchsia',
        'transparent', 'none',
    ];

    public ?string $videoPath = null;
    public ?string $imagePath = null;
    public float $duration = 0.0;
    public ?float $start = null;
    public ?float $end = null;
    public ?string $backgroundColor = null;
    public ResizeMode $resizeMode;
    public ?Transition $transition = null;
    public float $transitionDuration = 0.5;

    /** @var VideoEffect[] */
    public array $effects = [];

    /** @var TextOverlay[] */
    public array $textOverlays = [];

    /** @var ImageOverlay[] */
    public array $imageOverlays = [];

    /** @var string[] */
    public array $audioPaths = [];

    public function __construct()
    {
        $this->resizeMode = ResizeMode::ContainBlur;
    }

    public static function fromImage(string $path, float $duration = 1.0): self
    {
        $clip = new self();
        $clip->imagePath = $path;
        $clip->duration = $duration;

        return $clip;
    }

    public static function fromVideo(string $path, ?float $start = null, ?float $end = null): self
    {
        $clip = new self();
        $clip->videoPath = $path;
        $clip->start = $start;
        $clip->end = $end;

        return $clip;
    }

    /**
     * @throws RenderException
     */
    public static function fromColor(string $color, float $duration): self
    {
        self::validateColor($color);

        $clip = new self();
        $clip->backgroundColor = $color;
        $clip->duration = $duration;

        return $clip;
    }

    public function isVideo(): bool
    {
        return $this->videoPath !== null;
    }

    public function isImage(): bool
    {
        return $this->imagePath !== null;
    }

    public function isColor(): bool
    {
        return $this->backgroundColor !== null;
    }

    public function cacheKey(int $width, int $height, int $fps): string
    {
        $parts = [
            $this->videoPath ?? $this->imagePath ?? $this->backgroundColor ?? '',
            (string) $this->duration,
            $this->start !== null ? (string) $this->start : '',
            $this->end !== null ? (string) $this->end : '',
            $this->resizeMode->value,
            implode(',', array_map(static fn(VideoEffect $e) => $e->value, $this->effects)),
            implode(',', array_map(fn($t) => $this->serializeTextOverlay($t), $this->textOverlays)),
            implode(',', array_map(static fn($o) => "{$o->path}|{$o->position->x}|{$o->position->y}", $this->imageOverlays)),
            implode(',', $this->audioPaths),
            $this->transition?->value ?? 'none',
            (string) $this->transitionDuration,
            "{$width}x{$height}x{$fps}",
        ];

        return hash('xxh3', implode('|', array_filter($parts, fn($p) => $p !== '')));
    }

    public function cacheKeyWithCanvas(int $width, int $height, int $fps): string
    {
        return $this->cacheKey($width, $height, $fps);
    }

    private function serializeTextOverlay(TextOverlay $t): string
    {
        return "{$t->text}|{$t->position->x}|{$t->position->y}|{$t->fontSize}|{$t->fontColor}|{$t->borderWidth}";
    }

    /**
     * @throws RenderException
     */
    public static function validateColor(string $color): void
    {
        $lower = strtolower($color);

        if (in_array($lower, self::CSS_COLORS, true)) {
            return;
        }

        if (preg_match('/^#([0-9a-f]{3,8})$/i', $color)) {
            return;
        }

        if (preg_match('/^0x([0-9a-f]{3,8})$/i', $color)) {
            return;
        }

        if (str_contains($color, ':') || str_contains($color, ';') || str_contains($color, '[') || str_contains($color, ']')) {
            throw RenderException::failed("Invalid color value: '{$color}'. Use CSS color names or hex values (#RRGGBB).");
        }
    }
}
