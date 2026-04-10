<?php

declare(strict_types=1);

namespace B7s\FluentCut\Support;

use function is_int;
use function is_string;
use function ltrim;
use function round;
use function rtrim;
use function str_ends_with;
use function strtolower;
use function trim;

final readonly class Position
{
    private const CENTER = PHP_INT_MIN;
    private const EDGE_END = PHP_INT_MAX;

    public function __construct(
        public int $x,
        public int $y,
        public bool $isPercentX = false,
        public bool $isPercentY = false,
    ) {}

    public static function parse(int|string $x, int|string $y): self
    {
        return new self(
            x: self::parseValue($x),
            y: self::parseValue($y),
            isPercentX: is_string($x) && str_ends_with($x, '%'),
            isPercentY: is_string($y) && str_ends_with($y, '%'),
        );
    }

    public function resolveX(int $canvasWidth, int $elementWidth = 0): int
    {
        if ($this->x === self::CENTER) {
            return (int) round(($canvasWidth - $elementWidth) / 2);
        }

        if ($this->x === self::EDGE_END) {
            return $canvasWidth - $elementWidth;
        }

        return $this->isPercentX
            ? (int) round($canvasWidth * ($this->x / 100))
            : $this->x;
    }

    public function resolveY(int $canvasHeight, int $elementHeight = 0): int
    {
        if ($this->y === self::CENTER) {
            return (int) round(($canvasHeight - $elementHeight) / 2);
        }

        if ($this->y === self::EDGE_END) {
            return $canvasHeight - $elementHeight;
        }

        return $this->isPercentY
            ? (int) round($canvasHeight * ($this->y / 100))
            : $this->y;
    }

    public function toFFmpegDrawtextX(): string
    {
        if ($this->x === self::CENTER) {
            return '(w-text_w)/2';
        }

        if ($this->x === self::EDGE_END) {
            return 'w-text_w';
        }

        if ($this->isPercentX) {
            return "w*{$this->x}/100";
        }

        return (string) $this->x;
    }

    public function toFFmpegDrawtextY(): string
    {
        if ($this->y === self::CENTER) {
            return '(h-text_h)/2';
        }

        if ($this->y === self::EDGE_END) {
            return 'h-text_h';
        }

        if ($this->isPercentY) {
            return "h*{$this->y}/100";
        }

        return (string) $this->y;
    }

    private static function parseValue(int|string $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        $value = trim($value);

        if (str_ends_with($value, '%')) {
            $percent = ltrim(rtrim($value, '%'), '0') ?: '0';

            return (int) $percent;
        }

        return match (strtolower($value)) {
            'center', 'middle' => self::CENTER,
            'top', 'left' => 0,
            'bottom', 'right' => self::EDGE_END,
            default => (int) $value,
        };
    }
}
