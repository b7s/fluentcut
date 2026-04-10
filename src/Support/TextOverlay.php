<?php

declare(strict_types=1);

namespace B7s\FluentCut\Support;

use function file_exists;
use function is_file;
use function str_replace;

final readonly class TextOverlay
{
    private const ESCAPE_FROM = ['\\', "'", '"', ':', '%', '[', ']', ';', '{', '}'];
    private const ESCAPE_TO = ['\\\\', "\\'", '\\"', '\\:', '%%', '\\[', '\\]', '\\;', '\\{', '\\}'];

    public function __construct(
        public string $text,
        public Position $position,
        public int $fontSize = 32,
        public string $fontColor = 'white',
        public ?string $fontFile = null,
        public int $borderWidth = 0,
        public string $borderColor = 'black',
        public int $shadowX = 0,
        public int $shadowY = 0,
        public string $shadowColor = 'black',
        public float $start = 0.0,
        public ?float $end = null,
    ) {}

    public function toFFmpegDrawtext(int $canvasW, int $canvasH, float $clipDuration): string
    {
        $x = $this->position->toFFmpegDrawtextX();
        $y = $this->position->toFFmpegDrawtextY();

        $params = [
            "text='" . self::escape($this->text) . "'",
            "fontcolor={$this->fontColor}",
            "fontsize={$this->fontSize}",
        ];

        if ($this->fontFile !== null && file_exists($this->fontFile) && is_file($this->fontFile)) {
            $params[] = "fontfile='" . self::escape($this->fontFile) . "'";
        }

        if ($this->borderWidth > 0) {
            $params[] = "borderw={$this->borderWidth}";
            $params[] = "bordercolor={$this->borderColor}";
        }

        if ($this->shadowX !== 0 || $this->shadowY !== 0) {
            $params[] = "shadowcolor={$this->shadowColor}";
            $params[] = "shadowx={$this->shadowX}";
            $params[] = "shadowy={$this->shadowY}";
        }

        $params[] = "x={$x}";
        $params[] = "y={$y}";

        if ($this->start > 0.0) {
            $params[] = "enable='between(t,{$this->start}," . ($this->end ?? $clipDuration) . ")'";
        } elseif ($this->end !== null) {
            $params[] = "enable='between(t,0,{$this->end})'";
        }

        return 'drawtext=' . implode(':', $params);
    }

    public static function escape(string $value): string
    {
        return str_replace(self::ESCAPE_FROM, self::ESCAPE_TO, $value);
    }
}
