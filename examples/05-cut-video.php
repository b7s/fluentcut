<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use B7s\FluentCut\FluentCut;

$start = microtime(true);
$result = FluentCut::make()
    ->fromVideo(__DIR__ . '/../examples/assets/composition.mp4', start: 10.0, end: 30.0)
    ->saveTo(__DIR__ . '/output/clip.mp4')
    ->render();
$elapsed = microtime(true) - $start;

if ($result->isSuccessful()) {
    echo "Clip: {$result->outputPath} ({$result->getFormattedDuration()})" . PHP_EOL;
    echo "Render time: " . round($elapsed, 2) . " seconds" . PHP_EOL;
} else {
    echo "Error: {$result->error}" . PHP_EOL;
}
