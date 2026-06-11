<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Core\MetricRollupDaily;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/analytics', name: 'admin_analytics_')]
final class AnalyticsDashboardController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $since = new \DateTimeImmutable('-14 days');
        $rollups = $entityManager->createQueryBuilder()
            ->select('rollup')
            ->from(MetricRollupDaily::class, 'rollup')
            ->where('rollup.rollupDate >= :since')
            ->setParameter('since', $since)
            ->orderBy('rollup.rollupDate', 'ASC')
            ->addOrderBy('rollup.eventName', 'ASC')
            ->getQuery()
            ->getResult();

        $series = [];
        $totals = [];
        foreach ($rollups as $rollup) {
            if (!$rollup instanceof MetricRollupDaily) {
                continue;
            }

            $dateKey = $rollup->getRollupDate()->format('Y-m-d');
            $eventName = $rollup->getEventName();
            $series[$dateKey][$eventName] = ($series[$dateKey][$eventName] ?? 0) + $rollup->getEventCount();
            $totals[$eventName] = ($totals[$eventName] ?? 0) + $rollup->getEventCount();
        }

        ksort($series);
        arsort($totals);

        return $this->render('admin/analytics/index.html.twig', [
            'series' => $series,
            'totals' => $totals,
            'days' => array_keys($series),
            'top_events' => array_slice(array_keys($totals), 0, 8),
            'cron_command' => 'php bin/console app:analytics:rollup-daily --date=yesterday --env=prod',
        ]);
    }
}
