<?php

declare(strict_types=1);

namespace B7s\FluentCut\Tests\Unit;

use B7s\FluentCut\Enums\Codec;
use B7s\FluentCut\Enums\Format;
use B7s\FluentCut\Enums\HardwareAccel;
use B7s\FluentCut\Enums\ResizeMode;
use B7s\FluentCut\Enums\Transition;
use B7s\FluentCut\Exceptions\RenderException;
use B7s\FluentCut\FluentCut;
use B7s\FluentCut\Support\Clip;
use B7s\FluentCut\Support\Position;
use B7s\FluentCut\Support\TextOverlay;

describe('FluentCut Builder', function () {
    it('creates instance via make()', function () {
        $fc = FluentCut::make();
        expect($fc)->toBeInstanceOf(FluentCut::class);
    });

    it('sets canvas dimensions', function () {
        expect(FluentCut::make()->canvas(800, 600))->toBeInstanceOf(FluentCut::class);
    });

    it('sets dimension presets', function () {
        expect(FluentCut::make()->hd())->toBeInstanceOf(FluentCut::class);
        expect(FluentCut::make()->fullHd())->toBeInstanceOf(FluentCut::class);
        expect(FluentCut::make()->fourK())->toBeInstanceOf(FluentCut::class);
        expect(FluentCut::make()->vertical())->toBeInstanceOf(FluentCut::class);
        expect(FluentCut::make()->square())->toBeInstanceOf(FluentCut::class);
    });

    it('chains methods fluently', function () {
        $fc = FluentCut::make()
            ->fullHd()
            ->fps(30)
            ->addColor('black', duration: 2)
            ->addText('Hello', x: 'center', y: '10%')
            ->saveTo('/tmp/fluentcut_test_output.mp4');

        expect($fc)->toBeInstanceOf(FluentCut::class);
    });

    it('fails render with no clips', function () {
        $result = FluentCut::make()
            ->fullHd()
            ->saveTo('/tmp/output.mp4')
            ->render();

        expect($result->isSuccessful())->toBeFalse();
        expect($result->error)->toContain('No clips');
    });

    it('fails render with no output path', function () {
        $result = FluentCut::make()
            ->fullHd()
            ->addColor('black')
            ->render();

        expect($result->isSuccessful())->toBeFalse();
        expect($result->error)->toContain('output path');
    });

    it('throws when adding non-existent video file', function () {
        expect(fn () => FluentCut::make()->addVideo('/nonexistent/video.mp4'))
            ->toThrow(RenderException::class);
    });

    it('throws when adding non-existent image file', function () {
        expect(fn () => FluentCut::make()->addImage('/nonexistent/image.jpg'))
            ->toThrow(RenderException::class);
    });

    it('throws when overlaying non-existent image', function () {
        expect(function () {
            FluentCut::make()
                ->addColor('black')
                ->overlayImage('/nonexistent/logo.png');
        })->toThrow(RenderException::class);
    });

    it('throws when adding non-existent audio', function () {
        expect(function () {
            FluentCut::make()
                ->addColor('black')
                ->withAudio('/nonexistent/music.mp3');
        })->toThrow(RenderException::class);
    });

    it('chains useGpu fluently', function () {
        $fc = FluentCut::make()
            ->fullHd()
            ->useGpu()
            ->addColor('black', duration: 2)
            ->saveTo('/tmp/fluentcut_test_gpu.mp4');

        expect($fc)->toBeInstanceOf(FluentCut::class);
    });

    it('chains useGpu with specific backend', function () {
        $fc = FluentCut::make()
            ->fullHd()
            ->useGpu(HardwareAccel::Nvenc)
            ->addColor('black', duration: 2)
            ->saveTo('/tmp/fluentcut_test_gpu.mp4');

        expect($fc)->toBeInstanceOf(FluentCut::class);
    });
});

describe('Enums', function () {
    it('Format detects from path', function () {
        expect(Format::fromPath('video.mp4'))->toBe(Format::Mp4);
        expect(Format::fromPath('video.mkv'))->toBe(Format::Mkv);
        expect(Format::fromPath('video.webm'))->toBe(Format::Webm);
        expect(Format::fromPath('video.gif'))->toBe(Format::Gif);
        expect(Format::fromPath('video.mov'))->toBe(Format::Mov);
        expect(Format::fromPath('unknown.xyz'))->toBe(Format::Mp4);
    });

    it('Format returns default codec', function () {
        expect(Format::Mp4->defaultCodec())->toBe(Codec::H264);
        expect(Format::Webm->defaultCodec())->toBe(Codec::Vp9);
        expect(Format::Gif->defaultCodec())->toBe(Codec::Gif);
    });

    it('Codec returns output args', function () {
        $args = Codec::H264->defaultOutputArgs();
        expect($args)->toContain('libx264');
        expect($args)->toContain('yuv420p');
    });

    it('ResizeMode generates FFmpeg filter', function () {
        $filter = ResizeMode::Cover->toFFmpegFilter(1920, 1080);
        expect($filter)->toContain('scale=1920:1080');
        expect($filter)->toContain('crop=1920:1080');
    });

    it('ResizeMode ContainBlur uses blur filter', function () {
        $filter = ResizeMode::ContainBlur->toFFmpegFilter(1920, 1080);
        expect($filter)->toContain('boxblur');
        expect($filter)->toContain('overlay');
    });

    it('Transition returns description', function () {
        expect(Transition::Fade->description())->toBeString();
        expect(Transition::Fade->isCrossfade())->toBeTrue();
        expect(Transition::WipeLeft->isCrossfade())->toBeFalse();
    });

    it('HardwareAccel maps GPU encoders', function () {
        expect(HardwareAccel::Nvenc->toGpuEncoder(Codec::H264))->toBe('h264_nvenc');
        expect(HardwareAccel::Nvenc->toGpuEncoder(Codec::H265))->toBe('hevc_nvenc');
        expect(HardwareAccel::VideoToolbox->toGpuEncoder(Codec::H264))->toBe('h264_videotoolbox');
        expect(HardwareAccel::Qsv->toGpuEncoder(Codec::H264))->toBe('h264_qsv');
        expect(HardwareAccel::Vaapi->toGpuEncoder(Codec::H264))->toBe('h264_vaapi');
    });

    it('HardwareAccel returns null for unsupported codecs', function () {
        expect(HardwareAccel::Nvenc->toGpuEncoder(Codec::Vp9))->toBeNull();
        expect(HardwareAccel::Nvenc->toGpuEncoder(Codec::Copy))->toBeNull();
    });

    it('HardwareAccel None is not GPU', function () {
        expect(HardwareAccel::None->isGpu())->toBeFalse();
        expect(HardwareAccel::Auto->isGpu())->toBeTrue();
        expect(HardwareAccel::Nvenc->isGpu())->toBeTrue();
    });

    it('HardwareAccel supportsCodec checks correctly', function () {
        expect(HardwareAccel::Nvenc->supportsCodec(Codec::H264))->toBeTrue();
        expect(HardwareAccel::Nvenc->supportsCodec(Codec::Vp9))->toBeFalse();
    });

    it('HardwareAccel returns GPU output args', function () {
        $args = HardwareAccel::Nvenc->gpuOutputArgs(Codec::H264);
        expect($args)->toContain('h264_nvenc');
        expect($args)->toContain('p4');
    });

    it('HardwareAccel Auto returns empty GPU output args', function () {
        $args = HardwareAccel::Auto->gpuOutputArgs(Codec::H264);
        expect($args)->toBe([]);
    });

    it('HardwareAccel returns hwaccel input args', function () {
        $args = HardwareAccel::Nvenc->hwAccelInputArgs();
        expect($args)->toContain('-hwaccel');
        expect($args)->toContain('cuda');
    });

    it('HardwareAccel None returns empty input args', function () {
        expect(HardwareAccel::None->hwAccelInputArgs())->toBe([]);
        expect(HardwareAccel::Auto->hwAccelInputArgs())->toBe([]);
    });

    it('Codec supportsGpu delegates correctly', function () {
        expect(Codec::H264->supportsGpu(HardwareAccel::Nvenc))->toBeTrue();
        expect(Codec::Vp9->supportsGpu(HardwareAccel::Nvenc))->toBeFalse();
    });
});

describe('Position', function () {
    it('parses integer positions', function () {
        $pos = Position::parse(100, 200);
        expect($pos->resolveX(1920))->toBe(100);
        expect($pos->resolveY(1080))->toBe(200);
    });

    it('parses percent positions', function () {
        $pos = Position::parse('50%', '25%');
        expect($pos->resolveX(1920))->toBe(960);
        expect($pos->resolveY(1080))->toBe(270);
    });

    it('parses center keyword', function () {
        $pos = Position::parse('center', 'center');
        expect($pos->resolveX(1920))->toBe(960);
        expect($pos->resolveY(1080))->toBe(540);
    });

    it('parses top/left keywords', function () {
        $pos = Position::parse('left', 'top');
        expect($pos->resolveX(1920))->toBe(0);
        expect($pos->resolveY(1080))->toBe(0);
    });

    it('parses bottom/right keywords', function () {
        $pos = Position::parse('right', 'bottom');
        expect($pos->resolveX(1920, 120))->toBe(1800);
        expect($pos->resolveY(1080, 60))->toBe(1020);
    });
});

describe('Clip', function () {
    it('creates image clip', function () {
        $clip = Clip::fromImage('photo.jpg', 3.0);
        expect($clip->isImage())->toBeTrue();
        expect($clip->isVideo())->toBeFalse();
        expect($clip->duration)->toBe(3.0);
    });

    it('creates video clip', function () {
        $clip = Clip::fromVideo('video.mp4', start: 5.0, end: 15.0);
        expect($clip->isVideo())->toBeTrue();
        expect($clip->start)->toBe(5.0);
        expect($clip->end)->toBe(15.0);
    });

    it('creates color clip', function () {
        $clip = Clip::fromColor('#ff0000', 2.0);
        expect($clip->isColor())->toBeTrue();
        expect($clip->backgroundColor)->toBe('#ff0000');
    });

    it('validates color values', function () {
        expect(fn () => Clip::fromColor('red;malicious', 1.0))->toThrow(RenderException::class);
        expect(fn () => Clip::fromColor('color[0]', 1.0))->toThrow(RenderException::class);
    });

    it('accepts valid color names', function () {
        expect(Clip::fromColor('black', 1.0)->backgroundColor)->toBe('black');
        expect(Clip::fromColor('White', 1.0)->backgroundColor)->toBe('White');
    });

    it('accepts valid hex colors', function () {
        expect(Clip::fromColor('#ff0000', 1.0)->backgroundColor)->toBe('#ff0000');
        expect(Clip::fromColor('#fff', 1.0)->backgroundColor)->toBe('#fff');
        expect(Clip::fromColor('#00000080', 1.0)->backgroundColor)->toBe('#00000080');
    });
});

describe('TextOverlay', function () {
    it('generates drawtext filter', function () {
        $overlay = new TextOverlay(
            text: 'Hello World',
            position: Position::parse('center', '10%'),
            fontSize: 48,
            fontColor: 'white',
        );

        $filter = $overlay->toFFmpegDrawtext(1920, 1080, 5.0);
        expect($filter)->toContain('drawtext=');
        expect($filter)->toContain('Hello World');
        expect($filter)->toContain('fontsize=48');
        expect($filter)->toContain('fontcolor=white');
    });

    it('includes border when set', function () {
        $overlay = new TextOverlay(
            text: 'Bordered',
            position: Position::parse(0, 0),
            borderWidth: 3,
            borderColor: 'black',
        );

        $filter = $overlay->toFFmpegDrawtext(1920, 1080, 5.0);
        expect($filter)->toContain('borderw=3');
        expect($filter)->toContain('bordercolor=black');
    });

    it('includes shadow when set', function () {
        $overlay = new TextOverlay(
            text: 'Shadow',
            position: Position::parse(0, 0),
            shadowX: 2,
            shadowY: 2,
            shadowColor: 'black@0.5',
        );

        $filter = $overlay->toFFmpegDrawtext(1920, 1080, 5.0);
        expect($filter)->toContain('shadowx=2');
        expect($filter)->toContain('shadowy=2');
        expect($filter)->toContain('shadowcolor=black@0.5');
    });

    it('includes enable timing when start/end set', function () {
        $overlay = new TextOverlay(
            text: 'Timed',
            position: Position::parse(0, 0),
            start: 1.0,
            end: 3.0,
        );

        $filter = $overlay->toFFmpegDrawtext(1920, 1080, 5.0);
        expect($filter)->toContain('enable=');
        expect($filter)->toContain('between(t');
    });

    it('escapes FFmpeg metacharacters', function () {
        expect(TextOverlay::escape('test[0]'))->toBe('test\\[0\\]');
        expect(TextOverlay::escape('a;b'))->toBe('a\\;b');
        expect(TextOverlay::escape('{key}'))->toBe('\\{key\\}');
        expect(TextOverlay::escape('100%'))->toBe('100%%');
    });
});

describe('RenderResult', function () {
    it('creates success result', function () {
        $result = \B7s\FluentCut\Results\RenderResult::success(
            outputPath: '/tmp/video.mp4',
            duration: 10.5,
            width: 1920,
            height: 1080,
            format: 'mp4',
            fileSize: 1024000,
        );

        expect($result->isSuccessful())->toBeTrue();
        expect($result->outputPath)->toBe('/tmp/video.mp4');
        expect($result->duration)->toBe(10.5);
        expect($result->getFormattedSize())->toBe('1000.0 KB');
    });

    it('creates failure result', function () {
        $result = \B7s\FluentCut\Results\RenderResult::failure('Something broke');

        expect($result->isSuccessful())->toBeFalse();
        expect($result->error)->toBe('Something broke');
        expect($result->outputPath)->toBeNull();
    });

    it('formats duration with fractional seconds', function () {
        $result = \B7s\FluentCut\Results\RenderResult::success(
            outputPath: '/tmp/video.mp4',
            duration: 65.7,
            width: 1920,
            height: 1080,
            format: 'mp4',
            fileSize: 1000,
        );

        expect($result->getFormattedDuration())->toBe('01:05.70');
    });

    it('converts to array', function () {
        $result = \B7s\FluentCut\Results\RenderResult::success(
            outputPath: '/tmp/video.mp4',
            duration: 5.0,
            width: 1920,
            height: 1080,
            format: 'mp4',
            fileSize: 500000,
        );

        $arr = $result->toArray();
        expect($arr)->toHaveKey('success');
        expect($arr)->toHaveKey('output_path');
        expect($arr)->toHaveKey('duration');
        expect($arr['success'])->toBeTrue();
    });
});
