<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use B7s\FluentCut\FluentCut;

$result = FluentCut::make()
    ->fromVideo(__DIR__ . '/../examples/assets/input.mp4')
    ->resize(1280, 720)
    ->saveTo(__DIR__ . '/output/resized.mp4')
    ->render();

if ($result->isSuccessful()) {
    echo "Resized to: {$result->width}x{$result->height}" . PHP_EOL;
    echo "Output: {$result->outputPath}" . PHP_EOL;
} else {
    echo "Error: {$result->error}" . PHP_EOL;
}
