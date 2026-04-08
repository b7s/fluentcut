<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use B7s\FluentCut\FluentCut;

$result = FluentCut::make()
    ->forGif()
    ->addImage(__DIR__ . '/../examples/assets/slide1.jpg', duration: 0.5)
    ->addImage(__DIR__ . '/../examples/assets/slide2.jpg', duration: 0.5)
    ->addImage(__DIR__ . '/../examples/assets/slide3.jpg', duration: 0.5)
    ->saveTo(__DIR__ . '/output/animation.gif')
    ->render();

if ($result->isSuccessful()) {
    echo "GIF: {$result->outputPath} ({$result->getFormattedSize()})" . PHP_EOL;
} else {
    echo "Error: {$result->error}" . PHP_EOL;
}
