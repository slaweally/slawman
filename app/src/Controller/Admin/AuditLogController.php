<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\AuditLogRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/audit-log', name: 'admin_audit_')]
#[IsGranted('ROLE_ADMIN')]
class AuditLogController extends AbstractController
{
    public function __construct(private readonly AuditLogRepository $auditLogRepository)
    {
    }

    #[Route('', name: 'index')]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $qb = $this->auditLogRepository->createQueryBuilder('a')->orderBy('a.createdAt', 'DESC');
        if ($action = $request->query->get('action')) {
            $qb->andWhere('a.action = :action')->setParameter('action', $action);
        }

        return $this->render('admin/audit/index.html.twig', [
            'pagination' => $paginator->paginate($qb, $request->query->getInt('page', 1), 50),
        ]);
    }
}
