<?php

declare(strict_types=1);

namespace B7s\FluentCut\Enums;

enum HardwareAccel: string
{
    case None = 'none';
    case Auto = 'auto';
    case Nvenc = 'nvenc';
    case Qsv = 'qsv';
    case Vaapi = 'vaapi';
    case VideoToolbox = 'videotoolbox';

    public function description(): string
    {
        return match ($this) {
            self::None => 'CPU only (software encoding)',
            self::Auto => 'Auto-detect best available GPU encoder',
            self::Nvenc => 'NVIDIA NVENC (CUDA)',
            self::Qsv => 'Intel Quick Sync Video',
            self::Vaapi => 'VA-API (Linux, AMD/Intel)',
            self::VideoToolbox => 'Apple VideoToolbox (macOS)',
        };
    }

    public function isGpu(): bool
    {
        return $this !== self::None;
    }

    public function toGpuEncoder(Codec $codec): ?string
    {
        return match ($this) {
            self::Nvenc => match ($codec) {
                Codec::H264 => 'h264_nvenc',
                Codec::H265 => 'hevc_nvenc',
                default => null,
            },
            self::Qsv => match ($codec) {
                Codec::H264 => 'h264_qsv',
                Codec::H265 => 'hevc_qsv',
                default => null,
            },
            self::Vaapi => match ($codec) {
                Codec::H264 => 'h264_vaapi',
                Codec::H265 => 'hevc_vaapi',
                default => null,
            },
            self::VideoToolbox => match ($codec) {
                Codec::H264 => 'h264_videotoolbox',
                Codec::H265 => 'hevc_videotoolbox',
                default => null,
            },
            default => null,
        };
    }

    /**
     * @return string[]
     */
    public function gpuOutputArgs(Codec $codec): array
    {
        $encoder = $this->toGpuEncoder($codec);
        if ($encoder === null) {
            return [];
        }

        return match ($this) {
            self::Nvenc => ['-c:v', $encoder, '-preset', 'p4', '-cq', '23', '-pix_fmt', 'yuv420p'],
            self::Qsv => ['-c:v', $encoder, '-preset', 'medium', '-global_quality', '23'],
            self::Vaapi => ['-c:v', $encoder, '-qp', '23'],
            self::VideoToolbox => ['-c:v', $encoder, '-q:v', '65'],
            default => [],
        };
    }

    /**
     * @return string[]
     */
    public function hwAccelInputArgs(): array
    {
        return match ($this) {
            self::Nvenc => ['-hwaccel', 'cuda', '-hwaccel_output_format', 'cuda'],
            self::Qsv => ['-hwaccel', 'qsv'],
            self::Vaapi => ['-hwaccel', 'vaapi', '-vaapi_device', '/dev/dri/renderD128'],
            self::VideoToolbox => ['-hwaccel', 'videotoolbox'],
            default => [],
        };
    }

    public function supportsCodec(Codec $codec): bool
    {
        return $this->toGpuEncoder($codec) !== null;
    }

    public function supportsHwaccelInput(): bool
    {
        return match ($this) {
            self::Nvenc => $this->checkNvencDevice(),
            self::Vaapi => $this->checkVaapiDevice(),
            self::VideoToolbox => true,
            self::Qsv => $this->checkQsvDevice(),
            default => false,
        };
    }

    private function checkNvencDevice(): bool
    {
        return is_dir('/usr/local/cuda') || file_exists('/usr/bin/nvidia-smi');
    }

    private function checkVaapiDevice(): bool
    {
        $devices = glob('/dev/dri/renderD*');
        return !empty($devices);
    }

    private function checkQsvDevice(): bool
    {
        return false;
    }
}
