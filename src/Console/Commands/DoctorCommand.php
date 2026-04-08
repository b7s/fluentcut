<?php

declare(strict_types=1);

namespace B7s\FluentCut\Console\Commands;

use B7s\FluentCut\Services\FFmpegService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'doctor',
    description: 'Check system requirements for FluentCut',
)]
final class DoctorCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('FluentCut Doctor');

        $checks = [];

        $checks[] = $this->checkPHP($io);
        $checks[] = $this->checkFFmpeg($io);
        $checks[] = $this->checkFFprobe($io);

        $allPassed = !in_array(false, $checks, true);

        $io->newLine();
        if ($allPassed) {
            $io->success('All checks passed! FluentCut is ready to use.');
        } else {
            $io->error('Some checks failed. Please fix the issues above.');
        }

        return $allPassed ? Command::SUCCESS : Command::FAILURE;
    }

    private function checkPHP(SymfonyStyle $io): bool
    {
        $version = PHP_VERSION;
        $ok = version_compare($version, '8.3.0', '>=');

        $io->definitionList(
            ['PHP Version' => $ok ? "<info>{$version} ✓</info>" : "<error>{$version} (requires 8.3+) ✗</error>"],
        );

        return $ok;
    }

    private function checkFFmpeg(SymfonyStyle $io): bool
    {
        try {
            $ffmpeg = new FFmpegService();
            $path = $ffmpeg->getFFmpegPath();

            $io->definitionList(
                ['FFmpeg' => "<info>{$path} ✓</info>"],
            );

            return true;
        } catch (\Throwable $e) {
            $io->definitionList(
                ['FFmpeg' => '<error>Not found ✗</error>'],
            );

            return false;
        }
    }

    private function checkFFprobe(SymfonyStyle $io): bool
    {
        try {
            $ffmpeg = new FFmpegService();
            $path = $ffmpeg->getFFprobePath();

            $io->definitionList(
                ['FFprobe' => "<info>{$path} ✓</info>"],
            );

            return true;
        } catch (\Throwable $e) {
            $io->definitionList(
                ['FFprobe' => '<error>Not found ✗</error>'],
            );

            return false;
        }
    }
}
