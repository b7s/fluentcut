<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use B7s\FluentCut\Enums\Transition;
use B7s\FluentCut\FluentCut;

$result = FluentCut::make()
    ->fullHd()
    ->addVideo(__DIR__ . '/../examples/assets/intro.mp4')
    ->addImage(__DIR__ . '/../examples/assets/slide3.jpg.jpg', duration: 1)
    ->addText('Part 2', x: 'center', y: 'center', fontSize: 72, fontColor: 'yellow')
    ->addVideo(__DIR__ . '/../examples/assets/outro.mp4')
    ->fade(0.5)
    ->withAudio(__DIR__ . '/../examples/assets/david-j-barrios-intense-doom-style-instrumental-metal-synthetic-eden.mp3', volume: 0.5)
    ->saveTo(__DIR__ . '/output/video-with-images.mp4')
    ->render();

if ($result->isSuccessful()) {
    echo "Created: {$result->outputPath}" . PHP_EOL;
} else {
    echo "Error: {$result->error}" . PHP_EOL;
}
