<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\SubscriberRepository;
use App\Service\SubscriberService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/subscribers', name: 'admin_subscriber_')]
#[IsGranted('ROLE_ADMIN')]
class SubscriberController extends AbstractController
{
    public function __construct(
        private readonly SubscriberRepository $subscriberRepository,
        private readonly SubscriberService $subscriberService,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $pagination = $paginator->paginate(
            $this->subscriberRepository->createListQueryBuilder(),
            $request->query->getInt('page', 1),
            $request->query->getInt('per_page', 50)
        );

        return $this->render('admin/subscriber/index.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route('/import', name: 'import', methods: ['POST'])]
    public function import(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('subscriber_import', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Geçersiz token.');

            return $this->redirectToRoute('admin_subscriber_index');
        }

        $file = $request->files->get('csv');
        if ($file === null) {
            $this->addFlash('error', 'CSV dosyası seçilmedi.');

            return $this->redirectToRoute('admin_subscriber_index');
        }

        $content = (string) file_get_contents($file->getPathname());
        if ($content === '') {
            $this->addFlash('error', 'Dosya boş.');

            return $this->redirectToRoute('admin_subscriber_index');
        }

        $result = $this->subscriberService->importFromCsv($content);
        $this->addFlash(
            'success',
            sprintf(
                '%d abone eklendi, %d atlandı (zaten kayıtlı), %d geçersiz e-posta.',
                $result['added'],
                $result['skipped'],
                $result['invalid']
            )
        );

        return $this->redirectToRoute('admin_subscriber_index');
    }

    #[Route('/export', name: 'export')]
    public function export(): StreamedResponse
    {
        $subscribers = $this->subscriberRepository->findAll();

        return new StreamedResponse(function () use ($subscribers): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'is_active', 'subscribed_at']);
            foreach ($subscribers as $s) {
                fputcsv($out, [$s->getEmail(), $s->isActive() ? '1' : '0', $s->getSubscribedAt()?->format('Y-m-d H:i:s')]);
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="subscribers.csv"',
        ]);
    }
}
