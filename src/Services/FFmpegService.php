<?php

declare(strict_types=1);

namespace B7s\FluentCut\Services;

use B7s\FluentCut\Enums\Codec;
use B7s\FluentCut\Enums\HardwareAccel;
use B7s\FluentCut\Exceptions\FFmpegNotFoundException;
use B7s\FluentCut\Results\ProgressInfo;
use B7s\FluentCut\Support\Platform;
use Symfony\Component\Process\Process;

use function explode;
use function is_array;
use function json_decode;
use function microtime;
use function preg_match;
use function preg_match_all;
use function realpath;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function trim;
use function usleep;

final class FFmpegService
{
    /** @var array<string, array<string, mixed>> */
    private array $probeCache = [];

    private ?string $ffmpegPath = null;
    private ?string $ffprobePath = null;
    private readonly int $timeout;
    private ?HardwareAccel $detectedAccel = null;
    /** @var array<string, true>|null */
    private ?array $encodersCache = null;

    public function __construct(?int $timeout = null)
    {
        $this->timeout = $timeout ?? 0;
    }

    public function getDefaultTimeout(): int
    {
        return $this->timeout;
    }

    public function getFFmpegPath(): string
    {
        if ($this->ffmpegPath !== null) {
            return $this->ffmpegPath;
        }

        return $this->ffmpegPath = $this->findBinary(Platform::ffmpegBinaryName(), 'FFmpeg');
    }

    public function getFFprobePath(): string
    {
        if ($this->ffprobePath !== null) {
            return $this->ffprobePath;
        }

        return $this->ffprobePath = $this->findBinary(Platform::ffprobeBinaryName(), 'FFprobe');
    }

    /**
     * @return array<string, mixed>
     */
    public function probe(string $path): array
    {
        $realPath = realpath($path) ?: $path;

        if (isset($this->probeCache[$realPath])) {
            return $this->probeCache[$realPath];
        }

        $command = [
            $this->getFFprobePath(),
            '-v', 'quiet',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            $path,
        ];

        $process = new Process($command, timeout: $this->timeout);
        $process->run();

        if (!$process->isSuccessful()) {
            return [];
        }

        $data = json_decode($process->getOutput(), true);
        $result = is_array($data) ? $data : [];

        $this->probeCache[$realPath] = $result;

        return $result;
    }

    public function invalidateProbeCache(?string $path = null): void
    {
        if ($path !== null) {
            $realPath = realpath($path) ?: $path;
            unset($this->probeCache[$realPath]);
        } else {
            $this->probeCache = [];
        }
    }

    public function getDuration(string $path): ?float
    {
        $info = $this->probe($path);
        $format = $info['format'] ?? [];

        if (isset($format['duration'])) {
            return (float) $format['duration'];
        }

        return null;
    }

    /**
     * @return array{width: int, height: int}|null
     */
    public function getVideoDimensions(string $path): ?array
    {
        $info = $this->probe($path);
        $streams = $info['streams'] ?? [];

        foreach ($streams as $stream) {
            if (($stream['codec_type'] ?? '') === 'video') {
                return [
                    'width' => (int) ($stream['width'] ?? 0),
                    'height' => (int) ($stream['height'] ?? 0),
                ];
            }
        }

        return null;
    }

    public function getVideoFps(string $path): ?float
    {
        $info = $this->probe($path);
        $streams = $info['streams'] ?? [];

        foreach ($streams as $stream) {
            if (($stream['codec_type'] ?? '') === 'video') {
                $rFrameRate = $stream['r_frame_rate'] ?? null;

                if ($rFrameRate !== null && str_contains($rFrameRate, '/')) {
                    [$num, $den] = explode('/', $rFrameRate);
                    if ((int) $den > 0) {
                        return (float) $num / (float) $den;
                    }
                }

                return null;
            }
        }

        return null;
    }

    public function hasAudioStream(string $path): bool
    {
        $info = $this->probe($path);
        $streams = $info['streams'] ?? [];

        foreach ($streams as $stream) {
            if (($stream['codec_type'] ?? '') === 'audio') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string[] $command
     */
    public function run(array $command, ?int $timeout = null): Process
    {
        $process = $this->createProcess($command, $timeout);
        $process->run();

        return $process;
    }

    /**
     * @param string[] $command
     * @param callable(\B7s\FluentCut\Results\ProgressInfo): void|null $onProgress
     */
    public function runWithProgress(array $command, ?callable $onProgress = null, float $totalDuration = 0.0, int $fps = 30, int $segment = 0, int $totalSegments = 1, string $phase = 'rendering', ?int $timeout = null): Process
    {
        $process = $this->createProcess($command, $timeout);

        if ($onProgress === null) {
            $process->run();

            return $process;
        }

        $process->start();

        $lastEmit = 0.0;

        while ($process->isRunning()) {
            $stderr = $process->getIncrementalErrorOutput();

            if ($stderr !== '') {
                $progress = $this->parseProgress($stderr, $totalDuration, $fps, $segment, $totalSegments, $phase);
                if ($progress !== null) {
                    $now = microtime(true);
                    if ($now - $lastEmit >= 0.25) {
                        $onProgress($progress);
                        $lastEmit = $now;
                    }
                }
            }

            usleep(50000);
        }

        $stderr = $process->getIncrementalErrorOutput();
        if ($stderr !== '') {
            $progress = $this->parseProgress($stderr, $totalDuration, $fps, $segment, $totalSegments, $phase);
            if ($progress !== null && $progress->percentage < 100.0) {
                $onProgress(new \B7s\FluentCut\Results\ProgressInfo(
                    frame: $progress->frame,
                    currentTime: $progress->currentTime,
                    bitrate: $progress->bitrate,
                    speed: $progress->speed,
                    totalFrames: $progress->totalFrames,
                    totalDuration: $totalDuration,
                    percentage: 100.0,
                    segment: $segment,
                    totalSegments: $totalSegments,
                    phase: $phase,
                ));
            }
        }

        return $process;
    }

    /**
     * @param string[] $command
     */
    public function runAsync(array $command, ?int $timeout = null): Process
    {
        $process = $this->createProcess($command, $timeout);
        $process->start();

        return $process;
    }

    /**
     * @param string[] $command
     */
    private function createProcess(array $command, ?int $timeout = null): Process
    {
        $resolvedTimeout = $timeout ?? ($this->timeout > 0 ? $this->timeout : null);

        return new Process($command, timeout: $resolvedTimeout);
    }

    private function parseProgress(string $stderr, float $totalDuration, int $fps, int $segment, int $totalSegments, string $phase): ?ProgressInfo
    {
        $lines = explode("\r", $stderr);
        $lastValid = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (!str_starts_with($line, 'frame=')) {
                continue;
            }

            $parsed = $this->parseFFmpegLine($line);
            if ($parsed !== null) {
                $lastValid = $parsed;
            }
        }

        if ($lastValid === null) {
            return null;
        }

        $totalFrames = $totalDuration > 0 ? (int) round($totalDuration * $fps) : 0;
        $percentage = 0.0;

        if ($totalDuration > 0 && $lastValid['time'] > 0) {
            $percentage = min(100.0, ($lastValid['time'] / $totalDuration) * 100);
        } elseif ($totalFrames > 0 && $lastValid['frame'] > 0) {
            $percentage = min(100.0, ($lastValid['frame'] / $totalFrames) * 100);
        }

        return new ProgressInfo(
            frame: $lastValid['frame'],
            currentTime: $lastValid['time'],
            bitrate: $lastValid['bitrate'],
            speed: $lastValid['speed'],
            totalFrames: $totalFrames,
            totalDuration: $totalDuration,
            percentage: $percentage,
            segment: $segment,
            totalSegments: $totalSegments,
            phase: $phase,
        );
    }

    /**
     * @return array{frame: int, time: float, bitrate: float, speed: float}|null
     */
    private function parseFFmpegLine(string $line): ?array
    {
        if (!str_contains($line, '=')) {
            return null;
        }

        $data = [];
        preg_match_all('/(\w+)=\s*([\d.:\/\s\w]+)/i', $line, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $data[strtolower($match[1])] = trim($match[2]);
        }

        $frame = (int) ($data['frame'] ?? 0);
        $time = $this->parseTimeToSeconds($data['lct'] ?? $data['time'] ?? '0');
        $bitrate = $this->parseBitrate($data['bitrate'] ?? '0');
        $speed = (float) ($data['speed'] ?? '0');

        if ($frame === 0 && $time === 0.0) {
            return null;
        }

        return ['frame' => $frame, 'time' => $time, 'bitrate' => $bitrate, 'speed' => $speed];
    }

    private function parseTimeToSeconds(string $time): float
    {
        $time = trim($time);
        $parts = explode(':', $time);

        if (count($parts) === 3) {
            return (float) $parts[0] * 3600 + (float) $parts[1] * 60 + (float) $parts[2];
        }

        if (count($parts) === 2) {
            return (float) $parts[0] * 60 + (float) $parts[1];
        }

        return (float) $time;
    }

    private function parseBitrate(string $bitrate): float
    {
        $bitrate = trim($bitrate);

        if (str_contains($bitrate, 'Mbps')) {
            return (float) $bitrate * 1000;
        }

        if (str_contains($bitrate, 'kbps')) {
            return (float) $bitrate;
        }

        return (float) $bitrate;
    }

    public function checkRequirements(): bool
    {
        try {
            $this->getFFmpegPath();
            $this->getFFprobePath();

            return true;
        } catch (FFmpegNotFoundException) {
            return false;
        }
    }

    public function isEncoderAvailable(string $encoder): bool
    {
        $encoders = $this->getAvailableEncoders();

        return isset($encoders[$encoder]);
    }

    public function detectHardwareAccel(): ?HardwareAccel
    {
        if ($this->detectedAccel !== null) {
            return $this->detectedAccel;
        }

        $candidates = [];

        if (Platform::isMacOS()) {
            $candidates = [HardwareAccel::VideoToolbox];
        } elseif (Platform::isWindows()) {
            $candidates = [HardwareAccel::Nvenc, HardwareAccel::Qsv];
        } else {
            $candidates = [HardwareAccel::Vaapi, HardwareAccel::Nvenc, HardwareAccel::Qsv];
        }

        foreach ($candidates as $accel) {
            $encoder = $accel->toGpuEncoder(Codec::H264);
            if ($encoder !== null && $this->isEncoderAvailable($encoder) && $accel->supportsHwaccelInput()) {
                return $this->detectedAccel = $accel;
            }
        }

        return $this->detectedAccel = null;
    }

    public function resolveHardwareAccel(?HardwareAccel $requested): ?HardwareAccel
    {
        if ($requested === null || $requested === HardwareAccel::None) {
            return null;
        }

        if ($requested === HardwareAccel::Auto) {
            return $this->detectHardwareAccel();
        }

        $encoder = $requested->toGpuEncoder(Codec::H264);
        if ($encoder !== null && $this->isEncoderAvailable($encoder)) {
            return $requested;
        }

        return null;
    }

    /**
     * @return array<string, true>
     */
    private function getAvailableEncoders(): array
    {
        if ($this->encodersCache !== null) {
            return $this->encodersCache;
        }

        $process = new Process(
            [$this->getFFmpegPath(), '-hide_banner', '-encoders'],
            timeout: 10,
        );
        $process->run();

        $this->encodersCache = [];

        if (!$process->isSuccessful()) {
            return $this->encodersCache;
        }

        $output = $process->getOutput();
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (!preg_match('/^V\.+/', $trimmed)) {
                continue;
            }

            $parts = preg_split('/\s+/', $trimmed);
            if (isset($parts[1]) && preg_match('/^[a-z0-9_]+$/i', $parts[1])) {
                $this->encodersCache[$parts[1]] = true;
            }
        }

        return $this->encodersCache;
    }

    private function findBinary(string $name, string $label): string
    {
        $candidates = [
            $name,
            '/usr/bin/' . $name,
            '/usr/local/bin/' . $name,
        ];

        if (Platform::isWindows()) {
            $candidates[] = 'C:\\ffmpeg\\bin\\' . $name;
        } elseif (Platform::isMacOS()) {
            $candidates[] = '/opt/homebrew/bin/' . $name;
        }

        foreach ($candidates as $candidate) {
            $process = new Process([$candidate, '-version'], timeout: 10);

            try {
                $process->run();
                if ($process->isSuccessful()) {
                    return $candidate;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        if ($label === 'FFmpeg') {
            throw FFmpegNotFoundException::ffmpegNotFound();
        }

        throw FFmpegNotFoundException::ffprobeNotFound();
    }
}
