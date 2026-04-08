<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use B7s\FluentCut\FluentCut;

$result = FluentCut::make()
    ->fromVideo(__DIR__ . '/../examples/assets/composition.mp4', start: 10.0, end: 30.0)
    ->saveTo(__DIR__ . '/output/clip.mp4')
    ->render();

if ($result->isSuccessful()) {
    echo "Clip: {$result->outputPath} ({$result->getFormattedDuration()})" . PHP_EOL;
} else {
    echo "Error: {$result->error}" . PHP_EOL;
}
