<?php

declare(strict_types=1);

namespace App\Service\SystemExec;

use App\Service\CacheManagerService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class SystemExecService
{
    private const OUTPUT_TRUNCATE_LENGTH = 20000;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly CacheManagerService $cacheManagerService,
    ) {
    }

    /** @return array{success: bool, exitCode: ?int, output: string, durationMs: int} */
    public function run(SystemExecCommand $command): array
    {
        $start = microtime(true);

        if ($command === SystemExecCommand::CacheClear) {
            $report = $this->cacheManagerService->clearAndReport();

            return [
                'success' => $report['success'],
                'exitCode' => $report['success'] ? 0 : 1,
                'output' => $this->formatCacheReport($report),
                'durationMs' => $this->elapsedMs($start),
            ];
        }

        $process = $this->buildProcess($command);

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            return [
                'success' => false,
                'exitCode' => null,
                'output' => 'Zaman aşımı: ' . $e->getMessage(),
                'durationMs' => $this->elapsedMs($start),
            ];
        }

        $output = $this->truncate($process->getOutput() . "\n" . $process->getErrorOutput());

        return [
            'success' => $process->isSuccessful(),
            'exitCode' => $process->getExitCode(),
            'output' => $output,
            'durationMs' => $this->elapsedMs($start),
        ];
    }

    /** @return array{success: bool, exitCode: ?int, output: string, durationMs: int} */
    public function migrationsDryRun(): array
    {
        $start = microtime(true);
        $process = new Process(
            [$this->resolvePhpCliBinary(), $this->projectDir . '/app/bin/console', 'doctrine:migrations:migrate', '--dry-run', '--no-interaction'],
            $this->projectDir,
            null,
            null,
            120,
        );

        $process->run();

        return [
            'success' => $process->isSuccessful(),
            'exitCode' => $process->getExitCode(),
            'output' => $this->truncate($process->getOutput() . "\n" . $process->getErrorOutput()),
            'durationMs' => $this->elapsedMs($start),
        ];
    }

    private function buildProcess(SystemExecCommand $command): Process
    {
        return match ($command) {
            SystemExecCommand::ComposerInstall => new Process(
                ['composer', 'install', '--no-interaction', '--optimize-autoloader'],
                $this->projectDir,
                null,
                null,
                300,
            ),
            SystemExecCommand::ComposerUpdate => new Process(
                ['composer', 'update', '--no-interaction', '--optimize-autoloader'],
                $this->projectDir,
                null,
                null,
                600,
            ),
            SystemExecCommand::MigrationsMigrate => new Process(
                [$this->resolvePhpCliBinary(), $this->projectDir . '/app/bin/console', 'doctrine:migrations:migrate', '--no-interaction'],
                $this->projectDir,
                null,
                null,
                120,
            ),
            SystemExecCommand::NpmBuild => Process::fromShellCommandline(
                'npm run build',
                $this->projectDir . '/app',
                null,
                null,
                600,
            ),
            SystemExecCommand::CacheClear => throw new \LogicException('CacheClear in-process olarak ele alınır, Process gerektirmez.'),
        };
    }

    /** @param array{success: bool, message: string, cleared: list<string>, details: list<string>, warnings: list<string>} $report */
    private function formatCacheReport(array $report): string
    {
        $lines = [$report['message']];
        if ($report['cleared'] !== []) {
            $lines[] = 'Temizlendi: ' . implode(', ', $report['cleared']);
        }
        foreach ($report['details'] as $detail) {
            $lines[] = '  ' . $detail;
        }
        foreach ($report['warnings'] as $warning) {
            $lines[] = 'Uyarı: ' . $warning;
        }

        return implode("\n", $lines);
    }

    private function truncate(string $output): string
    {
        $output = trim($output);
        if (mb_strlen($output) <= self::OUTPUT_TRUNCATE_LENGTH) {
            return $output;
        }

        return '... (kırpıldı) ...' . "\n" . mb_substr($output, -self::OUTPUT_TRUNCATE_LENGTH);
    }

    private function elapsedMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }

    /**
     * Bu servis admin panelinden (php-fpm worker'ı içinde) çalıştığında PHP_BINARY
     * "php-fpm" binary'sini gösterir — console komutlarını çalıştıramaz. CLI SAPI'de
     * (örn. cron/artisan benzeri kullanım) PHP_BINARY zaten doğru "php" yolunu verir,
     * o durumda değiştirmeye gerek yok.
     */
    private function resolvePhpCliBinary(): string
    {
        if (PHP_SAPI === 'cli' && str_contains(basename(PHP_BINARY), 'php') && !str_contains(basename(PHP_BINARY), 'fpm')) {
            return PHP_BINARY;
        }

        return 'php';
    }
}
