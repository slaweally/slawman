<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\AuditLogService;
use App\Service\MediaUploadService;
use App\Service\Import\WordPressImportRunnerService;
use App\Service\WordPressImporterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/import', name: 'admin_import_')]
#[IsGranted('ROLE_ADMIN')]
class ImportController extends AbstractController
{
    public function __construct(
        private readonly WordPressImporterService $importer,
        private readonly WordPressImportRunnerService $runner,
        private readonly AuditLogService $auditLog,
        private readonly MediaUploadService $mediaUploadService,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    #[Route('/wordpress', name: 'wordpress', methods: ['GET', 'POST'])]
    public function wordpress(Request $request, SessionInterface $session): Response
    {
        $action = (string) $request->request->get('action', '');

        if ($request->isMethod('POST')) {
            $tokenName = $action === 'rollback' ? 'wp_rollback' : 'wp_import';
            if (!$this->isCsrfTokenValid($tokenName, (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Geçersiz token.');

                return $this->redirectToRoute('admin_import_wordpress');
            }

            $user = $this->getUser();
            if (!$user instanceof User) {
                throw $this->createAccessDeniedException();
            }

            try {
                if ($action === 'rollback') {
                    $batchId = (string) $request->request->get('batch_id', '');
                    $rollback = $this->importer->rollback($batchId);
                    if ($rollback['success']) {
                        $this->auditLog->log($user, 'import_rollback', 'WordPress', 0);
                        $this->addFlash('success', $rollback['message']);
                    } else {
                        $this->addFlash('error', $rollback['message']);
                    }

                    return $this->redirectToRoute('admin_import_wordpress');
                }

                if ($action === 'preview') {
                    /** @var UploadedFile|null $file */
                    $file = $request->files->get('wxr_file');
                    if (!$file instanceof UploadedFile) {
                        throw new \RuntimeException('WXR dosyası seçilmedi.');
                    }

                    $stored = $this->storeUploadedFile($file);
                    $previewData = $this->importer->preview($stored);
                    $session->set('wp_import_preview', [
                        'file' => $stored,
                        'data' => $previewData,
                        'filename' => $file->getClientOriginalName(),
                    ]);
                    $session->remove('wp_import_active_job');

                    if (!$previewData['valid']) {
                        $this->addFlash('error', 'Dosya doğrulaması başarısız. Hataları inceleyin.');
                    } else {
                        $this->addFlash('info', 'Önizleme hazır. İçe aktarmadan önce sonuçları kontrol edin.');
                    }

                    return $this->redirectToRoute('admin_import_wordpress');
                }
            } catch (\Throwable $e) {
                $this->addFlash('error', 'İşlem başarısız: ' . $e->getMessage());
            }
        }

        return $this->render('admin/import/wordpress.html.twig', [
            'preview' => $session->get('wp_import_preview'),
            'batches' => $this->importer->listBatches(),
            'activeJobId' => $session->get('wp_import_active_job'),
        ]);
    }

    #[Route('/wordpress/job/start', name: 'wordpress_job_start', methods: ['POST'])]
    public function startJob(Request $request, SessionInterface $session): JsonResponse
    {
        if (!$this->isCsrfTokenValid('wp_import', (string) $request->request->get('_token'))) {
            return $this->json(['error' => 'Geçersiz token.'], 400);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Yetkisiz.'], 403);
        }

        $preview = $session->get('wp_import_preview');
        $stored = (string) ($preview['file'] ?? '');
        if ($stored === '' || !is_readable($stored)) {
            return $this->json(['error' => 'Önce WXR dosyasını yükleyip önizleme yapın.'], 400);
        }

        $chunkSize = (int) $request->request->get('chunk_size', 10);
        $downloadMedia = $request->request->has('download_media');

        try {
            $start = $this->runner->startJob($stored, $user, $chunkSize, $downloadMedia);
            $session->set('wp_import_active_job', $start['job_id']);

            return $this->json([
                'job_id' => $start['job_id'],
                'batch_id' => $start['batch_id'],
                'total_posts' => $start['total_posts'],
                'total_media' => $start['total_media'],
                'chunk_size' => $start['chunk_size'],
                'skipped' => $start['skipped'],
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/wordpress/job/{jobId}/step', name: 'wordpress_job_step', methods: ['POST'])]
    public function runStep(string $jobId, Request $request, SessionInterface $session): JsonResponse
    {
        if (!$this->isCsrfTokenValid('wp_import', (string) $request->request->get('_token'))) {
            return $this->json(['error' => 'Geçersiz token.'], 400);
        }

        if ($session->get('wp_import_active_job') !== $jobId) {
            return $this->json(['error' => 'Geçersiz veya süresi dolmuş import işi.'], 400);
        }

        try {
            $step = $this->runner->runStep($jobId);

            if ($step['done'] ?? false) {
                $user = $this->getUser();
                if ($user instanceof User) {
                    $this->auditLog->log($user, 'import', 'WordPress', 0);
                }
                $session->remove('wp_import_preview');
                $session->remove('wp_import_active_job');
            }

            return $this->json($step);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage(), 'done' => false], 500);
        }
    }

    #[Route('/wordpress/job/{jobId}/cancel', name: 'wordpress_job_cancel', methods: ['POST'])]
    public function cancelJob(string $jobId, Request $request, SessionInterface $session): JsonResponse
    {
        if (!$this->isCsrfTokenValid('wp_import', (string) $request->request->get('_token'))) {
            return $this->json(['error' => 'Geçersiz token.'], 400);
        }

        try {
            $result = $this->runner->cancelJob($jobId);
            $session->remove('wp_import_active_job');

            return $this->json($result);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage(), 'success' => false], 500);
        }
    }

    private function storeUploadedFile(UploadedFile $file): string
    {
        $this->mediaUploadService->validateWxrFile($file);

        $dir = $this->projectDir . '/slw-content/import-temp';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Geçici dizin oluşturulamadı.');
        }

        $target = $dir . '/' . bin2hex(random_bytes(16)) . '.xml';
        $file->move(dirname($target), basename($target));

        return $target;
    }
}
