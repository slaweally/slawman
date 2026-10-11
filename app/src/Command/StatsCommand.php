<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\CommentRepository;
use App\Repository\MediaRepository;
use App\Repository\PostRepository;
use App\Repository\SubscriberRepository;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:stats', description: 'İstatistik özeti gösterir')]
class StatsCommand extends Command
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly CommentRepository $commentRepository,
        private readonly MediaRepository $mediaRepository,
        private readonly UserRepository $userRepository,
        private readonly SubscriberRepository $subscriberRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Slawman Portal İstatistikleri');
        $io->table(['Metrik', 'Değer'], [
            ['İçerik', (string) $this->postRepository->count([])],
            ['Yorum', (string) $this->commentRepository->count([])],
            ['Medya', (string) $this->mediaRepository->count([])],
            ['Kullanıcı', (string) $this->userRepository->count([])],
            ['Abone', (string) $this->subscriberRepository->count([])],
        ]);

        return Command::SUCCESS;
    }
}
