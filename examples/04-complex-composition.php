<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use B7s\FluentCut\Enums\Transition;
use B7s\FluentCut\FluentCut;

echo "Just a moment. This may take some time..." . PHP_EOL . PHP_EOL;

$result = FluentCut::make()
    ->fullHd()
    ->useGpu()
    ->addVideo(__DIR__ . '/../examples/assets/intro.mp4')
    ->addImage(__DIR__ . '/../examples/assets/slide3.jpg', duration: 5)
    ->addText('Chapter 1', x: 'center', y: 'top', fontSize: 64, borderWidth: 3)
    ->overlayImage(__DIR__ . '/../examples/assets/logo.png', x: '90%', y: '5%', width: 120)
    ->addBlack(0.5)
    ->addVideo(__DIR__ . '/../examples/assets/outro.mp4', start: 0, end: 10)
    ->addText('Thanks for watching!', x: 'center', y: 'center', fontSize: 48, shadowX: 3, shadowY: 3)
    ->transition(Transition::Fade, 0.5)
    ->withAudio(__DIR__ . '/../examples/assets/david-j-barrios-intense-doom-style-instrumental-metal-synthetic-eden.mp3', loop: true, volume: 0.7)
    ->keepSourceAudio()
    ->saveTo(__DIR__ . '/output/composition.mp4')
    ->render();

if ($result->isSuccessful()) {
    echo "Created: {$result->outputPath}" . PHP_EOL;
    echo "Duration: {$result->getFormattedDuration()}" . PHP_EOL;
    echo "Size: {$result->getFormattedSize()}" . PHP_EOL;
} else {
    echo "Error: {$result->error}" . PHP_EOL;
}
