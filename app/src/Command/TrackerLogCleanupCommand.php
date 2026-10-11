<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\TrackerLogRepository;
use App\Service\SettingsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:tracker:cleanup',
    description: 'Takip paneli loglarını (retention/azami sayı) temizler — cron ile çalıştırılır',
)]
class TrackerLogCleanupCommand extends Command
{
    public function __construct(
        private readonly TrackerLogRepository $trackerLogRepository,
        private readonly SettingsService $settingsService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $retentionDays = $this->settingsService->getInt('tracker_retention_days', 30);
        $maxLogs = $this->settingsService->getInt('tracker_max_logs', 500);

        $deletedByAge = 0;
        if ($retentionDays > 0) {
            $threshold = (new \DateTimeImmutable())->modify(sprintf('-%d days', $retentionDays));
            $deletedByAge = $this->trackerLogRepository->deleteOlderThan($threshold);
        }

        $deletedByLimit = $maxLogs > 0 ? $this->trackerLogRepository->deleteBeyondLimit($maxLogs) : 0;

        $io->success(sprintf(
            'Takip paneli temizliği tamamlandı: %d günden eski %d kayıt, azami %d sınırını aşan %d kayıt silindi.',
            $retentionDays,
            $deletedByAge,
            $maxLogs,
            $deletedByLimit
        ));

        return Command::SUCCESS;
    }
}
