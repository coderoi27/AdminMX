<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\Core\MetricRollupDaily;
use App\Entity\Core\EventLog;
use Doctrine\ORM\EntityManagerInterface;

final class EventRollupService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function rollupDay(\DateTimeImmutable $date): int
    {
        $day = $date->setTime(0, 0);
        $nextDay = $day->modify('+1 day');

        /** @var list<array{event_name: string, source_app: string, event_count: int|string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('event.eventName AS event_name, event.sourceApp AS source_app, COUNT(event.id) AS event_count')
            ->from(EventLog::class, 'event')
            ->where('event.occurredAt >= :day')
            ->andWhere('event.occurredAt < :nextDay')
            ->groupBy('event.eventName')
            ->addGroupBy('event.sourceApp')
            ->setParameter('day', $day)
            ->setParameter('nextDay', $nextDay)
            ->getQuery()
            ->getArrayResult();

        $this->entityManager->createQueryBuilder()
            ->delete(MetricRollupDaily::class, 'rollup')
            ->where('rollup.rollupDate = :day')
            ->setParameter('day', $day)
            ->getQuery()
            ->execute();

        $written = 0;
        foreach ($rows as $row) {
            $rollup = (new MetricRollupDaily())
                ->setRollupDate($day)
                ->setEventName((string) $row['event_name'])
                ->setSourceApp((string) $row['source_app']);

            $rollup
                ->setEventCount((int) $row['event_count'])
                ->setComputedAt(new \DateTimeImmutable());

            $this->entityManager->persist($rollup);
            $written += 1;
        }

        $this->entityManager->flush();

        return $written;
    }
}
