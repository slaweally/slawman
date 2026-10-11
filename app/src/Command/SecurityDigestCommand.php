<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\SecurityAlertRepository;
use App\Service\AdminNotificationService;
use App\Service\SettingsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Son 24 saatteki tüm WAF/güvenlik olaylarının tek bir özet mailini gönderir.
 * Anlık mail (waf_notify_realtime_enabled) varsayılan kapalıyken bu, adminin
 * güvenlik durumundan haberdar olduğu tek kanaldır. `waf_notify_digest_enabled`
 * ayarı kapalıysa hiçbir şey yapmadan çıkar. Cron ile günde bir kez çalıştırılmalıdır.
 */
#[AsCommand(name: 'app:security:daily-digest', description: 'Son 24 saatteki güvenlik alarmlarının özetini tek mailde gönderir')]
class SecurityDigestCommand extends Command
{
    public function __construct(
        private readonly SecurityAlertRepository $securityAlertRepository,
        private readonly AdminNotificationService $adminNotificationService,
        private readonly SettingsService $settingsService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->settingsService->isTruthy('waf_notify_digest_enabled')) {
            $io->note('waf_notify_digest_enabled kapalı — özet maili gönderilmedi.');

            return Command::SUCCESS;
        }

        $since = new \DateTimeImmutable('-24 hours');
        $totalHits = $this->securityAlertRepository->countSince($since);

        if ($totalHits === 0) {
            $io->info('Son 24 saatte güvenlik alarmı yok — özet maili atlandı.');

            return Command::SUCCESS;
        }

        $bannedCount = $this->securityAlertRepository->countBansSince($since);
        $byType = $this->securityAlertRepository->countByTypeSince($since);
        $topOffenders = $this->securityAlertRepository->findTopOffenders($since, 10);

        $sent = $this->adminNotificationService->notifySecurityDigest($since, $totalHits, $bannedCount, $byType, $topOffenders);

        if ($sent) {
            $io->success(sprintf('Güvenlik özet maili gönderildi: %d istek, %d ban.', $totalHits, $bannedCount));
        } else {
            $io->warning('Güvenlik özet maili gönderilemedi (alıcı yok veya SMTP yapılandırılmamış).');
        }

        return Command::SUCCESS;
    }
}
