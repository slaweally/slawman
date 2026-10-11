<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Repository\TrackerLogRepository;
use App\Service\SettingsService;
use App\Service\TrackerLogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class TrackerController extends AbstractController
{
    public function __construct(
        private readonly TrackerLogRepository $trackerLogRepository,
        private readonly SettingsService $settingsService,
        private readonly TrackerLogService $trackerLogService,
    ) {
    }

    #[Route('/tracker-feed', name: 'front_tracker_feed', methods: ['GET'])]
    public function feed(Request $request): JsonResponse
    {
        if (!$this->settingsService->isTruthy('tracker_enabled')) {
            return $this->json(['items' => [], 'hasMore' => false]);
        }

        $offset = max(0, $request->query->getInt('offset', 0));
        $pageSize = $this->settingsService->getInt('tracker_page_size', 20);

        $logs = $this->trackerLogRepository->findPage($pageSize, $offset);
        $total = $this->trackerLogRepository->countAll();

        return $this->json([
            'items' => array_map(fn ($log): array => [
                'messageHtml' => $this->trackerLogService->renderMessageWithLink($log),
                'time' => $log->getCreatedAt()->format('d.m.Y H:i'),
                'type' => $log->getEventType(),
            ], $logs),
            'hasMore' => ($offset + count($logs)) < $total,
        ]);
    }
}
