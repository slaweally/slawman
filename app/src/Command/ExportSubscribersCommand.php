<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\SubscriberRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:export-subscribers', description: 'Aboneleri CSV dosyasına export eder')]
class ExportSubscribersCommand extends Command
{
    public function __construct(private readonly SubscriberRepository $subscriberRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Çıktı dosyası', 'subscribers.csv');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = (string) $input->getOption('output');
        $handle = fopen($file, 'w');
        fputcsv($handle, ['email', 'is_active', 'subscribed_at']);
        foreach ($this->subscriberRepository->findAll() as $s) {
            fputcsv($handle, [$s->getEmail(), $s->isActive() ? '1' : '0', $s->getSubscribedAt()?->format('Y-m-d H:i:s')]);
        }
        fclose($handle);
        $io->success('Export tamamlandı: ' . $file);

        return Command::SUCCESS;
    }
}
