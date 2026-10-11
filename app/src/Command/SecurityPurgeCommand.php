<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\SecurityAlertRepository;
use App\Service\SettingsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `waf_auto_purge_days` ayarına göre uzun süredir görülmemiş (last_seen_at)
 * güvenlik alarmı kayıtlarını siler. Aggregation sayesinde tablo zaten küçük
 * kalır; bu komut yalnızca artık aktif olmayan eski IP/tehdit kayıtlarını
 * temizler. Cron ile günlük çalıştırılmak üzere tasarlanmıştır.
 */
#[AsCommand(name: 'app:security:purge', description: 'Eski güvenlik alarmı kayıtlarını ayarlanan saklama süresine göre temizler')]
class SecurityPurgeCommand extends Command
{
    public function __construct(
        private readonly SecurityAlertRepository $securityAlertRepository,
        private readonly SettingsService $settingsService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Ayarlardaki waf_auto_purge_days değerini geçersiz kılar')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Sadece silinecek kayıt sayısını gösterir, silmez');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = $input->getOption('days') !== null
            ? max(0, (int) $input->getOption('days'))
            : max(0, $this->settingsService->getInt('waf_auto_purge_days', 30));

        if ($days <= 0) {
            $io->note('waf_auto_purge_days 0 (veya negatif) — otomatik temizlik kapalı, işlem yapılmadı.');

            return Command::SUCCESS;
        }

        $threshold = new \DateTimeImmutable(sprintf('-%d days', $days));

        if ($input->getOption('dry-run')) {
            $io->info(sprintf(
                'Dry-run: %s tarihinden eski (son görülme) kayıtlar silinecekti. Gerçek silme yapılmadı.',
                $threshold->format('Y-m-d H:i:s')
            ));

            return Command::SUCCESS;
        }

        $deleted = $this->securityAlertRepository->deleteOlderThan($threshold);

        $io->success(sprintf(
            '%d günden eski %d güvenlik alarmı kaydı silindi (eşik: %s).',
            $days,
            $deleted,
            $threshold->format('Y-m-d H:i:s')
        ));

        return Command::SUCCESS;
    }
}
