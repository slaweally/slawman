<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\VisitDaily;
use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use App\Repository\SubscriberRepository;
use App\Repository\TagRepository;
use App\Repository\VisitDailyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class VisitorStatsService
{
    public const VISITOR_COOKIE_NAME = 'vid';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VisitDailyRepository $visitDailyRepository,
        private readonly PostRepository $postRepository,
        private readonly CommentRepository $commentRepository,
        private readonly SubscriberRepository $subscriberRepository,
        private readonly TagRepository $tagRepository,
    ) {
    }

    /**
     * @return bool true ise bu istek yeni bir benzersiz ziyaretçi olarak sayıldı;
     *              çağıran taraf (listener) bu durumda vid çerezini set etmelidir.
     */
    public function track(Request $request): bool
    {
        if ($request->getMethod() !== Request::METHOD_GET) {
            return false;
        }

        if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return false;
        }

        $accept = $request->headers->get('Accept', '');
        if ($accept !== '' && !str_contains($accept, 'text/html') && !str_contains($accept, '*/*')) {
            return false;
        }

        $today = new \DateTimeImmutable('today');
        $isNewVisitor = $request->cookies->get(self::VISITOR_COOKIE_NAME) === null;

        $daily = $this->visitDailyRepository->findByDate($today);
        if ($daily === null) {
            $daily = new VisitDaily();
            $daily->setVisitDate($today);
            $this->entityManager->persist($daily);
        }

        $daily->incrementPageViews();

        if ($isNewVisitor) {
            $daily->incrementUniqueVisitors();
        }

        $this->entityManager->flush();

        return $isNewVisitor;
    }

    /** @return array<string, int|list<array{date: string, label: string, page_views: int, unique_visitors: int}>> */
    public function getDashboardStats(): array
    {
        $today = new \DateTimeImmutable('today');
        $todayRow = $this->visitDailyRepository->findByDate($today);

        return [
            'today_page_views' => $todayRow?->getPageViews() ?? 0,
            'today_unique_visitors' => $todayRow?->getUniqueVisitors() ?? 0,
            'week_page_views' => $this->visitDailyRepository->sumPageViewsSince($today->modify('-6 days')),
            'week_unique_visitors' => $this->visitDailyRepository->sumUniqueVisitorsSince($today->modify('-6 days')),
            'month_page_views' => $this->sumCurrentMonthPageViews($today),
            'month_unique_visitors' => $this->sumCurrentMonthUniqueVisitors($today),
            'chart' => $this->buildCurrentMonthChart($today),
            'chart_month_label' => $today->format('m.Y'),
            'chart_days' => (int) $today->format('t'),
            'totals' => $this->visitDailyRepository->sumTotals(),
        ];
    }

    /** @return array<string, int> */
    public function getPublicStats(): array
    {
        $today = new \DateTimeImmutable('today');
        $todayRow = $this->visitDailyRepository->findByDate($today);
        $totals = $this->visitDailyRepository->sumTotals();

        return [
            'today_visitors' => $todayRow?->getUniqueVisitors() ?? 0,
            'today_views' => $todayRow?->getPageViews() ?? 0,
            'total_views' => $totals['page_views'],
            'total_visitors' => $totals['unique_visitors'],
            'posts' => $this->postRepository->countActive(),
            'comments' => $this->commentRepository->count([]),
            'subscribers' => $this->subscriberRepository->count([]),
        ];
    }

    /**
     * @return array{
     *     year: int,
     *     month: int,
     *     years: list<int>,
     *     yearly: list<array{year: int, page_views: int, unique_visitors: int}>,
     *     monthly: list<array{month: int, label: string, page_views: int, unique_visitors: int}>,
     *     daily: list<array{date: string, label: string, page_views: int, unique_visitors: int}>,
     *     top_views: list<\App\Entity\Post>,
     *     top_comments: list<\App\Entity\Post>,
     *     popular_tags: list<\App\Entity\Tag>,
     *     totals: array{page_views: int, unique_visitors: int}
     * }
     */
    public function getAnalyticsReport(int $year, int $month): array
    {
        $now = new \DateTimeImmutable('today');
        $year = max(2020, min((int) $now->format('Y'), $year));
        $month = max(1, min(12, $month));

        $allRows = $this->visitDailyRepository->findAllOrdered();
        $years = [];
        $yearlyMap = [];
        $monthlyMap = [];

        foreach ($allRows as $row) {
            $date = $row->getVisitDate();
            if ($date === null) {
                continue;
            }
            $y = (int) $date->format('Y');
            $m = (int) $date->format('n');
            $years[$y] = true;
            $yearlyMap[$y]['page_views'] = ($yearlyMap[$y]['page_views'] ?? 0) + $row->getPageViews();
            $yearlyMap[$y]['unique_visitors'] = ($yearlyMap[$y]['unique_visitors'] ?? 0) + $row->getUniqueVisitors();

            if ($y === $year) {
                $monthlyMap[$m]['page_views'] = ($monthlyMap[$m]['page_views'] ?? 0) + $row->getPageViews();
                $monthlyMap[$m]['unique_visitors'] = ($monthlyMap[$m]['unique_visitors'] ?? 0) + $row->getUniqueVisitors();
            }
        }

        if ($years === []) {
            $years[(int) $now->format('Y')] = true;
        }

        $yearKeys = array_keys($years);
        rsort($yearKeys);
        $yearly = [];
        foreach ($yearKeys as $y) {
            $yearly[] = [
                'year' => $y,
                'page_views' => $yearlyMap[$y]['page_views'] ?? 0,
                'unique_visitors' => $yearlyMap[$y]['unique_visitors'] ?? 0,
            ];
        }

        $monthly = [];
        for ($m = 1; $m <= 12; ++$m) {
            $monthly[] = [
                'month' => $m,
                'label' => sprintf('%02d', $m),
                'page_views' => $monthlyMap[$m]['page_views'] ?? 0,
                'unique_visitors' => $monthlyMap[$m]['unique_visitors'] ?? 0,
            ];
        }

        $daily = [];
        $monthRows = $this->visitDailyRepository->findByYearMonth($year, $month);
        $indexed = [];
        foreach ($monthRows as $row) {
            if ($row->getVisitDate() !== null) {
                $indexed[$row->getVisitDate()->format('Y-m-d')] = $row;
            }
        }

        $daysInMonth = (int) (new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');
        for ($d = 1; $d <= $daysInMonth; ++$d) {
            $key = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $row = $indexed[$key] ?? null;
            $daily[] = [
                'date' => $key,
                'label' => sprintf('%02d', $d),
                'page_views' => $row?->getPageViews() ?? 0,
                'unique_visitors' => $row?->getUniqueVisitors() ?? 0,
            ];
        }

        return [
            'year' => $year,
            'month' => $month,
            'years' => $yearKeys,
            'yearly' => $yearly,
            'monthly' => $monthly,
            'daily' => $daily,
            'top_views' => $this->postRepository->findTopByViewCount(10),
            'top_comments' => $this->postRepository->findTopByCommentCount(10),
            'popular_tags' => $this->tagRepository->findPopular(12),
            'totals' => $this->visitDailyRepository->sumTotals(),
        ];
    }

    /** @return list<array{date: string, label: string, show_label: bool, page_views: int, unique_visitors: int}> */
    private function buildCurrentMonthChart(\DateTimeImmutable $today): array
    {
        $year = (int) $today->format('Y');
        $month = (int) $today->format('n');
        $daysInMonth = (int) $today->format('t');
        $rows = $this->visitDailyRepository->findByYearMonth($year, $month);
        $indexed = [];
        foreach ($rows as $row) {
            if ($row->getVisitDate() !== null) {
                $indexed[$row->getVisitDate()->format('Y-m-d')] = $row;
            }
        }

        $series = [];
        for ($d = 1; $d <= $daysInMonth; ++$d) {
            $key = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $row = $indexed[$key] ?? null;
            $series[] = [
                'date' => $key,
                'label' => sprintf('%02d', $d),
                'show_label' => $d === 1 || $d % 5 === 0 || $d === $daysInMonth,
                'page_views' => $row?->getPageViews() ?? 0,
                'unique_visitors' => $row?->getUniqueVisitors() ?? 0,
            ];
        }

        return $series;
    }

    private function sumCurrentMonthPageViews(\DateTimeImmutable $today): int
    {
        $rows = $this->visitDailyRepository->findByYearMonth((int) $today->format('Y'), (int) $today->format('n'));
        $sum = 0;
        foreach ($rows as $row) {
            $sum += $row->getPageViews();
        }

        return $sum;
    }

    private function sumCurrentMonthUniqueVisitors(\DateTimeImmutable $today): int
    {
        $rows = $this->visitDailyRepository->findByYearMonth((int) $today->format('Y'), (int) $today->format('n'));
        $sum = 0;
        foreach ($rows as $row) {
            $sum += $row->getUniqueVisitors();
        }

        return $sum;
    }

    /** @return list<array{date: string, label: string, page_views: int, unique_visitors: int}> */
    private function buildChartSeries(int $days): array
    {
        $rows = $this->visitDailyRepository->findLastDays($days);
        $indexed = [];
        foreach ($rows as $row) {
            if ($row->getVisitDate() === null) {
                continue;
            }
            $key = $row->getVisitDate()->format('Y-m-d');
            $indexed[$key] = $row;
        }

        $series = [];
        $start = new \DateTimeImmutable(sprintf('-%d days', $days - 1));
        for ($i = 0; $i < $days; ++$i) {
            $date = $start->modify('+' . $i . ' days');
            $key = $date->format('Y-m-d');
            $row = $indexed[$key] ?? null;
            $series[] = [
                'date' => $key,
                'label' => $date->format('d.m'),
                'page_views' => $row?->getPageViews() ?? 0,
                'unique_visitors' => $row?->getUniqueVisitors() ?? 0,
            ];
        }

        return $series;
    }
}
