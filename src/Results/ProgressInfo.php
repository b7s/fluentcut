<?php

declare(strict_types=1);

namespace B7s\FluentCut\Results;

use function round;

final readonly class ProgressInfo
{
    public function __construct(
        public int $frame = 0,
        public float $currentTime = 0.0,
        public float $bitrate = 0.0,
        public float $speed = 0.0,
        public int $totalFrames = 0,
        public float $totalDuration = 0.0,
        public float $percentage = 0.0,
        public int $segment = 0,
        public int $totalSegments = 0,
        public string $phase = 'rendering',
    ) {}

    public function getFormattedTime(): string
    {
        $minutes = (int) floor($this->currentTime / 60);
        $seconds = fmod($this->currentTime, 60);

        return sprintf('%02d:%05.2f', $minutes, $seconds);
    }

    public function getFormattedSpeed(): string
    {
        return $this->speed > 0 ? round($this->speed, 1) . 'x' : '...';
    }

    public function getFormattedBitrate(): string
    {
        if ($this->bitrate <= 0) {
            return '...';
        }

        if ($this->bitrate >= 1000) {
            return round($this->bitrate / 1000, 1) . ' Mbps';
        }

        return round($this->bitrate, 0) . ' kbps';
    }

    public function getFormattedPercentage(): string
    {
        return round($this->percentage, 1) . '%';
    }

    /**
     * @return array{
     *     frame: int,
     *     current_time: float,
     *     bitrate: float,
     *     speed: float,
     *     percentage: float,
     *     segment: int,
     *     total_segments: int,
     *     phase: string,
     * }
     */
    public function toArray(): array
    {
        return [
            'frame' => $this->frame,
            'current_time' => $this->currentTime,
            'bitrate' => $this->bitrate,
            'speed' => $this->speed,
            'percentage' => $this->percentage,
            'segment' => $this->segment,
            'total_segments' => $this->totalSegments,
            'phase' => $this->phase,
        ];
    }
}
