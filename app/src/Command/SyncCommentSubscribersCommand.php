<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SubscriberService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:sync-comment-subscribers', description: 'Yorum yapan e-posta adreslerini abone listesine ekler')]
class SyncCommentSubscribersCommand extends Command
{
    public function __construct(private readonly SubscriberService $subscriberService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->subscriberService->syncFromCommentEmails();
        $io->success(sprintf('%d yeni abone eklendi, %d atlandı (zaten kayıtlı veya geçersiz).', $result['added'], $result['skipped']));

        return Command::SUCCESS;
    }
}
