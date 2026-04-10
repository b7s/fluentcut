<?php

declare(strict_types=1);

namespace B7s\FluentCut\Support;

final readonly class ImageOverlay
{
    public function __construct(
        public string $path,
        public Position $position,
        public ?int $width = null,
        public ?int $height = null,
        public float $start = 0.0,
        public ?float $end = null,
    ) {}

    public function toFFmpegOverlay(int $canvasW, int $canvasH): string
    {
        $x = $this->position->resolveX($canvasW);
        $y = $this->position->resolveY($canvasH);

        $overlay = "[1:v]";

        if ($this->width !== null || $this->height !== null) {
            $w = $this->width ?? -1;
            $h = $this->height ?? -1;
            $overlay .= "scale={$w}:{$h},";
        }

        $overlay .= "setpts=PTS-STARTPTS[overlay];";
        $overlay .= "[0:v][overlay]overlay={$x}:{$y}";

        if ($this->start > 0.0 || $this->end !== null) {
            $start = $this->start;
            $end = $this->end ?? 999999.0;
            $overlay .= ":enable='between(t,{$start},{$end})'";
        }

        return $overlay;
    }
}
