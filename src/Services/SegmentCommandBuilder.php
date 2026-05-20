<?php

declare(strict_types=1);

namespace B7s\FluentCut\Services;

use B7s\FluentCut\Enums\Codec;
use B7s\FluentCut\Enums\HardwareAccel;
use B7s\FluentCut\Enums\VideoEffect;
use B7s\FluentCut\Exceptions\FFmpegNotFoundException;
use B7s\FluentCut\Exceptions\RenderException;
use B7s\FluentCut\Support\Clip;
use B7s\FluentCut\Support\ImageOverlay;

use function count;
use function implode;
use function sprintf;

final readonly class SegmentCommandBuilder
{
    public function __construct(
        private FFmpegService $ffmpeg,
    ) {}

    /**
     * @return string[]
     *
     * @throws RenderException|FFmpegNotFoundException
     */
    public function buildSegmentCommand(Clip $clip, int $width, int $height, int $fps, Codec $codec, string $outputPath, ?HardwareAccel $hardwareAccel = null): array
    {
        if ($clip->isVideo()) {
            return $this->buildVideoSegmentCommand($clip, $width, $height, $fps, $codec, $outputPath, $hardwareAccel);
        }

        if ($clip->isImage()) {
            return $this->buildImageSegmentCommand($clip, $width, $height, $fps, $codec, $outputPath, $hardwareAccel);
        }

        if ($clip->isColor()) {
            return $this->buildColorSegmentCommand($clip, $width, $height, $fps, $codec, $outputPath, $hardwareAccel);
        }

        throw RenderException::failed('Clip has no source (video, image, or color).');
    }

    /**
     * @return string[]
     */
    public function collectClipFilters(Clip $clip, int $width, int $height, int $fps): array
    {
        $filters = [];

        foreach ($clip->effects as $effect) {
            $effectFilter = $effect->toFFmpegFilter($width, $height, $clip->duration, $fps, $clip->isVideo());
            if ($effectFilter !== '') {
                $filters[] = $effectFilter;
            }
        }

        foreach ($clip->textOverlays as $textOverlay) {
            $filters[] = $textOverlay->toFFmpegDrawtext($width, $height, $clip->duration);
        }

        return $filters;
    }

    /**
     * @param  string[]  $command
     * @param  string[]  $filters
     * @return string[]
     */
    public function appendOverlaysFiltersAndOutput(array $command, Clip $clip, array $filters, int $width, int $height, int $fps, Codec $codec, string $outputPath, bool $useGpu, ?HardwareAccel $hardwareAccel): array
    {
        $hasImageOverlays = ! empty($clip->imageOverlays);
        $audioClipCount = count($clip->audioPaths);
        $hasAudioClips = $audioClipCount > 0;
        $sourceHasAudio = $clip->isVideo();

        if ($hasImageOverlays) {
            foreach ($clip->imageOverlays as $overlay) {
                $command = [...$command, '-i', $overlay->path];
            }
        }

        foreach ($clip->audioPaths as $audioFile) {
            $command = [...$command, '-i', $audioFile];
        }

        $audioLabels = [];
        for ($i = 1; $i <= $audioClipCount; $i++) {
            $audioLabels[] = "[{$i}:a]";
        }

        if ($hasImageOverlays) {
            $filterChain = implode(',', $filters);
            $command = [...$command, '-filter_complex', $this->buildOverlayFilterComplex($clip->imageOverlays, $filterChain, $width, $height)];
            $command = [...$command, '-map', '[vout]'];
            $command = $this->hasAudioClips($hasAudioClips, $command, $audioLabels, $audioClipCount, $sourceHasAudio);
        } elseif (! empty($filters)) {
            $command = [...$command, '-vf', implode(',', $filters)];
            if ($hasAudioClips) {
                $command[] = '-filter_complex';
                $command[] = implode('', $audioLabels)."amix=inputs={$audioClipCount}:duration=first[aout]";
                $command[] = '-map';
                $command[] = '0:v:0';
                $command[] = '-map';
                $command[] = '[aout]';
            } else {
                $command[] = '-map';
                $command[] = '0:v:0';
                if ($sourceHasAudio) {
                    $command[] = '-map';
                    $command[] = '0:a:0?';
                }
            }
        } else {
            $command[] = '-map';
            $command[] = '0:v:0';
            $command = $this->hasAudioClips($hasAudioClips, $command, $audioLabels, $audioClipCount, $sourceHasAudio);
        }

        $outputArgs = $useGpu ? $hardwareAccel->gpuOutputArgs($codec) : $codec->defaultOutputArgs();

        return [...$command, ...$outputArgs, '-r', (string) $fps, '-y', $outputPath];
    }

    /**
     * @return string[]
     *
     * @throws FFmpegNotFoundException
     */
    private function buildVideoSegmentCommand(Clip $clip, int $width, int $height, int $fps, Codec $codec, string $outputPath, ?HardwareAccel $hardwareAccel = null): array
    {
        $useGpu = $hardwareAccel !== null && $hardwareAccel->supportsCodec($codec) && $clip->isVideo() && $hardwareAccel->supportsHwaccelInput();
        $command = [$this->ffmpeg->getFFmpegPath()];

        if ($clip->start !== null) {
            $command = [...$command, '-ss', sprintf('%.6f', $clip->start)];
        }

        $command = [...$command, '-i', $clip->videoPath];

        if ($clip->end !== null) {
            $command = [...$command, '-t', sprintf('%.6f', max(0, $clip->end - ($clip->start ?? 0.0)))];
        }

        $filters = [];

        if ($codec->isReEncode()) {
            $filters[] = $clip->resizeMode->toFFmpegFilter($width, $height);
        }

        $filters = [...$filters, ...$this->collectClipFilters($clip, $width, $height, $fps)];

        return $this->appendOverlaysFiltersAndOutput($command, $clip, $filters, $width, $height, $fps, $codec, $outputPath, $useGpu, $hardwareAccel);
    }

    /**
     * @return string[]
     *
     * @throws FFmpegNotFoundException
     */
    private function buildImageSegmentCommand(Clip $clip, int $width, int $height, int $fps, Codec $codec, string $outputPath, ?HardwareAccel $hardwareAccel = null): array
    {
        $useGpu = $hardwareAccel !== null && $hardwareAccel->supportsCodec($codec);
        $useHwAccel = $useGpu && $clip->isVideo() && $hardwareAccel->supportsHwaccelInput();
        $command = [$this->ffmpeg->getFFmpegPath()];

        if ($useHwAccel) {
            $command = [...$command, ...$hardwareAccel->hwAccelInputArgs()];
        }

        $hasZoom = count(array_filter($clip->effects, static fn (VideoEffect $e) => $e->isZoom())) > 0;

        if ($hasZoom) {
            $command = [...$command, '-loop', '1', '-framerate', (string) $fps, '-i', $clip->imagePath];
        } else {
            $command = [...$command, '-loop', '1', '-i', $clip->imagePath];
        }

        if ($clip->duration > 0) {
            $command = [...$command, '-t', sprintf('%.6f', $clip->duration)];
        }

        $filters = [$clip->resizeMode->toFFmpegFilter($width, $height), ...$this->collectClipFilters($clip, $width, $height, $fps)];

        $command = $this->appendOverlaysFiltersAndOutput($command, $clip, $filters, $width, $height, $fps, $codec, $outputPath, $useGpu, $hardwareAccel);

        if (! empty($clip->audioPaths)) {
            $command[] = '-shortest';
        }

        return $command;
    }

    /**
     * @return string[]
     *
     * @throws FFmpegNotFoundException
     */
    private function buildColorSegmentCommand(Clip $clip, int $width, int $height, int $fps, Codec $codec, string $outputPath, ?HardwareAccel $hardwareAccel = null): array
    {
        $command = [$this->ffmpeg->getFFmpegPath(), '-f', 'lavfi', '-i', sprintf('color=c=%s:s=%dx%d:d=%.6f:r=%d', $clip->backgroundColor, $width, $height, $clip->duration, $fps)];

        $filters = $this->collectClipFilters($clip, $width, $height, $fps);

        if (! empty($filters)) {
            $command = [...$command, '-vf', implode(',', $filters)];
        }

        return [...$command, ...$codec->defaultOutputArgs(), '-y', $outputPath];
    }

    /**
     * @param  ImageOverlay[]  $overlays
     */
    public function buildOverlayFilterComplex(array $overlays, string $baseFilter, int $canvasW, int $canvasH): string
    {
        $parts = [];
        $parts[] = "[0:v]{$baseFilter}[base]";

        foreach ($overlays as $idx => $overlay) {
            $inputIdx = $idx + 1;
            $x = $overlay->position->resolveX($canvasW);
            $y = $overlay->position->resolveY($canvasH);

            $scale = '';
            if ($overlay->width !== null || $overlay->height !== null) {
                $w = $overlay->width ?? -1;
                $h = $overlay->height ?? -1;
                $scale = "scale={$w}:{$h},";
            }

            $enable = '';
            if ($overlay->start > 0.0 || $overlay->end !== null) {
                $start = $overlay->start;
                $end = $overlay->end ?? 999999.0;
                $enable = ":enable='between(t,{$start},{$end})'";
            }

            $isLast = $idx === count($overlays) - 1;
            $outLabel = $isLast ? '[vout]' : "[v{$idx}]";
            $inLabel = $idx === 0 ? '[base]' : '[v'.($idx - 1).']';

            $parts[] = "[{$inputIdx}:v]{$scale}setpts=PTS-STARTPTS[ol{$idx}]";
            $parts[] = "{$inLabel}[ol{$idx}]overlay={$x}:{$y}{$enable}{$outLabel}";
        }

        return implode(';', $parts);
    }

    /**
     * @param  string[]  $command
     * @param  string[]  $audioLabels
     * @return string[]
     */
    private function hasAudioClips(bool $hasAudioClips, array $command, array $audioLabels, int $audioClipCount, bool $sourceHasAudio): array
    {
        if ($hasAudioClips) {
            $command[] = '-filter_complex';
            $command[] = implode('', $audioLabels)."amix=inputs={$audioClipCount}:duration=first[aout]";
            $command[] = '-map';
            $command[] = '[aout]';
        } elseif ($sourceHasAudio) {
            $command[] = '-map';
            $command[] = '0:a:0?';
        }

        return $command;
    }
}
