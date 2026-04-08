<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use B7s\FluentCut\FluentCut;

$result = FluentCut::make()
    ->fullHd()
    ->addImage(__DIR__ . '/../examples/assets/slide2.jpg', duration: 5)
    ->addStyledText(
        text: 'Welcome to FluentCut',
        x: 'center',
        y: '10%',
        fontSize: 64,
        fontColor: 'white',
        borderWidth: 3,
        borderColor: 'black',
        shadowX: 3,
        shadowY: 3,
        shadowColor: 'black@0.7',
    )
    ->addText('A fluent video editing API', x: 'center', y: '25%', fontSize: 28, fontColor: '#cccccc')
    ->saveTo(__DIR__ . '/output/text-overlay.mp4')
    ->render();

if ($result->isSuccessful()) {
    echo "Created: {$result->outputPath}" . PHP_EOL;
} else {
    echo "Error: {$result->error}" . PHP_EOL;
}
