<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Core\MetricRollupDaily;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/analytics', name: 'admin_analytics_')]
final class AnalyticsDashboardController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $daysWindow = $request->query->getInt('days', 14);
        if (!in_array($daysWindow, [7, 14, 30], true)) {
            $daysWindow = 14;
        }

        $selectedSourceApp = trim($request->query->getString('source_app', ''));
        $since = (new \DateTimeImmutable(sprintf('-%d days', $daysWindow - 1)))->setTime(0, 0);

        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('rollup')
            ->from(MetricRollupDaily::class, 'rollup')
            ->where('rollup.rollupDate >= :since')
            ->setParameter('since', $since)
            ->orderBy('rollup.rollupDate', 'ASC')
            ->addOrderBy('rollup.eventName', 'ASC');

        if ($selectedSourceApp !== '') {
            $queryBuilder->andWhere('rollup.sourceApp = :sourceApp')->setParameter('sourceApp', $selectedSourceApp);
        }

        $rollups = $queryBuilder->getQuery()->getResult();

        $series = [];
        $totals = [];
        $sourceTotals = [];
        $groupTotals = $this->emptyBusinessGroups();
        $latestComputedAt = null;
        $latestRollupDate = null;

        foreach ($rollups as $rollup) {
            if (!$rollup instanceof MetricRollupDaily) {
                continue;
            }

            $dateKey = $rollup->getRollupDate()->format('Y-m-d');
            $eventName = $rollup->getEventName();
            $series[$dateKey][$eventName] = ($series[$dateKey][$eventName] ?? 0) + $rollup->getEventCount();
            $totals[$eventName] = ($totals[$eventName] ?? 0) + $rollup->getEventCount();
            $sourceTotals[$rollup->getSourceApp()] = ($sourceTotals[$rollup->getSourceApp()] ?? 0) + $rollup->getEventCount();
            $businessGroup = $this->businessGroupForEvent($eventName);
            $groupTotals[$businessGroup] = ($groupTotals[$businessGroup] ?? 0) + $rollup->getEventCount();

            if ($latestRollupDate === null || $rollup->getRollupDate() > $latestRollupDate) {
                $latestRollupDate = $rollup->getRollupDate();
            }

            if ($rollup->getComputedAt() !== null && ($latestComputedAt === null || $rollup->getComputedAt() > $latestComputedAt)) {
                $latestComputedAt = $rollup->getComputedAt();
            }
        }

        ksort($series);
        arsort($totals);
        arsort($sourceTotals);

        return $this->render('admin/analytics/index.html.twig', [
            'series' => $series,
            'totals' => $totals,
            'source_totals' => $sourceTotals,
            'group_totals' => $groupTotals,
            'days' => array_keys($series),
            'top_events' => array_slice(array_keys($totals), 0, 8),
            'available_days' => [7, 14, 30],
            'filters' => [
                'days' => $daysWindow,
                'source_app' => $selectedSourceApp,
            ],
            'latest_rollup_date' => $latestRollupDate,
            'latest_computed_at' => $latestComputedAt,
            'cron_command' => 'php bin/console app:analytics:rollup-daily --date=yesterday --env=prod --no-debug',
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function emptyBusinessGroups(): array
    {
        return [
            'Descubrimiento' => 0,
            'Intención' => 0,
            'Retención' => 0,
            'Cuenta y ubicación' => 0,
            'Claims' => 0,
            'Otros' => 0,
        ];
    }

    private function businessGroupForEvent(string $eventName): string
    {
        if (in_array($eventName, [
            'public_location_opened',
            'public_section_changed',
            'public_service_filter_changed',
            'public_saved_address_selected',
        ], true)) {
            return 'Descubrimiento';
        }

        if (in_array($eventName, [
            'public_directions_clicked',
            'public_whatsapp_clicked',
            'public_link_clicked',
        ], true)) {
            return 'Intención';
        }

        if (in_array($eventName, [
            'public_favorite_added',
            'public_favorite_removed',
        ], true)) {
            return 'Retención';
        }

        if (in_array($eventName, [
            'public_address_saved',
            'public_address_removed',
        ], true)) {
            return 'Cuenta y ubicación';
        }

        if ($eventName === 'public_claim_started') {
            return 'Claims';
        }

        return 'Otros';
    }
}
