<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use B7s\FluentCut\Enums\Transition;
use B7s\FluentCut\Enums\VideoEffect;
use B7s\FluentCut\FluentCut;

$start = microtime(true);
$result = FluentCut::make()
    ->fullHd()
    ->addImage(__DIR__ . '/../examples/assets/slide1.jpg', duration: 3, effect: VideoEffect::ZoomCenter)
    ->transition(Transition::WipeDown, duration: 1)
    ->addImage(__DIR__ . '/../examples/assets/slide2.jpg', duration: 3)
    ->transition(Transition::SlideRight, duration: 1)
    ->addImage(__DIR__ . '/../examples/assets/slide3.jpg', duration: 5)
    ->transition(Transition::Dissolve, duration: 1)
    ->addImage(__DIR__ . '/../examples/assets/slide1.jpg', duration: 3, effect: VideoEffect::ZoomCenter) // should use the cache
    ->transition(Transition::HorizontalBlur,  duration: 3)
    ->addImage(__DIR__ . '/../examples/assets/slide2.jpg', duration: 4)
    ->fade(0.5)
    ->saveTo(__DIR__ . '/output/slideshow.mp4')
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
