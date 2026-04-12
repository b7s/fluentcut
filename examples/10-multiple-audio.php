<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use B7s\FluentCut\FluentCut;

echo "Creating video with multiple audio tracks..." . PHP_EOL . PHP_EOL;

$start = microtime(true);
$result = FluentCut::make()
    ->fullHd()
    ->addImage(__DIR__ . '/../examples/assets/slide1.jpg', duration: 3)
    ->addImage(__DIR__ . '/../examples/assets/slide2.jpg', duration: 3)
    ->addImage(__DIR__ . '/../examples/assets/slide3.jpg', duration: 3)
    ->fade(0.5)
    // Track 1: Chariots of War - plays from start with fade in/out
    ->withAudio(__DIR__ . '/../examples/assets/chariots-of-war-aakash-gandhi.mp3', volume: 0.5, endAt: 3, fadeDuration: 0.5)
    // Track 2: Intense Doom - loops from 3s with different volume and fade
    ->withAudio(__DIR__ . '/../examples/assets/david-j-barrios-intense-doom-style-instrumental-metal-synthetic-eden.mp3', volume: 0.9, startAt: 3.0, loop: true, fadeDuration: 0.5)
    ->saveTo(__DIR__ . '/output/multiple-audio.mp4')
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