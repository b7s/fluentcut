<?php

declare(strict_types=1);

namespace B7s\FluentCut\Results;

final readonly class RenderResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public bool    $success,
        public ?string $outputPath,
        public ?float  $duration,
        public ?int    $width,
        public ?int    $height,
        public ?string $format,
        public ?int    $fileSize,
        public ?string $error = null,
        public array   $metadata = [],
    ) {}

    /**
     * @param array<string, mixed> $metadata
     */
    public static function success(
        string $outputPath,
        float $duration,
        int $width,
        int $height,
        string $format,
        int $fileSize,
        array $metadata = [],
    ): self {
        return new self(
            success: true,
            outputPath: $outputPath,
            duration: $duration,
            width: $width,
            height: $height,
            format: $format,
            fileSize: $fileSize,
            metadata: $metadata,
        );
    }

    public static function failure(string $error): self
    {
        return new self(
            success: false,
            outputPath: null,
            duration: null,
            width: null,
            height: null,
            format: null,
            fileSize: null,
            error: $error,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function getFormattedDuration(): ?string
    {
        if ($this->duration === null) {
            return null;
        }

        $minutes = (int) floor($this->duration / 60);
        $seconds = fmod($this->duration, 60);

        return sprintf('%02d:%05.2f', $minutes, $seconds);
    }

    public function getFormattedSize(): ?string
    {
        if ($this->fileSize === null) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->fileSize;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return sprintf('%.1f %s', $size, $units[$unit]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'output_path' => $this->outputPath,
            'duration' => $this->duration,
            'duration_formatted' => $this->getFormattedDuration(),
            'width' => $this->width,
            'height' => $this->height,
            'format' => $this->format,
            'file_size' => $this->fileSize,
            'file_size_formatted' => $this->getFormattedSize(),
            'error' => $this->error,
            'metadata' => $this->metadata,
        ];
    }
}
