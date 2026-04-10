<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use B7s\FluentCut\Enums\VideoEffect;
use B7s\FluentCut\FluentCut;

$result = FluentCut::make()
    ->fullHd()
    ->addImage(__DIR__ . '/../examples/assets/slide1.jpg', duration: 3, effect: VideoEffect::SoftZoom)
    ->addImage(__DIR__ . '/../examples/assets/slide2.jpg', duration: 3)
    ->addImage(__DIR__ . '/../examples/assets/slide3.jpg', duration: 2)
    ->addImage(__DIR__ . '/../examples/assets/slide1.jpg', duration: 3, effect: VideoEffect::SoftZoom) // should use the cache
    ->fade(0.5)
    ->saveTo(__DIR__ . '/output/slideshow.mp4')
    ->render();

if ($result->isSuccessful()) {
    echo "Created: {$result->outputPath}" . PHP_EOL;
    echo "Duration: {$result->getFormattedDuration()}" . PHP_EOL;
    echo "Size: {$result->getFormattedSize()}" . PHP_EOL;
} else {
    echo "Error: {$result->error}" . PHP_EOL;
}
