<?php

declare(strict_types=1);

namespace B7s\FluentCut\Console\Commands;

use B7s\FluentCut\Services\FFmpegService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'info',
    description: 'Get information about a media file',
)]
final class InfoCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Path to the media file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = $input->getArgument('file');

        if (!file_exists($path)) {
            $io->error("File not found: {$path}");

            return Command::FAILURE;
        }

        $ffmpeg = new FFmpegService();
        $info = $ffmpeg->probe($path);

        if (empty($info)) {
            $io->error('Could not probe file. Is it a valid media file?');

            return Command::FAILURE;
        }

        $format = $info['format'] ?? [];

        $io->title('Media File Info');
        $io->definitionList(
            ['File' => realpath($path)],
            ['Format' => $format['format_long_name'] ?? $format['format_name'] ?? 'unknown'],
            ['Duration' => (isset($format['duration']) ? number_format((float) $format['duration'], 2) . 's' : 'unknown')],
            ['Size' => isset($format['size']) ? number_format((int) $format['size']) . ' bytes' : 'unknown'],
            ['Bit Rate' => isset($format['bit_rate']) ? number_format((int) $format['bit_rate']) . ' bps' : 'unknown'],
        );

        foreach ($info['streams'] ?? [] as $idx => $stream) {
            $type = $stream['codec_type'] ?? 'unknown';
            $io->section(ucfirst($type) . ' Stream #' . $idx);

            $items = [
                ['Codec' => $stream['codec_name'] ?? $stream['codec_long_name'] ?? 'unknown'],
            ];

            if ($type === 'video') {
                $items[] = ['Resolution' => ($stream['width'] ?? '?') . 'x' . ($stream['height'] ?? '?')];
                $items[] = ['Frame Rate' => $stream['r_frame_rate'] ?? 'unknown'];
            }

            if ($type === 'audio') {
                $items[] = ['Sample Rate' => ($stream['sample_rate'] ?? 'unknown') . ' Hz'];
                $items[] = ['Channels' => $stream['channels'] ?? 'unknown'];
            }

            $io->definitionList(...$items);
        }

        return Command::SUCCESS;
    }
}
