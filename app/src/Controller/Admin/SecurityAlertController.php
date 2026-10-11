<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\SecurityAlert;
use App\Repository\SecurityAlertRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/security-alerts', name: 'admin_security_alert_')]
#[IsGranted('ROLE_ADMIN')]
class SecurityAlertController extends AbstractController
{
    private const TYPE_LABELS = [
        SecurityAlert::TYPE_SQLI => 'SQL Injection',
        SecurityAlert::TYPE_XSS => 'XSS',
        SecurityAlert::TYPE_TRAVERSAL => 'Directory Traversal',
        SecurityAlert::TYPE_LOGIN_FAILED => 'Başarısız Giriş',
        SecurityAlert::TYPE_RATE_LIMIT => 'Rate Limit Aşımı',
    ];

    private const SEVERITY_LABELS = [
        SecurityAlert::SEVERITY_CRITICAL => 'Kritik',
        SecurityAlert::SEVERITY_HIGH => 'Yüksek',
        SecurityAlert::SEVERITY_MEDIUM => 'Orta',
    ];

    public function __construct(
        private readonly SecurityAlertRepository $securityAlertRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $type = (string) $request->query->get('type', '');
        $severity = (string) $request->query->get('severity', '');

        $qb = $this->securityAlertRepository->createFilteredQueryBuilder(
            $type !== '' ? $type : null,
            $severity !== '' ? $severity : null,
        );

        $since24h = new \DateTimeImmutable('-24 hours');
        $since7d = new \DateTimeImmutable('-7 days');

        return $this->render('admin/security_alert/index.html.twig', [
            'pagination' => $paginator->paginate($qb, $request->query->getInt('page', 1), 40),
            'typeLabels' => self::TYPE_LABELS,
            'severityLabels' => self::SEVERITY_LABELS,
            'activeType' => $type,
            'activeSeverity' => $severity,
            'count24h' => $this->securityAlertRepository->countSince($since24h),
            'countCritical24h' => $this->securityAlertRepository->countCriticalSince($since24h),
            'topOffenders' => $this->securityAlertRepository->findTopOffenders($since7d, 5),
        ]);
    }

    #[Route('/{id}/toggle-read', name: 'toggle_read', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleRead(Request $request, int $id): JsonResponse
    {
        if (!$this->isCsrfTokenValid('security_alert_toggle_read_' . $id, (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'error' => 'invalid_token'], 400);
        }

        $alert = $this->securityAlertRepository->find($id);
        if (!$alert instanceof SecurityAlert) {
            return $this->json(['ok' => false, 'error' => 'not_found'], 404);
        }

        $alert->setIsRead(!$alert->isRead());
        $this->entityManager->flush();

        return $this->json(['ok' => true, 'isRead' => $alert->isRead()]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, int $id): JsonResponse
    {
        if (!$this->isCsrfTokenValid('security_alert_delete_' . $id, (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'error' => 'invalid_token'], 400);
        }

        $alert = $this->securityAlertRepository->find($id);
        if ($alert instanceof SecurityAlert) {
            $this->entityManager->remove($alert);
            $this->entityManager->flush();
        }

        return $this->json(['ok' => true]);
    }
}
