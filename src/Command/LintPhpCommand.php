<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'lint:php',
    description: 'Vérifie la syntaxe PHP (php -l) pour les fichiers ou répertoires donnés.',
)]
final class LintPhpCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument(
            'path',
            InputArgument::IS_ARRAY | InputArgument::REQUIRED,
            'Un ou plusieurs fichiers .php ou répertoires à parcourir',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $paths = $input->getArgument('path');
        \assert(\is_array($paths));

        $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';

        try {
            $files = $this->resolvePhpFiles($paths);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ($files === []) {
            $io->warning('Aucun fichier PHP trouvé pour les chemins indiqués.');

            return Command::SUCCESS;
        }

        sort($files);

        $failed = false;
        foreach ($files as $file) {
            $process = new Process([$php, '-l', $file]);
            $process->run();
            if (!$process->isSuccessful()) {
                $io->writeln(\trim($process->getErrorOutput() ?: $process->getOutput()));
                $failed = true;
            }
        }

        if ($failed) {
            $io->error('Syntaxe PHP invalide pour au moins un fichier.');

            return Command::FAILURE;
        }

        $io->success(\sprintf('%d fichier(s) PHP : syntaxe OK.', \count($files)));

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function resolvePhpFiles(array $paths): array
    {
        $files = [];
        foreach ($paths as $rawPath) {
            $rawPath = trim($rawPath);
            if ($rawPath === '') {
                continue;
            }
            $real = realpath($rawPath);
            if ($real === false) {
                throw new \InvalidArgumentException(\sprintf('Chemin introuvable : « %s ».', $rawPath));
            }
            foreach ($this->collectPhpFiles($real) as $file) {
                $files[$file] = true;
            }
        }

        return array_keys($files);
    }

    /**
     * @return \Generator<int, string>
     */
    private function collectPhpFiles(string $path): \Generator
    {
        if (is_file($path)) {
            if (str_ends_with(strtolower($path), '.php')) {
                yield $path;
            }

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $path,
                \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS,
            ),
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }
            $pathname = $fileInfo->getPathname();
            if (str_ends_with(strtolower($pathname), '.php')) {
                yield $pathname;
            }
        }
    }
}
