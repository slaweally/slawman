<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\AuditLogService;
use App\Service\SystemExec\PreflightBackupService;
use App\Service\SystemExec\SystemExecCommand;
use App\Service\SystemExec\SystemExecService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/system', name: 'admin_system_')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class SystemController extends AbstractController
{
    public function __construct(
        private readonly SystemExecService $systemExecService,
        private readonly PreflightBackupService $preflightBackupService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(): Response
    {
        return $this->render('admin/system/exec.html.twig', [
            'commands' => SystemExecCommand::cases(),
        ]);
    }

    #[Route('/run', name: 'run', methods: ['POST'])]
    public function run(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('system_exec_run', (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'error' => 'Geçersiz güvenlik tokeni.'], 400);
        }

        $command = SystemExecCommand::tryFrom((string) $request->request->get('command'));
        if ($command === null) {
            return $this->json(['ok' => false, 'error' => 'Geçersiz komut.'], 400);
        }

        if ($command === SystemExecCommand::MigrationsMigrate) {
            return $this->json(['ok' => false, 'error' => 'Migration için önce önizleme yapılmalı.'], 400);
        }

        set_time_limit(0);

        if ($command->requiresPreflightBackup()) {
            $timestamp = date('Y-m-d_His');
            $backup = $this->preflightBackupService->backupLockFiles($timestamp);
            if (!$backup['ok']) {
                $this->logRun($command, false, null, 0, 'Yedek başarısız: ' . $backup['message']);

                return $this->json(['ok' => false, 'error' => 'Yedek alınamadı: ' . $backup['message']], 500);
            }
        }

        $result = $this->systemExecService->run($command);
        $this->logRun($command, $result['success'], $result['exitCode'], $result['durationMs'], $result['output']);

        return $this->json([
            'ok' => $result['success'],
            'exitCode' => $result['exitCode'],
            'output' => $result['output'],
            'durationMs' => $result['durationMs'],
        ]);
    }

    #[Route('/migrate/preview', name: 'migrate_preview', methods: ['POST'])]
    public function migratePreview(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('system_migrate_preview', (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'error' => 'Geçersiz güvenlik tokeni.'], 400);
        }

        $result = $this->systemExecService->migrationsDryRun();

        $this->logRun(SystemExecCommand::MigrationsMigrate, $result['success'], $result['exitCode'], $result['durationMs'], '[DRY-RUN] ' . $result['output']);

        return $this->json([
            'ok' => $result['success'],
            'exitCode' => $result['exitCode'],
            'output' => $result['output'],
            'durationMs' => $result['durationMs'],
        ]);
    }

    #[Route('/migrate/run', name: 'migrate_run', methods: ['POST'])]
    public function migrateRun(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('system_migrate_run', (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'error' => 'Geçersiz güvenlik tokeni.'], 400);
        }

        $timestamp = date('Y-m-d_His');
        $backup = $this->preflightBackupService->backupDatabase($timestamp);
        if (!$backup['ok']) {
            $this->logRun(SystemExecCommand::MigrationsMigrate, false, null, 0, 'DB yedeği başarısız, migration engellendi: ' . $backup['message']);

            return $this->json(['ok' => false, 'error' => 'DB yedeği alınamadı, migration çalıştırılmadı: ' . $backup['message']], 500);
        }

        $result = $this->systemExecService->run(SystemExecCommand::MigrationsMigrate);
        $this->logRun(SystemExecCommand::MigrationsMigrate, $result['success'], $result['exitCode'], $result['durationMs'], $result['output']);

        return $this->json([
            'ok' => $result['success'],
            'exitCode' => $result['exitCode'],
            'output' => $result['output'],
            'durationMs' => $result['durationMs'],
            'backupPath' => $backup['path'],
        ]);
    }

    private function logRun(SystemExecCommand $command, bool $success, ?int $exitCode, int $durationMs, string $output): void
    {
        $user = $this->getUser();
        $this->auditLogService->log(
            $user instanceof User ? $user : null,
            'system_exec_' . $command->value,
            'SystemExec',
            0,
            [],
            [
                'command' => $command->value,
                'exitCode' => $exitCode,
                'durationMs' => $durationMs,
                'success' => $success,
                'output_excerpt' => mb_substr($output, 0, 2000),
            ],
        );
    }
}
