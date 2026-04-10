<?php

declare(strict_types=1);

namespace B7s\FluentCut;

use B7s\FluentCut\Enums\Codec;
use B7s\FluentCut\Enums\HardwareAccel;
use B7s\FluentCut\Enums\ResizeMode;
use B7s\FluentCut\Enums\Transition;
use B7s\FluentCut\Enums\VideoEffect;
use B7s\FluentCut\Exceptions\RenderException;
use B7s\FluentCut\Results\ProgressInfo;
use B7s\FluentCut\Results\RenderResult;
use B7s\FluentCut\Services\CompositorService;
use B7s\FluentCut\Services\FFmpegService;
use B7s\FluentCut\Support\Clip;
use B7s\FluentCut\Support\ImageOverlay;
use B7s\FluentCut\Support\Position;
use B7s\FluentCut\Support\TextOverlay;

use JsonException;
use Throwable;
use function array_key_last;
use function dirname;
use function file_exists;
use function is_dir;
use function is_file;
use function max;
use function mkdir;

/**
 * FluentCut - Fluent PHP API for programmatic video editing powered by FFmpeg.
 *
 * @example
 * // Slideshow from images
 * FluentCut::make()
 *     ->fullHd()
 *     ->addImage('slide1.jpg', duration: 3)
 *     ->addImage('slide2.jpg', duration: 2)
 *     ->addText('Title', x: 'center', y: '10%', fontSize: 48)
 *     ->withAudio('music.mp3')
 *     ->saveTo('slideshow.mp4')
 *     ->render();
 *
 * // Edit a video: cut segments, insert images between
 * FluentCut::make()
 *     ->fullHd()
 *     ->addVideo('intro.mp4')
 *     ->addImage('slide.jpg', duration: 5)
 *     ->addText('Chapter 1', x: 'center', y: 'center')
 *     ->addVideo('outro.mp4', start: 0, end: 10)
 *     ->transition(Transition::Fade, duration: 0.5)
 *     ->saveTo('output.mp4')
 *     ->render();
 *
 * // Resize existing video
 * FluentCut::make()
 *     ->fromVideo('input.mp4')
 *     ->resize(1280, 720)
 *     ->saveTo('output.mp4')
 *     ->render();
 */
class FluentCut
{
    /** @var Clip[] */
    private array $clips = [];

    private int $width;
    private int $height;
    private int $fps;
    private ?string $outputPath = null;
    private ?Codec $codec = null;
    private ?Transition $transition = null;
    private float $transitionDuration = 0.5;
    private ?string $audioPath = null;
    private bool $loopAudio = false;
    private ?float $audioVolume = null;
    private bool $keepSourceAudio = true;
    private ResizeMode $resizeMode;
    private int $timeout;
    private bool $verbose;
    private bool $cacheEnabled;
    private ?HardwareAccel $hardwareAccel = null;
    private bool $forceCpu = false;
    private int $maxConcurrentSegments = 0;
    /** @var callable(ProgressInfo): void|null */
    private $onProgress = null;

    private readonly FFmpegService $ffmpegService;
    private readonly CompositorService $compositor;

    private static ?FFmpegService $sharedFFmpeg = null;

    public function __construct()
    {
        $this->width = (int) Config::get('default_width', 1920);
        $this->height = (int) Config::get('default_height', 1080);
        $this->fps = (int) Config::get('default_fps', 30);
        $this->timeout = (int) Config::get('timeout', 0);
        $this->verbose = (bool) Config::get('verbose', false);
        $this->cacheEnabled = (bool) Config::get('cache_enabled', true);
        $this->resizeMode = ResizeMode::ContainBlur;

        $this->ffmpegService = new FFmpegService($this->timeout);
        $this->compositor = new CompositorService(
            $this->ffmpegService,
            $this->cacheEnabled,
            Config::get('cache_dir'),
            (bool) Config::get('clear_cache_after_render', true),
        );
    }

    public static function make(): self
    {
        return new self();
    }

    // =========================================================================
    // CANVAS / DIMENSIONS
    // =========================================================================

    public function canvas(int $width, int $height): self
    {
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    public function hd(): self
    {
        return $this->canvas(1280, 720);
    }

    public function fullHd(): self
    {
        return $this->canvas(1920, 1080);
    }

    public function fourK(): self
    {
        return $this->canvas(3840, 2160);
    }

    public function vertical(): self
    {
        return $this->canvas(1080, 1920);
    }

    public function square(): self
    {
        return $this->canvas(1080, 1080);
    }

    public function fps(int $fps): self
    {
        $this->fps = max(1, $fps);

        return $this;
    }

    public function resizeMode(ResizeMode $mode): self
    {
        $this->resizeMode = $mode;

        return $this;
    }

    // =========================================================================
    // ADD VIDEO CLIPS
    // =========================================================================

    /**
     * @throws RenderException
     */
    public function addVideo(string $path, ?float $start = null, ?float $end = null, VideoEffect|array|null $effect = null): self
    {
        $this->assertFileExists($path);

        $clip = Clip::fromVideo($path, $start, $end);
        $clip->resizeMode = $this->resizeMode;
        $clip->effects = self::normalizeEffects($effect);
        $this->clips[] = $clip;

        return $this;
    }

    /**
     * @throws RenderException
     */
    public function fromVideo(string $path, ?float $start = null, ?float $end = null, VideoEffect|array|null $effect = null): self
    {
        return $this->addVideo($path, $start, $end, $effect);
    }

    // =========================================================================
    // ADD IMAGE CLIPS
    // =========================================================================

    /**
     * @throws RenderException
     */
    public function addImage(string $path, float $duration = 1.0, VideoEffect|array|null $effect = null): self
    {
        $this->assertFileExists($path);

        $clip = Clip::fromImage($path, $duration);
        $clip->resizeMode = $this->resizeMode;
        $clip->effects = self::normalizeEffects($effect);
        $this->clips[] = $clip;

        return $this;
    }

    /**
     * @param string[] $paths
     * @throws RenderException
     */
    public function addImages(array $paths, float $duration = 1.0, VideoEffect|array|null $effect = null): self
    {
        foreach ($paths as $path) {
            $this->addImage($path, $duration, $effect);
        }

        return $this;
    }

    // =========================================================================
    // ADD COLOR / BACKGROUND CLIPS
    // =========================================================================

    /**
     * @throws RenderException
     */
    public function addColor(string $color, float $duration = 1.0, VideoEffect|array|null $effect = null): self
    {
        $clip = Clip::fromColor($color, $duration);
        $clip->effects = self::normalizeEffects($effect);
        $this->clips[] = $clip;

        return $this;
    }

    /**
     * @throws RenderException
     */
    public function addBlack(float $duration = 1.0): self
    {
        return $this->addColor('black', $duration);
    }

    /**
     * @throws RenderException
     */
    public function addWhite(float $duration = 1.0): self
    {
        return $this->addColor('white', $duration);
    }

    // =========================================================================
    // TEXT OVERLAYS (applied to the last clip)
    // =========================================================================

    /**
     * Add a text overlay to the most recently added clip.
     * @throws RenderException
     */
    public function addText(
        string $text,
        int|string $x = 'center',
        int|string $y = 'center',
        int $fontSize = 32,
        string $fontColor = 'white',
        ?string $fontFile = null,
        int $borderWidth = 0,
        string $borderColor = 'black',
        int $shadowX = 0,
        int $shadowY = 0,
        string $shadowColor = 'black@0.5',
        float $start = 0.0,
        ?float $end = null,
    ): self {
        if ($fontFile !== null) {
            $this->assertFileExists($fontFile);
        }

        $clip = $this->lastClip();

        $clip->textOverlays[] = new TextOverlay(
            text: $text,
            position: Position::parse($x, $y),
            fontSize: $fontSize,
            fontColor: $fontColor,
            fontFile: $fontFile,
            borderWidth: $borderWidth,
            borderColor: $borderColor,
            shadowX: $shadowX,
            shadowY: $shadowY,
            shadowColor: $shadowColor,
            start: $start,
            end: $end,
        );

        return $this;
    }

    

    // =========================================================================
    // VIDEO EFFECTS (applied to the last clip)
    // =========================================================================

    /**
     * Apply one or more visual effects to the most recently added clip.
     *
     * @param VideoEffect ...$effects One or more effects to apply (duplicates removed)
     * @throws RenderException
     */
    public function effect(VideoEffect ...$effects): self
    {
        $clip = $this->lastClip();
        $merged = array_unique([...$clip->effects, ...$effects], SORT_REGULAR);
        $clip->effects = array_values(array_filter($merged, static fn (VideoEffect $e) => $e !== VideoEffect::None));

        return $this;
    }

    // =========================================================================
    // IMAGE OVERLAYS (applied to the last clip)
    // =========================================================================

    /**
     * Overlay an image on top of the most recently added clip.
     * @throws RenderException
     */
    public function overlayImage(
        string $path,
        int|string $x = 0,
        int|string $y = 0,
        ?int $width = null,
        ?int $height = null,
        float $start = 0.0,
        ?float $end = null,
    ): self {
        $this->assertFileExists($path);

        $clip = $this->lastClip();

        $clip->imageOverlays[] = new ImageOverlay(
            path: $path,
            position: Position::parse($x, $y),
            width: $width,
            height: $height,
            start: $start,
            end: $end,
        );

        return $this;
    }

    // =========================================================================
    // AUDIO
    // =========================================================================

    /**
     * @throws RenderException
     */
    public function withAudio(string $path, bool $loop = false, ?float $volume = null): self
    {
        $this->assertFileExists($path);

        $this->audioPath = $path;
        $this->loopAudio = $loop;
        $this->audioVolume = $volume;

        return $this;
    }

    public function keepSourceAudio(bool $keep = true): self
    {
        $this->keepSourceAudio = $keep;

        return $this;
    }

    public function audioVolume(float $volume): self
    {
        $this->audioVolume = max(0, $volume);

        return $this;
    }

    /**
     * @throws RenderException
     */
    public function addAudioToClip(string $path): self
    {
        $this->assertFileExists($path);

        $clip = $this->lastClip();
        $clip->audioPaths[] = $path;

        return $this;
    }

    // =========================================================================
    // TRANSITIONS
    // =========================================================================

    public function transition(Transition $type, float $duration = 0.5): self
    {
        $this->transition = $type;
        $this->transitionDuration = max(0, $duration);

        return $this;
    }

    public function fade(float $duration = 0.5): self
    {
        return $this->transition(Transition::Fade, $duration);
    }

    public function fadeThroughBlack(float $duration = 0.5): self
    {
        return $this->transition(Transition::FadeBlack, $duration);
    }

    public function noTransition(): self
    {
        $this->transition = null;

        return $this;
    }

    // =========================================================================
    // OUTPUT
    // =========================================================================

    public function saveTo(string $path): self
    {
        $this->outputPath = $path;

        return $this;
    }

    public function output(string $path): self
    {
        return $this->saveTo($path);
    }

    public function codec(Codec $codec): self
    {
        $this->codec = $codec;

        return $this;
    }

    public function resize(int $width, int $height): self
    {
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    // =========================================================================
    // PROCESS CONFIG
    // =========================================================================

    public function timeout(int $seconds): self
    {
        $this->timeout = max(0, $seconds);

        return $this;
    }

    public function verbose(bool $verbose = true): self
    {
        $this->verbose = $verbose;

        return $this;
    }

    public function useCache(bool $enabled = true): self
    {
        $this->cacheEnabled = $enabled;
        $this->compositor->setCacheEnabled($enabled);

        return $this;
    }

    public function clearCache(): self
    {
        $this->compositor->clearCache();

        return $this;
    }

    /**
     * Enable GPU-accelerated encoding when available.
     *
     * Uses a hybrid approach: GPU encodes individual segments in parallel,
     * CPU handles the xfade transition pass (FFmpeg's xfade is CPU-only).
     *
     * @param HardwareAccel|null $accel Specific backend, or null to auto-detect
     */
    public function useGpu(?HardwareAccel $accel = null): self
    {
        $this->hardwareAccel = $accel ?? HardwareAccel::Auto;
        $this->forceCpu = false;

        return $this;
    }

    /**
     * Force CPU-only encoding, disabling any GPU acceleration.
     */
    public function useCpu(): self
    {
        $this->hardwareAccel = null;
        $this->forceCpu = true;

        return $this;
    }

    /**
     * Set a callback to monitor rendering progress in real time.
     *
     * The callback receives a ProgressInfo object with:
     * - frame, currentTime, bitrate, speed
     * - percentage (0-100), segment/totalSegments
     * - phase (e.g. "rendering segment 2", "mixing audio")
     *
     * @param callable(ProgressInfo): void $callback
     */
    public function onProgress(callable $callback): self
    {
        $this->onProgress = $callback;

        return $this;
    }

    /**
     * Limit the number of segments rendered concurrently.
     *
     * When set to 0 (default), uses auto-detection based on CPU cores.
     * Lower values reduce memory usage; higher values maximize parallelism.
     *
     * Note: Progress monitoring (onProgress) forces sequential rendering.
     */
    public function maxConcurrentSegments(int $max): self
    {
        $this->maxConcurrentSegments = max(0, $max);

        return $this;
    }

    // =========================================================================
    // PRESETS
    // =========================================================================

    public function forSlideshow(): self
    {
        return $this
            ->fullHd()
            ->fps(30)
            ->transition(Transition::Fade, 0.5)
            ->resizeMode(ResizeMode::ContainBlur);
    }

    public function forPresentation(): self
    {
        return $this
            ->fullHd()
            ->fps(24)
            ->resizeMode(ResizeMode::Contain);
    }

    public function forSocialMedia(): self
    {
        return $this
            ->vertical()
            ->fps(30)
            ->resizeMode(ResizeMode::Cover);
    }

    public function forGif(): self
    {
        return $this
            ->canvas(480, 270)
            ->fps(10)
            ->resizeMode(ResizeMode::Cover);
    }

    public function forWeb(): self
    {
        return $this
            ->fullHd()
            ->fps(30)
            ->codec(Codec::H264)
            ->resizeMode(ResizeMode::ContainBlur);
    }

    // =========================================================================
    // RENDER (terminal operation)
    // =========================================================================

    /**
     * @throws Throwable
     */
    public function render(): RenderResult
    {
        if (empty($this->clips)) {
            return RenderResult::failure('No clips added. Use addVideo(), addImage(), addColor(), or fromVideo() first.');
        }

        if ($this->outputPath === null) {
            return RenderResult::failure('No output path specified. Use saveTo() before render().');
        }

        $outputDir = dirname($this->outputPath);
        if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
            return RenderResult::failure("Cannot create output directory: {$outputDir}");
        }

        return $this->compositor->render(
            clips: $this->clips,
            width: $this->width,
            height: $this->height,
            fps: $this->fps,
            outputPath: $this->outputPath,
            codec: $this->codec,
            transition: $this->transition,
            transitionDuration: $this->transitionDuration,
            audioPath: $this->audioPath,
            loopAudio: $this->loopAudio,
            audioVolume: $this->audioVolume,
            keepSourceAudio: $this->keepSourceAudio,
            timeout: $this->timeout,
            verbose: $this->verbose,
            onProgress: $this->onProgress,
            hardwareAccel: $this->hardwareAccel,
            maxConcurrentSegments: $this->maxConcurrentSegments,
            forceCpu: $this->forceCpu,
        );
    }

    // =========================================================================
    // INFO / UTILITIES
    // =========================================================================

    /**
     * Probe a media file and return its metadata.
     *
     * @return array<string, mixed>
     *
     * @throws Exceptions\FFmpegNotFoundException|JsonException
     */
    public static function probe(string $path): array
    {
        return self::sharedFFmpegService()->probe($path);
    }

    public static function getDuration(string $path): ?float
    {
        return self::sharedFFmpegService()->getDuration($path);
    }

    /**
     * @return array{width: int, height: int}|null
     */
    public static function getDimensions(string $path): ?array
    {
        return self::sharedFFmpegService()->getVideoDimensions($path);
    }

    public static function checkRequirements(): bool
    {
        return self::sharedFFmpegService()->checkRequirements();
    }

    // =========================================================================
    // INTERNAL
    // =========================================================================

    /**
     * @throws RenderException
     */
    private function lastClip(): Clip
    {
        if (empty($this->clips)) {
            throw RenderException::noClips();
        }

        return $this->clips[array_key_last($this->clips)];
    }

    /**
     * @throws RenderException
     */
    private function assertFileExists(string $path): void
    {
        if (!file_exists($path)) {
            throw RenderException::fileNotFound($path);
        }
    }

    private static function sharedFFmpegService(): FFmpegService
    {
        return self::$sharedFFmpeg ??= new FFmpegService();
    }

    /**
     * @return VideoEffect[]
     */
    private static function normalizeEffects(VideoEffect|array|null $effects): array
    {
        if ($effects === null) {
            return [];
        }

        if ($effects instanceof VideoEffect) {
            $effects = [$effects];
        }

        $unique = array_unique($effects, SORT_REGULAR);

        return array_values(array_filter($unique, static fn (VideoEffect $e) => $e !== VideoEffect::None));
    }
}
