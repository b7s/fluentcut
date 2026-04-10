<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use B7s\FluentCut\Enums\VideoEffect;
use B7s\FluentCut\FluentCut;

$start = microtime(true);
$assets = __DIR__ . '/../examples/assets';

$result = FluentCut::make()
    ->fullHd()
    ->addImage("{$assets}/slide1.jpg", duration: 3, effect: [VideoEffect::SoftZoom, VideoEffect::EdgeDetect])
    ->addText('Soft Zoom + Edge Detect', x: 'center', y: 256, fontSize: 36, borderWidth: 2)
    ->addImage("{$assets}/slide2.jpg", duration: 3, effect: VideoEffect::Sepia)
    ->addText('Sepia', x: 'center', y: 'bottom', fontSize: 36, borderWidth: 2)
    ->addImage("{$assets}/slide3.jpg", duration: 3)
    ->effect(VideoEffect::Grayscale, VideoEffect::Sharpen, VideoEffect::SoftZoom)
    ->addText('Grayscale + Sharpen + Zoom', x: 'center', y: 'bottom', fontSize: 36, borderWidth: 2)
    ->addImage("{$assets}/slide1.jpg", duration: 3, effect: [VideoEffect::Sepia, VideoEffect::Vignette])
    ->addText('Sepia + Vignette', x: 'center', y: 100, fontSize: 36, borderWidth: 2)
    ->addImage("{$assets}/slide1.jpg", duration: 3, effect: [VideoEffect::SoftZoom, VideoEffect::EdgeDetect]) // should use the cache
    ->fade(0.5)
    ->addBlack(1)
    ->fade()
    ->saveTo(__DIR__ . '/output/video-effects.mp4')
    ->render();
$elapsed = microtime(true) - $start;

if ($result->isSuccessful()) {
    echo "Created: {$result->outputPath}" . PHP_EOL;
    echo "Duration: {$result->getFormattedDuration()}" . PHP_EOL;
    echo "Size: {$result->getFormattedSize()}" . PHP_EOL;
    echo "Render time: " . round($elapsed, 2) . " seconds" . PHP_EOL;
} else {
    echo "Error: {$result->error}" . PHP_EOL;
}
