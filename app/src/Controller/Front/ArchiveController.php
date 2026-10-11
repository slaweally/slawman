<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Repository\PostRepository;
use App\Service\PermalinkService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ArchiveController extends AbstractController
{
    private const MONTH_LABELS = [
        1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran',
        7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık',
    ];

    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly PermalinkService $permalinkService,
    ) {
    }

    #[Route('/arsiv', name: 'front_archive')]
    public function index(): Response
    {
        $counts = $this->postRepository->getArchiveCounts();

        $years = [];
        foreach ($counts as $year => $months) {
            $yearTotal = array_sum($months);
            $monthRows = [];
            foreach ($months as $month => $total) {
                $monthRows[] = [
                    'month' => $month,
                    'label' => self::MONTH_LABELS[$month] ?? (string) $month,
                    'total' => $total,
                ];
            }

            $years[] = [
                'year' => $year,
                'total' => $yearTotal,
                'months' => $monthRows,
            ];
        }

        return $this->render('front/archive/index.html.twig', [
            'years' => $years,
            'monthLabels' => self::MONTH_LABELS,
        ]);
    }

    #[Route('/arsiv/{year}/{month}', name: 'front_archive_month', requirements: ['year' => '\d{4}', 'month' => '\d{1,2}'], methods: ['GET'])]
    public function month(int $year, int $month): JsonResponse
    {
        if ($month < 1 || $month > 12) {
            return $this->json(['items' => []]);
        }

        $rows = $this->postRepository->findArchiveItemsForMonth($year, $month);

        // Sadece bu ayın az sayıdaki kaydı entity olarak çekilir (permalink üretimi için).
        $ids = array_column($rows, 'id');
        $posts = $ids === [] ? [] : $this->postRepository->findBy(['id' => $ids]);
        $pathsById = [];
        foreach ($posts as $post) {
            $pathsById[$post->getId()] = $this->permalinkService->buildPostPath($post);
        }

        $items = array_map(fn (array $row): array => [
            'title' => $row['title'] ?: $row['slug'],
            'url' => $pathsById[$row['id']] ?? '#',
            'type' => $row['type'],
            'publishedAt' => $row['publishedAt'],
        ], $rows);

        return $this->json(['items' => $items]);
    }
}
