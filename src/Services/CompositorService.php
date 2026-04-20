<?php

declare(strict_types=1);

namespace B7s\FluentCut\Services;

use B7s\FluentCut\Enums\Codec;
use B7s\FluentCut\Enums\Format;
use B7s\FluentCut\Enums\HardwareAccel;
use B7s\FluentCut\Enums\ResizeMode;
use B7s\FluentCut\Enums\Transition;
use B7s\FluentCut\Enums\VideoEffect;
use B7s\FluentCut\Exceptions\FFmpegNotFoundException;
use B7s\FluentCut\Exceptions\RenderException;
use B7s\FluentCut\Results\ProgressInfo;
use B7s\FluentCut\Results\RenderResult;
use B7s\FluentCut\Support\Clip;
use B7s\FluentCut\Support\ImageOverlay;
use JsonException;
use Random\RandomException;
use RuntimeException;
use Throwable;

use function bin2hex;
use function count;
use function file_exists;
use function file_put_contents;
use function implode;
use function is_dir;
use function is_file;
use function max;
use function mkdir;
use function random_bytes;
use function rename;
use function sprintf;
use function str_replace;
use function sys_get_temp_dir;
use function unlink;

final class CompositorService
{
    private bool $cacheEnabled;

    private string $cacheDir;

    private bool $clearCacheAfterRender;

    public function __construct(
        private readonly FFmpegService $ffmpeg,
        bool $cacheEnabled = true,
        ?string $cacheDir = null,
        bool $clearCacheAfterRender = true,
    ) {
        $this->cacheEnabled = $cacheEnabled;
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir().'/fluentcut-cache';
        $this->clearCacheAfterRender = $clearCacheAfterRender;

        if ($this->cacheEnabled && ! is_dir($this->cacheDir) && ! mkdir($concurrentDirectory = $this->cacheDir, 0755, true) && ! is_dir($concurrentDirectory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }
    }

    /**
     * @throws RandomException
     */
    private static function randomName(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @param  Clip[]  $clips
     * @param  callable(ProgressInfo): void|null  $onProgress
     *
     * @throws Throwable
     */
    public function render(
        array $clips,
        int $width,
        int $height,
        int $fps,
        string $outputPath,
        ?Codec $codec = null,
        ?Transition $transition = null,
        float $transitionDuration = 0.5,
        array $audioTracks = [],
        ?string $audioPath = null,
        bool $loopAudio = false,
        ?float $audioVolume = null,
        bool $keepSourceAudio = true,
        int $timeout = 0,
        bool $verbose = false,
        ?callable $onProgress = null,
        ?HardwareAccel $hardwareAccel = null,
        int $maxConcurrentSegments = 0,
        bool $forceCpu = false,
    ): RenderResult {
        if (empty($clips)) {
            return RenderResult::failure('No clips to render.');
        }

        $format = Format::fromPath($outputPath);
        $codec = $codec ?? $format->defaultCodec();
        $clips = $this->resolveDurations($clips);

        $resolvedAccel = $forceCpu ? null : $this->ffmpeg->resolveHardwareAccel($hardwareAccel);

        $totalDuration = 0.0;
        foreach ($clips as $clip) {
            $totalDuration += $clip->duration;
        }

        if ($format === Format::Gif) {
            return $this->renderGif($clips, $width, $height, $fps, $outputPath, $transition, $transitionDuration, $timeout, $verbose, $onProgress, $totalDuration);
        }

        return $this->renderVideo(
            clips: $clips,
            width: $width,
            height: $height,
            fps: $fps,
            outputPath: $outputPath,
            codec: $codec,
            format: $format,
            transition: $transition,
            transitionDuration: $transitionDuration,
            audioTracks: $audioTracks,
            audioPath: $audioPath,
            loopAudio: $loopAudio,
            audioVolume: $audioVolume,
            keepSourceAudio: $keepSourceAudio,
            timeout: $timeout,
            verbose: $verbose,
            onProgress: $onProgress,
            totalDuration: $totalDuration,
            hardwareAccel: $resolvedAccel,
            maxConcurrentSegments: $maxConcurrentSegments,
            forceCpu: $forceCpu,
        );
    }

    /**
     * @param  Clip[]  $clips
     * @param  callable(ProgressInfo): void|null  $onProgress
     *
     * @throws Throwable
     */
    private function renderVideo(
        array $clips,
        int $width,
        int $height,
        int $fps,
        string $outputPath,
        Codec $codec,
        Format $format,
        ?Transition $transition,
        float $transitionDuration,
        array $audioTracks = [],
        ?string $audioPath = null,
        bool $loopAudio = false,
        ?float $audioVolume = null,
        bool $keepSourceAudio = true,
        int $timeout = 0,
        bool $verbose = false,
        ?callable $onProgress = null,
        float $totalDuration = 0.0,
        ?HardwareAccel $hardwareAccel = null,
        int $maxConcurrentSegments = 0,
        bool $forceCpu = false,
    ): RenderResult {
        $tempFiles = [];
        $totalSegments = count($clips);

        try {
            $segmentPaths = $this->renderSegments(
                $clips, $width, $height, $fps, $codec, $verbose,
                $tempFiles, $onProgress, $fps, $totalSegments, $totalDuration,
                $timeout, $hardwareAccel, $maxConcurrentSegments,
            );
        } catch (Throwable $e) {
            if ($hardwareAccel !== null && ! $forceCpu) {
                if ($verbose) {
                    error_log('[FluentCut] GPU rendering failed, falling back to CPU: '.$e->getMessage());
                }

                foreach ($tempFiles as $tempFile) {
                    if (file_exists($tempFile)) {
                        @unlink($tempFile);
                    }
                }
                $tempFiles = [];

                $segmentPaths = $this->renderSegments(
                    $clips, $width, $height, $fps, $codec, $verbose,
                    $tempFiles, $onProgress, $fps, $totalSegments, $totalDuration,
                    $timeout, null, $maxConcurrentSegments,
                );
            } else {
                $this->clearCache();
                throw $e;
            }
        }

        $needsConcat = count($segmentPaths) > 1;
        $needsTransitions = $needsConcat && $this->hasAnyTransitions($clips);
        $needsAudio = ! empty($audioTracks);

        if (! $needsConcat && ! $needsAudio) {
            $finalPath = $segmentPaths[0];
        } elseif ($needsTransitions) {
            $finalPath = $this->applyTransitions(
                $clips, $segmentPaths, $width, $height, $fps, $codec,
                $audioTracks, $audioPath, $loopAudio, $audioVolume, $keepSourceAudio,
                $verbose, $tempFiles, $onProgress, $fps, $totalDuration, $timeout,
            );
        } else {
            $finalPath = $this->concatenateSimple($segmentPaths, $verbose, $tempFiles, $timeout);
        }

        if ($needsAudio && ! $needsTransitions) {
            $mixedPath = sys_get_temp_dir().'/fluentcut_mixed_'.self::randomName().'.mp4';
            $tempFiles[] = $mixedPath;
            $this->mixAudio($finalPath, $audioTracks, $mixedPath, $audioPath, $loopAudio, $audioVolume, $keepSourceAudio, $verbose, $onProgress, $fps, $totalDuration, $timeout);
            $finalPath = $mixedPath;
        }

        if ($finalPath !== $outputPath) {
            rename($finalPath, $outputPath);
        }

        if ($this->clearCacheAfterRender) {
            $this->clearCache();
        }

        return $this->buildResult($outputPath);
    }

    /**
     * @param  Clip[]  $clips
     * @param  string[]  $tempFiles
     * @param  callable(ProgressInfo): void|null  $onProgress
     * @return string[]
     *
     * @throws RenderException|RandomException
     */
    private function renderSegments(array $clips, int $width, int $height, int $fps, Codec $codec, bool $verbose, array &$tempFiles, ?callable $onProgress, int $clipFps, int $totalSegments, float $totalDuration, int $timeout, ?HardwareAccel $hardwareAccel, int $maxConcurrentSegments = 0): array
    {
        $segmentPaths = [];

        foreach ($clips as $index => $clip) {
            $cachedPath = $this->getCachedSegment($clip, $width, $height, $fps);
            if ($cachedPath !== null) {
                $tempPath = sys_get_temp_dir().'/fluentcut_seg_'.$index.'_'.self::randomName().'.mp4';
                $tempFiles[] = $tempPath;
                @copy($cachedPath, $tempPath);
                $segmentPaths[] = $tempPath;

                if ($verbose) {
                    error_log("[FluentCut] Segment {$index}: cache hit, using cached segment");
                }

                continue;
            }

            $job = $this->prepareSegmentJob($clip, $index, $width, $height, $fps, $codec, $hardwareAccel, $verbose, $tempFiles);
            $segmentPaths[] = $this->executeSegmentRender(
                $job['command'],
                $clip,
                $width,
                $height,
                $fps,
                $job['path'],
                $onProgress,
                $clipFps,
                $index,
                $totalSegments,
                $totalDuration,
                $timeout,
            );
        }

        return $segmentPaths;
    }

    /**
     * @param  string[]  $command
     *
     * @throws RenderException
     */
    private function executeSegmentRender(
        array $command,
        Clip $clip,
        int $width,
        int $height,
        int $fps,
        string $outputPath,
        ?callable $onProgress,
        int $clipFps,
        int $index,
        int $totalSegments,
        float $totalDuration,
        int $timeout
    ): string {
        $process = $onProgress !== null
            ? $this->ffmpeg->runWithProgress(
                command: $command,
                onProgress: $onProgress,
                totalDuration: $clip->duration,
                fps: $clipFps,
                segment: $index,
                totalSegments: $totalSegments,
                phase: "rendering segment {$index}",
                timeout: $this->estimateSegmentTimeout($clip, $timeout),
            )
            : $this->ffmpeg->run($command, $this->estimateSegmentTimeout($clip, $timeout));

        if (! $process->isSuccessful()) {
            throw RenderException::ffmpegError($process->getErrorOutput());
        }

        $this->saveCachedSegment($outputPath, $clip, $width, $height, $fps);
        $this->ffmpeg->invalidateProbeCache($outputPath);

        return $outputPath;
    }

    /**
     * @return string[]
     *
     * @throws RenderException|FFmpegNotFoundException
     */
    private function buildSegmentCommand(Clip $clip, int $width, int $height, int $fps, Codec $codec, string $outputPath, ?HardwareAccel $hardwareAccel = null): array
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
     * @param  string[]  &$tempFiles
     * @return array{path: string, command: string[]}
     *
     * @throws RenderException|RandomException|FFmpegNotFoundException
     */
    private function prepareSegmentJob(
        Clip $clip,
        int $index,
        int $width,
        int $height,
        int $fps,
        Codec $codec,
        ?HardwareAccel $hardwareAccel,
        bool $verbose,
        array &$tempFiles
    ): array {
        $segmentPath = sys_get_temp_dir().'/fluentcut_seg_'.$index.'_'.self::randomName().'.mp4';
        $tempFiles[] = $segmentPath;

        $command = $this->buildSegmentCommand($clip, $width, $height, $fps, $codec, $segmentPath, $hardwareAccel);

        if ($verbose) {
            error_log("[FluentCut] Segment {$index}: ".implode(' ', $command));
        }

        return ['path' => $segmentPath, 'command' => $command];
    }

    /**
     * @return string[]
     */
    private function collectClipFilters(Clip $clip, int $width, int $height, int $fps): array
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
    private function appendOverlaysFiltersAndOutput(array $command, Clip $clip, array $filters, int $width, int $height, int $fps, Codec $codec, string $outputPath, bool $useGpu, ?HardwareAccel $hardwareAccel): array
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
    private function buildOverlayFilterComplex(array $overlays, string $baseFilter, int $canvasW, int $canvasH): string
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
     * @param  string[]  $segmentPaths
     * @param  string[]  $tempFiles
     *
     * @throws RenderException|RandomException
     * @throws FFmpegNotFoundException
     */
    private function concatenateSimple(array $segmentPaths, bool $verbose, array &$tempFiles, int $timeout): string
    {
        $concatListPath = sys_get_temp_dir().'/fluentcut_concat_'.self::randomName().'.txt';
        $tempFiles[] = $concatListPath;

        $listContent = '';
        foreach ($segmentPaths as $path) {
            $listContent .= "file '".str_replace("'", "'\\''", $path)."'\n";
        }
        file_put_contents($concatListPath, $listContent);

        $finalPath = sys_get_temp_dir().'/fluentcut_concat_'.self::randomName().'.mp4';
        $tempFiles[] = $finalPath;

        $command = [
            $this->ffmpeg->getFFmpegPath(),
            '-f', 'concat', '-safe', '0',
            '-i', $concatListPath,
            '-c', 'copy',
            '-y', $finalPath,
        ];

        if ($verbose) {
            error_log('[FluentCut] Concat: '.implode(' ', $command));
        }

        $process = $this->ffmpeg->run($command, $this->resolveTimeout($timeout));
        if (! $process->isSuccessful()) {
            throw RenderException::ffmpegError($process->getErrorOutput());
        }

        return $finalPath;
    }

    /**
     * @param  Clip[]  $clips
     */
    private function hasAnyTransitions(array $clips): bool
    {
        foreach ($clips as $clip) {
            if ($clip->transition !== null && $clip->transition !== Transition::None) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Clip[]  $clips
     * @param  string[]  $segmentPaths
     * @param  string[]  $tempFiles
     * @param  callable(ProgressInfo): void|null  $onProgress
     *
     * @throws RenderException
     * @throws RandomException|FFmpegNotFoundException|JsonException
     */
    private function applyTransitions(
        array $clips,
        array $segmentPaths,
        int $width,
        int $height,
        int $fps,
        Codec $codec,
        array $audioTracks = [],
        ?string $audioPath = null,
        bool $loopAudio = false,
        ?float $audioVolume = null,
        bool $keepSourceAudio = true,
        bool $verbose = false,
        array &$tempFiles = [],
        ?callable $onProgress = null,
        int $clipFps = 30,
        float $totalDuration = 0.0,
        int $timeout = 0,
    ): string {
        $count = count($segmentPaths);
        $finalPath = sys_get_temp_dir().'/fluentcut_trans_'.self::randomName().'.mp4';
        $tempFiles[] = $finalPath;

        $durations = array_map(fn ($path) => $this->ffmpeg->getDuration($path) ?? 0.0, $segmentPaths);

        $command = [$this->ffmpeg->getFFmpegPath()];
        foreach ($segmentPaths as $path) {
            $command = [...$command, '-i', $path];
        }

        $filterParts = [];
        $cumulativeDuration = 0.0;
        $lastLabel = '[0:v]';

        for ($i = 1; $i < $count; $i++) {
            $cumulativeDuration += $durations[$i - 1];
            $isLast = $i === $count - 1;
            $outLabel = $isLast ? '[vout]' : "[v{$i}]";
            
            // Get transition from the current clip (clip at index $i)
            $clip = $clips[$i];
            $transition = $clip->transition ?? Transition::Fade;
            $transitionDuration = $clip->transitionDuration;
            
            // Skip transition if set to None
            if ($transition === Transition::None) {
                $transitionDuration = 0.0;
                $transition = Transition::Fade; // Use fade with 0 duration for hard cut
            }
            
            $xfadeType = $transition->toFFmpegXFilter();
            $offset = max(0, $cumulativeDuration - $transitionDuration);

            $filterParts[] = sprintf(
                '%s[%d:v]xfade=transition=%s:duration=%.3f:offset=%.3f%s',
                $lastLabel,
                $i,
                $xfadeType,
                $transitionDuration,
                $offset,
                $outLabel,
            );

            $cumulativeDuration -= $transitionDuration;
            $lastLabel = $outLabel;
        }

        $command = [...$command, '-filter_complex', implode(';', $filterParts)];
        $command = [...$command, '-map', '[vout]'];
        $command = [...$command, ...$codec->defaultOutputArgs(), '-y', $finalPath];

        if ($verbose) {
            error_log('[FluentCut] Transitions: '.implode(' ', $command));
        }

        $process = $this->ffmpeg->runWithProgress(
            command: $command,
            onProgress: $onProgress,
            totalDuration: $totalDuration,
            fps: $clipFps,
            segment: 0,
            totalSegments: 1,
            phase: 'applying transitions',
            timeout: $this->resolveTimeout($timeout),
        );

        if (! $process->isSuccessful()) {
            throw RenderException::ffmpegError($process->getErrorOutput());
        }

        $this->ffmpeg->invalidateProbeCache($finalPath);

        if (! empty($audioTracks)) {
            $mixedPath = sys_get_temp_dir().'/fluentcut_mixed_'.self::randomName().'.mp4';
            $tempFiles[] = $mixedPath;
            $this->mixAudio($finalPath, $audioTracks, $mixedPath, $audioPath, $loopAudio, $audioVolume, $keepSourceAudio, $verbose, $onProgress, $clipFps, $totalDuration, $timeout);

            return $mixedPath;
        }

        return $finalPath;
    }

    /**
     * @param  callable(ProgressInfo): void|null  $onProgress
     *
     * @throws RenderException
     * @throws FFmpegNotFoundException
     * @throws JsonException
     */
    private function mixAudio(
        string $videoPath,
        array $audioTracks,
        string $outputPath,
        ?string $legacyAudioPath = null,
        bool $legacyLoopAudio = false,
        ?float $legacyAudioVolume = null,
        bool $keepSourceAudio = true,
        bool $verbose = false,
        ?callable $onProgress = null,
        int $clipFps = 30,
        float $totalDuration = 0.0,
        int $timeout = 0,
    ): void {
        $command = [$this->ffmpeg->getFFmpegPath(), '-i', $videoPath];

        $filterParts = [];

        if (! empty($audioTracks)) {
            $inputIndex = 1;

            foreach ($audioTracks as $track) {
                $offset = $track['startAt'] ?? 0.0;
                $volume = $track['volume'] ?? 1.0;
                $loop = $track['loop'] ?? false;
                $endAt = $track['endAt'] ?? null;
                $fadeDuration = $track['fadeDuration'] ?? 0.5;

                $command[] = '-i';
                $command[] = $track['path'];

                $chain = sprintf('[%d:a]volume=%.6f', $inputIndex, $volume);

                if ($loop) {
                    $chain .= ',aloop=loop=-1:size=2147483647:start=0';
                }

                if ($offset > 0) {
                    $chain .= sprintf(',adelay=%dms', (int) ($offset * 1000));
                }

                if ($fadeDuration > 0) {
                    $actualStart = $offset;

                    $chain .= sprintf(',afade=t=in:st=%.3f:d=%.3f', $actualStart, min($fadeDuration, $actualStart));

                    if ($endAt !== null && $endAt > $fadeDuration) {
                        $chain .= sprintf(',afade=t=out:st=%.3f:d=%.3f', $endAt - $fadeDuration, $fadeDuration);
                    }
                }

                if ($endAt !== null && $endAt > 0) {
                    $chain .= sprintf(',atrim=end=%.6f', $endAt);
                }

                $chain .= sprintf('[a%d]', $inputIndex);
                $filterParts[] = $chain;

                $inputIndex++;
            }

            $audioLabels = [];
            for ($i = 1; $i < $inputIndex; $i++) {
                $audioLabels[] = "[a{$i}]";
            }

            $filterParts[] = implode('', $audioLabels).sprintf('amix=inputs=%d:duration=longest:dropout_transition=0[aout]', count($audioLabels));

            $command[] = '-filter_complex';
            $command[] = implode(';', $filterParts);
            $command[] = '-map';
            $command[] = '0:v:0';
            $command[] = '-map';
            $command[] = '[aout]';
        } else {
            $command[] = '-map';
            $command[] = '0:v:0';
            $command[] = '-map';
            $command[] = '0:a:0?';
        }

        $command = [...$command, '-c:v', 'copy', '-c:a', 'aac', '-shortest', '-y', $outputPath];

        if ($verbose) {
            error_log('[FluentCut] Audio mix: '.implode(' ', $command));
        }

        $process = $this->ffmpeg->runWithProgress(
            command: $command,
            onProgress: $onProgress,
            totalDuration: $totalDuration,
            fps: $clipFps,
            segment: 0,
            totalSegments: 1,
            phase: 'mixing audio',
            timeout: $this->resolveTimeout($timeout),
        );

        if (! $process->isSuccessful()) {
            throw RenderException::ffmpegError($process->getErrorOutput());
        }
    }

    /**
     * @param  Clip[]  $clips
     * @param  callable(ProgressInfo): void|null  $onProgress
     */
    private function renderGif(array $clips, int $width, int $height, int $fps, string $outputPath, ?Transition $transition, float $transitionDuration, int $timeout, bool $verbose, ?callable $onProgress, float $totalDuration): RenderResult
    {
        $tempFiles = [];
        try {
            $tempVideoPath = sys_get_temp_dir().'/fluentcut_temp_'.self::randomName().'.mp4';
            $tempFiles[] = $tempVideoPath;

            $videoResult = $this->renderVideo(
                $clips, $width, $height, $fps, $tempVideoPath,
                Codec::H264, Format::Mp4,
                $transition, $transitionDuration,
                [], null, false, null, false,
                $timeout, $verbose,
                $onProgress, $totalDuration,
            );

            if (! $videoResult->isSuccessful()) {
                return $videoResult;
            }

            $palettePath = sys_get_temp_dir().'/fluentcut_palette_'.self::randomName().'.png';
            $tempFiles[] = $palettePath;

            $process = $this->ffmpeg->run([
                $this->ffmpeg->getFFmpegPath(),
                '-i', $tempVideoPath,
                '-vf', "fps={$fps},scale={$width}:{$height}:flags=lanczos,palettegen",
                '-y', $palettePath,
            ], $this->resolveTimeout($timeout));

            if (! $process->isSuccessful()) {
                return RenderResult::failure('Failed to generate GIF palette: '.$process->getErrorOutput());
            }

            $process = $this->ffmpeg->runWithProgress(
                command: [
                    $this->ffmpeg->getFFmpegPath(),
                    '-i', $tempVideoPath,
                    '-i', $palettePath,
                    '-filter_complex', "fps={$fps},scale={$width}:{$height}:flags=lanczos[x];[x][1:v]paletteuse",
                    '-y', $outputPath,
                ],
                onProgress: $onProgress,
                totalDuration: $totalDuration,
                fps: $fps,
                segment: 0,
                totalSegments: 1,
                phase: 'generating GIF',
                timeout: $this->resolveTimeout($timeout),
            );

            if (! $process->isSuccessful()) {
                return RenderResult::failure('Failed to render GIF: '.$process->getErrorOutput());
            }

            if ($this->clearCacheAfterRender) {
                $this->clearCache();
            }

            return $this->buildResult($outputPath);
        } catch (Throwable $e) {
            return RenderResult::failure($e->getMessage());
        } finally {
            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    @unlink($tempFile);
                }
            }
        }
    }

    /**
     * @param  Clip[]  $clips
     * @return Clip[]
     *
     * @throws RenderException
     */
    private function resolveDurations(array $clips): array
    {
        foreach ($clips as $clip) {
            if ($clip->duration <= 0 && $clip->isVideo()) {
                try {
                    $duration = $this->ffmpeg->getDuration($clip->videoPath);
                } catch (FFmpegNotFoundException|JsonException $e) {
                    throw RenderException::failed($e->getMessage());
                }

                if ($duration !== null) {
                    $clip->duration = $duration;
                    if ($clip->start !== null) {
                        $clip->duration -= $clip->start;
                    }
                    if ($clip->end !== null) {
                        $clip->duration = $clip->end - ($clip->start ?? 0.0);
                    }
                }
            }
        }

        return $clips;
    }

    /**
     * @throws FFmpegNotFoundException
     * @throws JsonException
     */
    private function buildResult(string $outputPath): RenderResult
    {
        $info = $this->ffmpeg->probe($outputPath);
        $format = $info['format'] ?? [];

        $duration = isset($format['duration']) ? (float) $format['duration'] : 0.0;
        $fileSize = isset($format['size']) ? (int) $format['size'] : 0;
        $width = null;
        $height = null;

        foreach ($info['streams'] ?? [] as $stream) {
            if (($stream['codec_type'] ?? '') === 'video') {
                $width = (int) ($stream['width'] ?? 0);
                $height = (int) ($stream['height'] ?? 0);
                break;
            }
        }

        return RenderResult::success(
            outputPath: realpath($outputPath) ?: $outputPath,
            duration: $duration,
            width: $width ?? 0,
            height: $height ?? 0,
            format: pathinfo($outputPath, PATHINFO_EXTENSION),
            fileSize: $fileSize,
        );
    }

    private function estimateSegmentTimeout(Clip $clip, int $requestedTimeout): int
    {
        if ($requestedTimeout > 0) {
            $timeout = $requestedTimeout;
        } else {
            $duration = max(1.0, $clip->duration);
            $timeout = (int) ($duration * 120) + 120;
        }

        if ($clip->resizeMode === ResizeMode::ContainBlur && $clip->isImage()) {
            $duration = max(1.0, $clip->duration);
            $adaptive = (int) ($duration * 180) + 120;
            $timeout = max($timeout, $adaptive);
        }

        return $timeout;
    }

    private function resolveTimeout(int $requestedTimeout): ?int
    {
        if ($requestedTimeout > 0) {
            return $requestedTimeout;
        }

        return null;
    }

    public function setCacheEnabled(bool $enabled): void
    {
        $this->cacheEnabled = $enabled;
    }

    public function clearCache(): void
    {
        if (! is_dir($this->cacheDir)) {
            return;
        }

        foreach (glob($this->cacheDir.'/*') as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private function getCachedSegment(Clip $clip, int $width, int $height, int $fps): ?string
    {
        if (! $this->cacheEnabled) {
            return null;
        }

        $cacheKey = $clip->cacheKey($width, $height, $fps);
        $cachedPath = $this->cacheDir.'/'.$cacheKey.'.mp4';

        if (file_exists($cachedPath)) {
            return $cachedPath;
        }

        return null;
    }

    private function saveCachedSegment(string $sourcePath, Clip $clip, int $width, int $height, int $fps): void
    {
        if (! $this->cacheEnabled) {
            return;
        }

        $cacheKey = $clip->cacheKey($width, $height, $fps);
        $cachedPath = $this->cacheDir.'/'.$cacheKey.'.mp4';

        if (! file_exists($cachedPath)) {
            @copy($sourcePath, $cachedPath);
        }
    }

    private function hasAudioClips(
        bool $hasAudioClips,
        array $command,
        array $audioLabels,
        int $audioClipCount,
        bool $sourceHasAudio
    ): array {
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
