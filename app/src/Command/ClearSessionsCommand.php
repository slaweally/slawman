<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'app:clear-sessions', description: 'Eski session kayıtlarını temizler')]
class ClearSessionsCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/slw-content/sessions')]
        private readonly string $sessionDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $fs = new Filesystem();
        if ($fs->exists($this->sessionDir)) {
            $fs->remove($this->sessionDir);
            $fs->mkdir($this->sessionDir);
        }
        $io->success('Session dosyaları temizlendi.');

        return Command::SUCCESS;
    }
}
