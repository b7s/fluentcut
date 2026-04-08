<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use B7s\FluentCut\FluentCut;
use B7s\FluentCut\Results\ProgressInfo;

$result = FluentCut::make()
    ->fullHd()
    ->addColor('#1a1a2e', duration: 3)
    ->addStyledText(
        text: 'Real-time Progress Demo',
        x: 'center',
        y: 'center',
        fontSize: 48,
        fontColor: '#e94560',
        borderWidth: 3,
        borderColor: 'black',
        shadowX: 3,
        shadowY: 3,
        shadowColor: 'black@0.7',
    )
    ->addColor('#16213e', duration: 3)
    ->addBorderedText('Monitoring render progress...', x: 'center', y: 'center', fontSize: 36)
    ->fade(0.5)
    ->onProgress(function (ProgressInfo $progress) {
        $bar = str_repeat('=', (int) round($progress->percentage / 2.5));
        $pad = str_repeat(' ', 40 - strlen($bar));
        echo "\r  [{$bar}{$pad}] {$progress->getFormattedPercentage()} "
           . "| Time: {$progress->getFormattedTime()} "
           . "| Speed: {$progress->getFormattedSpeed()} "
           . "| {$progress->phase}";
    })
    ->saveTo(__DIR__ . '/output/progress-demo.mp4')
    ->render();

echo PHP_EOL;

if ($result->isSuccessful()) {
    echo "Created: {$result->outputPath}" . PHP_EOL;
    echo "Duration: {$result->getFormattedDuration()}" . PHP_EOL;
    echo "Size: {$result->getFormattedSize()}" . PHP_EOL;
} else {
    echo "Error: {$result->error}" . PHP_EOL;
}
