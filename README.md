<div align="center">
  <img src="docs/logo.webp" alt="Logo" width="200"/>
  
  # FluentCut
  
  ### 🎬 Craft and compose videos programmatically in PHP with an elegant fluent API
  
  [![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
  [![PHPStan Level 6](https://img.shields.io/badge/PHPStan-Level%206-brightgreen)](https://phpstan.org/)
  [![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
</div>

This standalone, developer‑friendly library brings the full power of FFmpeg into a beautifully fluent PHP API. Effortless to use, built for real‑world production environments, and designed with expressive chaining in mind — it handles everything from simple slideshows and video trimming to complex multi‑clip compositions with text overlays, transitions, and audio mixing.

Whether you're automating video generation, building media pipelines, or creating dynamic content at scale, FluentCut gives you a clean, modern, and type‑safe toolkit that makes programmatic video editing feel natural.

## ✨ Features

- 🎯 **Fluent API** - Laravel-inspired chainable interface that reads like a sentence
- 🎬 **Video Composition** - Combine videos, images, and color clips into seamless productions
- ✍️ **Text Overlays** - Add styled text with borders, shadows, and custom positioning
- 🖼️ **Image Overlays** - Layer images on top of clips with precise positioning
- 🎵 **Audio Control** - Add background music, keep source audio, and adjust volume
- 🔄 **Transitions** - 12 built-in transitions including fades, wipes, slides, and dissolves
- 🎨 **Video Effects** - 14 built-in visual effects with stackable combinations: soft zoom (Ken Burns), sepia, grayscale, vignette, and more
- 📐 **Smart Resize** - Four resize modes: contain, contain with blur, cover, and stretch
- ⚡ **Presets** - One‑call presets for slideshows, social media, GIFs, and web output
- 🔒 **Type‑Safe** - Full PHP 8.3+ type hints / PHPStan level 6
- 🛠️ **CLI Tools** - Built‑in doctor and info commands for diagnostics and media inspection
- 🚀 **GPU Acceleration** - Auto-detects and uses NVIDIA NVENC, Intel QSV, VA-API, or VideoToolbox when available
- ⏱️ **No Hard Timeout** - Renders without time limits by default; auto-scales per operation

## Easy to use

```php
use B7s\FluentCut\Enums\VideoEffect;

$result = FluentCut::make()
    ->fullHd()
    ->addImage('photo.jpg', duration: 3, effect: VideoEffect::SoftZoom)
    ->addText('Hello, world!')
    ->saveTo('output.mp4')
    ->render();
```

## 📦 Installation

```bash
composer require b7s/fluentcut
```

### Requirements

FFmpeg must be installed on your system. FluentCut uses `ffmpeg` and `ffprobe` under the hood for all media operations.

```bash
# Ubuntu / Debian
sudo apt install ffmpeg

# macOS
brew install ffmpeg

# Windows — download from https://ffmpeg.org/download.html
```

### Check Installation

```bash
vendor/bin/fluentcut doctor
```

This will verify that PHP 8.3+, FFmpeg, and FFprobe are available and properly configured.

## 🚀 Quick Start

### Basic Usage

```php
use B7s\FluentCut\FluentCut;
use B7s\FluentCut\Enums\VideoEffect;

$result = FluentCut::make()
    ->fullHd()
    ->addImage('slide1.jpg', duration: 3, effect: VideoEffect::SoftZoom)
    ->addImage('slide2.jpg', duration: 3)
    ->addImage('slide3.jpg', duration: 2)
    ->fade(0.5)
    ->saveTo('output/slideshow.mp4')
    ->render();

echo "Saved to: " . $result->outputPath;
echo "Duration: " . $result->getFormattedDuration();
```

### Text Overlays

```php
$result = FluentCut::make()
    ->fromVideo('input.mp4')
    ->fullHd()
    ->addImage('background.jpg', duration: 5)
    ->addText(
        text: 'Welcome to FluentCut',
        x: 'center',
        y: '10%',
        fontSize: 64,
        fontColor: 'white',
        borderWidth: 3,
        borderColor: 'black',
        shadowX: 3,
        shadowY: 3,
        shadowColor: 'black@0.7',
    )
    ->addText(
        'A fluent video editing API',
        x: 'center',
        y: '25%',
        fontSize: 28,
        fontColor: '#cccccc'
    )
    ->saveTo('output/text-overlay.mp4')
    ->render();
```

### Resize Video

```php
$result = FluentCut::make()
    ->fromVideo('input.mp4')
    ->resize(1280, 720)
    ->saveTo('output/resized.mp4')
    ->render();
```

### Cut/Trim Video

```php
$result = FluentCut::make()
    ->fromVideo('long-video.mp4', start: 10.0, end: 30.0)
    ->saveTo('output/clip.mp4')
    ->render();
```

### GIF Export

```php
$result = FluentCut::make()
    ->forGif()
    ->addImage('frame1.jpg', duration: 0.5)
    ->addImage('frame2.jpg', duration: 0.5)
    ->addImage('frame3.jpg', duration: 0.5)
    ->addImage('frame4.jpg', duration: 0.5)
    ->saveTo('output/animation.gif')
    ->render();
```

### Video Effects

Apply visual effects to individual clips. Pass a single effect or an array — duplicates are automatically removed.

```php
use B7s\FluentCut\Enums\VideoEffect;

$result = FluentCut::make()
    ->fullHd()
    ->addImage('photo1.jpg', duration: 3, effect: VideoEffect::SoftZoom)
    ->addText('Ken Burns Effect', x: 'center', y: 'bottom', fontSize: 36)
    ->addImage('photo2.jpg', duration: 3, effect: [VideoEffect::Sepia, VideoEffect::Vignette])
    ->addText('Sepia + Vignette', x: 'center', y: 'bottom', fontSize: 36)
    ->addImage('photo3.jpg', duration: 3)
    ->effect(VideoEffect::Grayscale, VideoEffect::Sharpen)
    ->addImage('photo4.jpg', duration: 3)
    ->fade(0.5)
    ->saveTo('output/effects-demo.mp4')
    ->render();
```

### Complex Composition

```php
use B7s\FluentCut\Enums\Transition;

$result = FluentCut::make()
    ->fullHd()
    ->addVideo('intro.mp4')
    ->addImage('slide.png', duration: 5)
    ->addText('Chapter 1', x: 'center', y: 'top', fontSize: 64, borderWidth: 3)
    ->overlayImage('logo.png', x: '90%', y: '5%', width: 120)
    ->addBlack(0.5)
    ->addVideo('outro.mp4', start: 0, end: 10)
    ->addText('Thanks for watching!', x: 'center', y: 'center', fontSize: 48)
    ->transition(Transition::Fade, 0.5)
    ->withAudio('bgm.mp3', volume: 0.7)
    ->keepSourceAudio()
    ->saveTo('output/composition.mp4')
    ->render();
```

### GPU Acceleration

Leverage hardware-accelerated encoding for dramatically faster rendering. FluentCut auto-detects available GPU encoders and uses them automatically.

```php
use B7s\FluentCut\Enums\HardwareAccel;

// Auto-detect best available GPU (NVIDIA, Intel, AMD, or Apple Silicon)
$result = FluentCut::make()
    ->fullHd()
    ->useGpu()
    ->addImage('slide1.jpg', duration: 3)
    ->addImage('slide2.jpg', duration: 3)
    ->fade(0.5)
    ->saveTo('output/fast_render.mp4')
    ->render();

// Explicit GPU backend
$result = FluentCut::make()
    ->useGpu(HardwareAccel::Nvenc)   // Force NVIDIA
    ->useGpu(HardwareAccel::VideoToolbox)  // Force Apple Silicon
    ->useGpu(HardwareAccel::Qsv)     // Force Intel Quick Sync
    ->useGpu(HardwareAccel::Vaapi)   // Force AMD/Intel VA-API
    // ... build composition
    ->render();
```

**How it works:** Segment rendering uses GPU encoding for parallel speed, while transition passes (xfade) use CPU — this is unavoidable since FFmpeg's xfade filter is CPU-only, but it's only re-encoding already-compressed segments.

## 🛠️ CLI Commands

### doctor - Diagnose Installation

```bash
vendor/bin/fluentcut doctor
```

The `doctor` command checks your installation and shows:

- PHP version (8.3+ required)
- FFmpeg availability and version
- FFprobe availability and version

### info - Media File Information

```bash
vendor/bin/fluentcut info video.mp4
```

The `info` command probes a media file and displays:

- Format and duration
- File size and bitrate
- Video streams (resolution, framerate)
- Audio streams (sample rate, channels)

## 📖 API Reference

### Canvas / Dimensions

Set the output canvas size and framerate for your composition. All dimension presets also set sensible default framerates.

```php
// Custom canvas
->canvas(1280, 720)  // Custom width x height

// Presets
->hd()         // 1280x720
->fullHd()     // 1920x1080
->fourK()      // 3840x2160
->vertical()   // 1080x1920 (portrait / stories)
->square()     // 1080x1080 (social media)

// Framerate
->fps(30)      // Custom framerate
->fps(60)      // High framerate

// Resize mode (how clips fit the canvas)
->resizeMode(ResizeMode::ContainBlur)  // Default
```

### Video Clips

Add video files to the composition. Optionally trim by specifying start and end times in seconds.

```php
// Add entire video
->addVideo('clip.mp4')

// Trim a segment
->addVideo('clip.mp4', start: 5.0, end: 15.0)

// With a visual effect
->addVideo('clip.mp4', effect: VideoEffect::Grayscale)

// With multiple effects
->addVideo('clip.mp4', effect: [VideoEffect::Grayscale, VideoEffect::Vignette])

// Alias
->fromVideo('clip.mp4', start: 5.0, end: 15.0)
```

### Image Clips

Add still images as clips with a specified duration. Perfect for building slideshows or title cards.

```php
// Single image (1 second default)
->addImage('photo.jpg', duration: 3)

// With a single effect
->addImage('photo.jpg', duration: 3, effect: VideoEffect::SoftZoom)

// With multiple effects
->addImage('photo.jpg', duration: 3, effect: [VideoEffect::SoftZoom, VideoEffect::Vignette])

// Multiple images at once
->addImages(['img1.jpg', 'img2.jpg', 'img3.jpg'], duration: 2)

// Multiple images with effects
->addImages(['img1.jpg', 'img2.jpg'], duration: 2, effect: [VideoEffect::Sepia, VideoEffect::Sharpen])
```

### Color / Background Clips

Add solid-color clips to create title screens, interstitials, or backgrounds.

```php
// Custom color
->addColor('#1a1a2e', duration: 2)
->addColor('red', duration: 1)

// With effects
->addColor('black', duration: 2, effect: VideoEffect::Vignette)
->addColor('black', duration: 2, effect: [VideoEffect::Brightness, VideoEffect::Vignette])

// Presets
->addBlack(0.5)  // Half-second black screen
->addWhite(1.0)  // One-second white screen
```

### Text Overlays

Add text to the last added clip. All parameters except `text` are optional. Supports positioning with pixel values, percentages (`'50%'`), or keywords (`'center'`, `'top'`, `'bottom'`, `'left'`, `'right'`).

```php
// Text with just the text parameter (all other options have sensible defaults)
->addText('Hello')

// Custom position, size, and color
->addText('Title', x: 'center', y: 'top', fontSize: 64, fontColor: 'white')

// Add border/outline
->addText('Subtitle', borderWidth: 3, borderColor: 'black')

// Add drop shadow
->addText('With Shadow', shadowX: 3, shadowY: 3, shadowColor: 'black@0.5')

// Both border and shadow combined
->addText('Styled', borderWidth: 2, borderColor: 'black', shadowX: 2, shadowY: 2, shadowColor: 'black@0.5')

// Custom font file
->addText('Custom', fontFile: '/path/to/font.ttf')
```

### Image Overlays

Layer images on top of the last added clip, such as watermarks or logos.

```php
->overlayImage('logo.png', x: '90%', y: '5%', width: 120)

// Full parameters
->overlayImage(
    path: 'watermark.png',
    x: 'right',
    y: 'bottom',
    width: 200,
    height: 100,
    start: 0.0,
    end: null  // null = visible for entire clip
)
```

### Audio

Control the audio layer of your composition. Add background music, preserve source audio, and adjust volume levels. You can add multiple audio tracks with independent control over volume, start time, and duration.

```php
// Single background music track (plays once, full volume)
->withAudio('bgm.mp3')

// Single track with custom volume
->withAudio('bgm.mp3', volume: 0.7)

// Multiple audio tracks - each call adds a new track
->withAudio('intro.mp3', volume: 1.0)           // Track 1: full volume from start
->withAudio('background.mp3', volume: 0.5)      // Track 2: half volume from start
->withAudio('ending.mp3', volume: 0.8, startAt: 30.0)  // Track 3: starts at 30s

// Multiple tracks with different start times and durations
->withAudio('music.mp3', volume: 0.6, startAt: 0.0, duration: null)   // Plays from 0s until video ends
->withAudio('narration.mp3', volume: 1.0, startAt: 5.0, duration: 30.0)  // Plays from 5s for 30s

// Keep audio from source video clips
->keepSourceAudio()

// Adjust volume of the last added audio track
->audioVolume(0.5)

// Add audio specifically to the current clip (not global)
->addAudioToClip('narration.mp3')
```

**Parameters:**
- `path` (string) - Path to the audio file
- `volume` (?float) - Volume level 0.0-1.0, defaults to 1.0
- `startAt` (float) - Start offset in seconds, defaults to 0.0
- `duration` (?float) - How long to play in seconds, null = play until video ends

**Note:** Each audio track plays once by default. If the audio is shorter than the video, it stops. Use `duration` to explicitly control how long each track plays.

### Transitions

Define the transition between the current clip and the next one. Transitions are applied between consecutive clips in the timeline.

```php
use B7s\FluentCut\Enums\Transition;

// Generic transition method
->transition(Transition::Fade, 0.5)
->transition(Transition::WipeLeft, 0.3)
->transition(Transition::Dissolve, 1.0)

// Shorthand presets
->fade(0.5)               // Crossfade
->fadeThroughBlack(0.5)   // Fade through black
->noTransition()          // Hard cut
```

**Available transitions:** `Fade`, `FadeBlack`, `FadeWhite`, `WipeLeft`, `WipeRight`, `WipeUp`, `WipeDown`, `SlideLeft`, `SlideRight`, `Dissolve`, `None`

### Video Effects

Apply one or more visual effects per clip. Pass a single `VideoEffect`, an array of effects, or use the variadic `effect()` method. Duplicates and `None` are automatically removed.

```php
use B7s\FluentCut\Enums\VideoEffect;

// Single effect when adding a clip
->addImage('photo.jpg', duration: 3, effect: VideoEffect::SoftZoom)
->addVideo('clip.mp4', effect: VideoEffect::Grayscale)
->addColor('black', duration: 2, effect: VideoEffect::Vignette)

// Multiple effects via array
->addImage('photo.jpg', duration: 3, effect: [VideoEffect::SoftZoom, VideoEffect::Vignette])
->addImages(['a.jpg', 'b.jpg'], duration: 2, effect: [VideoEffect::Sepia, VideoEffect::Sharpen])

// Variadic effect() on the last clip (merges with existing effects)
->addImage('photo.jpg', duration: 3)
->effect(VideoEffect::Sepia, VideoEffect::Sharpen)

// No effect (default)
->addImage('photo.jpg', duration: 3)
```

**Available effects:**

| Effect | Description |
|---|---|
| `VideoEffect::None` | No effect (default) |
| `VideoEffect::SoftZoom` | Slow zoom in (Ken Burns effect) |
| `VideoEffect::Grayscale` | Convert to grayscale |
| `VideoEffect::Sepia` | Sepia tone (vintage warm look) |
| `VideoEffect::Blur` | Gaussian blur |
| `VideoEffect::Sharpen` | Sharpen details |
| `VideoEffect::Vignette` | Dark edges vignette |
| `VideoEffect::Brightness` | Increase brightness |
| `VideoEffect::Contrast` | Increase contrast |
| `VideoEffect::Saturate` | Boost color saturation |
| `VideoEffect::Desaturate` | Reduce color saturation |
| `VideoEffect::Negate` | Invert colors |
| `VideoEffect::EdgeDetect` | Edge detection outline |
| `VideoEffect::Pixelate` | Pixelation mosaic |

### Resize Modes

Control how clips are fitted to the canvas when their aspect ratio doesn't match.

```php
use B7s\FluentCut\Enums\ResizeMode;

->resizeMode(ResizeMode::ContainBlur)  // Fit + blurred background (default)
->resizeMode(ResizeMode::Contain)      // Fit with letterboxing
->resizeMode(ResizeMode::Cover)        // Crop to fill (aspect preserved)
->resizeMode(ResizeMode::Stretch)      // Stretch to fill (aspect distorted)
```

### Output Configuration

Configure where and how the final video is saved.

```php
// Output path
->saveTo('output/video.mp4')  // Save to specific location
->output('output/video.mp4')  // alias

// Codec selection
use B7s\FluentCut\Enums\Codec;
->codec(Codec::H264)  // Most compatible (default)
->codec(Codec::H265)  // Better compression
->codec(Codec::Vp9)   // WebM format

// Resize output (independent of canvas)
->resize(1280, 720)
```

### Presets

Pre-configured settings optimized for common use cases. Each preset sets canvas size, framerate, transition, and resize mode.

```php
->forSlideshow()      // Full HD, 30fps, fade transition, contain with blur
->forPresentation()   // Full HD, 24fps, contain mode
->forSocialMedia()    // Vertical (1080x1920), 30fps, cover mode
->forGif()            // 480x270, 10fps, cover mode
->forWeb()            // Full HD, 30fps, H.264, contain with blur
```

### Progress Monitoring

Track render progress in real time with a callback that receives detailed progress information.

```php
->onProgress(function (\B7s\FluentCut\Results\ProgressInfo $progress) {
    $bar = str_repeat('=', (int) round($progress->percentage / 2.5));
    $pad = str_repeat(' ', 40 - strlen($bar));
    echo "\r  [{$bar}{$pad}] {$progress->getFormattedPercentage()} "
       . "| Time: {$progress->getFormattedTime()} "
       . "| Speed: {$progress->getFormattedSpeed()} "
       . "| {$progress->phase}";
})
```

### GPU Acceleration

Enable hardware-accelerated encoding for faster rendering. FluentCut auto-detects available GPU encoders.

```php
use B7s\FluentCut\Enums\HardwareAccel;

// Auto-detect best available GPU (NVIDIA, Intel, AMD, Apple Silicon)
->useGpu()

// Explicit backend
->useGpu(HardwareAccel::Nvenc)        // NVIDIA NVENC
->useGpu(HardwareAccel::VideoToolbox) // Apple Silicon
->useGpu(HardwareAccel::Qsv)          // Intel Quick Sync
->useGpu(HardwareAccel::Vaapi)        // AMD/Intel VA-API
```

**Platform detection:**
- macOS → VideoToolbox (Apple Silicon)
- Windows → Nvenc, then Qsv
- Linux → Nvenc, then Vaapi, then Qsv

**Note:** Transition effects (xfade) use CPU because FFmpeg's xfade filter has no GPU implementation. This is a minor re-encoding pass — the heavy segment rendering still uses GPU.

### Execution (render)

The `render()` method is the terminal operation that builds the FFmpeg command and executes it, returning a `RenderResult` object.

```php
$result = FluentCut::make()
    ->fullHd()
    ->addVideo('clip.mp4')
    ->saveTo('output.mp4')
    ->render();
```

### Result Object

The `RenderResult` object contains information about the rendered video and metadata about the render process.

```php
$result->isSuccessful();           // bool - Check if render succeeded
$result->getPath();                // string - Output file path
$result->getDuration();            // float - Duration in seconds
$result->getFormattedDuration();   // string - "02:30.50" format
$result->getFormattedSize();       // string - Human-readable file size
$result->width;                    // int - Video width
$result->height;                   // int - Video height
$result->format;                   // string - Output format
$result->fileSize;                 // int - File size in bytes
$result->error;                    // string|null - Error message if failed
$result->metadata;                 // array - Render parameters used
$result->toArray();                // array - All data as array
```

### Static Helpers

Utility methods for media inspection and system checks without creating a FluentCut instance.

```php
use B7s\FluentCut\FluentCut;

// Probe a media file (returns full metadata)
$info = FluentCut::probe('video.mp4');

// Get specific properties
$duration = FluentCut::getDuration('video.mp4');     // ?float - seconds
$dimensions = FluentCut::getDimensions('video.mp4'); // ?array ['width' => ..., 'height' => ...]

// Check system requirements
$ready = FluentCut::checkRequirements();  // bool - FFmpeg + FFprobe available
```

## ⚙️ Configuration

Create a `fluentcut-config.php` file in your project root:

```php
<?php

declare(strict_types=1);

return [
    // FFmpeg / FFprobe paths (null = auto-detect from PATH)
    'ffmpeg_path' => null,
    'ffprobe_path' => null,

    // Default canvas dimensions
    'default_width' => 1920,
    'default_height' => 1080,

    // Default framerate
    'default_fps' => 30,

    // Default video codec
    'default_codec' => 'libx264',

    // Default clip duration (for image/color clips)
    'default_duration' => 1.0,

    // Process timeout in seconds (0 = unlimited, default)
    'timeout' => 0,

    // Enable verbose FFmpeg output
    'verbose' => false,
];
```

**Configuration File Location:**

The configuration file is searched in the following order:

1. Explicit path (if provided programmatically)
2. Project root (where `composer.json` is located)
3. Current working directory
4. Package root (fallback)

## 📋 Requirements

- PHP 8.3+
- Composer 2+
- FFmpeg installed on system
- FFprobe installed on system (included with FFmpeg)

## 🌐 Platform Support

| Platform | Architecture                  | Notes              |
| -------- | ----------------------------- | ------------------ |
| Linux    | x86_64, arm64                 | Full support       |
| macOS    | x86_64, arm64 (Apple Silicon) | Full support       |
| Windows  | x86_64                        | Full support       |

## Running Tests

```bash
composer test          # Run all tests (Pest PHP)
composer test:unit     # Unit tests only
composer test:coverage # With coverage
composer analyse       # PHPStan level 6
```

## 📄 License

MIT License - see [LICENSE](LICENSE) file.

## 🙏 Credits

- [b7s/fluentvox](https://github.com/b7s/fluentvox) - API design and patterns inspiration
- Example music from: Intense Doom Style Instrumental Metal - "SYNTHETIC EDEN" (Free Music Archive) and license type (CC BY)
- Video Composition by [Onur Kaya](https://www.pexels.com/video/dramatic-mountain-landscape-with-rolling-clouds-35741878/)
- Video Intro by [Chandresh Uike](https://www.pexels.com/video/dynamic-abstract-light-trail-animation-29717596/)

