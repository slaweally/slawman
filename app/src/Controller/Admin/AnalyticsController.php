<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\VisitorStatsService;
use App\Service\YandexMetrikaApiService;
use App\Service\YandexMetrikaOauthService;
use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/analytics', name: 'admin_analytics_')]
#[IsGranted('ROLE_ADMIN')]
class AnalyticsController extends AbstractController
{
    public function __construct(
        private readonly VisitorStatsService $visitorStatsService,
        private readonly YandexMetrikaOauthService $metrikaOauthService,
        private readonly YandexMetrikaApiService $metrikaApiService,
        private readonly SettingsService $settingsService,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(Request $request): Response
    {
        $now = new \DateTimeImmutable('today');
        $year = $request->query->getInt('year', (int) $now->format('Y'));
        $month = $request->query->getInt('month', (int) $now->format('n'));

        $report = $this->visitorStatsService->getAnalyticsReport($year, $month);
        $maxDaily = max(1, ...array_column($report['daily'], 'page_views'));
        $maxMonthly = max(1, ...array_column($report['monthly'], 'page_views'));

        return $this->render('admin/analytics/index.html.twig', [
            'report' => $report,
            'maxDaily' => $maxDaily,
            'maxMonthly' => $maxMonthly,
            'dashboardStats' => $this->visitorStatsService->getDashboardStats(),
        ]);
    }

    #[Route('/metrika', name: 'metrika')]
    public function metrika(Request $request): Response
    {
        $counterId = (int) $this->settingsService->get('metrika_counter_id', '');
        $isReady = $this->metrikaOauthService->isAuthorized() && $counterId > 0;

        if (!$isReady) {
            return $this->render('admin/analytics/metrika.html.twig', [
                'isReady' => false,
                'statistics' => null,
                'popularContent' => [],
                'referrers' => [],
                'searchTerms' => [],
            ]);
        }

        $end = new \DateTimeImmutable('today');
        $start = $end->modify('-6 days');

        return $this->render('admin/analytics/metrika.html.twig', [
            'isReady' => true,
            'statistics' => $this->metrikaApiService->getStatistics($counterId, $start, $end),
            'popularContent' => $this->metrikaApiService->getPopularContent($counterId, $start, $end),
            'referrers' => $this->metrikaApiService->getReferrers($counterId, $start, $end),
            'searchTerms' => $this->metrikaApiService->getSearchTerms($counterId, $start, $end),
        ]);
    }
}
