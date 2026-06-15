<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\Core\DemoSeedBatch;
use App\Entity\Core\DemoSeedBatchItem;
use App\Entity\Core\MerchantLocation;
use Doctrine\ORM\EntityManagerInterface;

final class DemoSeedBatchLifecycleService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return array{batches:int, items:int, locations:int}
     */
    public function expireDueBatches(?\DateTimeImmutable $now = null, bool $flush = true): array
    {
        $now ??= new \DateTimeImmutable();
        $batches = $this->entityManager->createQueryBuilder()
            ->select('batch')
            ->from(DemoSeedBatch::class, 'batch')
            ->where('batch.status = :status')
            ->andWhere('batch.expiresAt IS NOT NULL')
            ->andWhere('batch.expiresAt <= :now')
            ->setParameter('status', DemoSeedBatch::STATUS_ACTIVE)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        $stats = ['batches' => 0, 'items' => 0, 'locations' => 0];
        foreach ($batches as $batch) {
            if (!$batch instanceof DemoSeedBatch) {
                continue;
            }

            $batchStats = $this->expireBatch($batch, $now, false);
            $stats['batches'] += $batchStats['batches'];
            $stats['items'] += $batchStats['items'];
            $stats['locations'] += $batchStats['locations'];
        }

        if ($flush) {
            $this->entityManager->flush();
        }

        return $stats;
    }

    /**
     * @return array{batches:int, items:int, locations:int}
     */
    public function expireBatch(DemoSeedBatch $batch, ?\DateTimeImmutable $now = null, bool $flush = true): array
    {
        $now ??= new \DateTimeImmutable();
        if (in_array($batch->getStatus(), [DemoSeedBatch::STATUS_PURGED, DemoSeedBatch::STATUS_EXPIRED], true)) {
            return ['batches' => 0, 'items' => 0, 'locations' => 0];
        }

        $items = $this->itemsForBatch($batch);
        $itemsTouched = 0;
        $locationsTouched = 0;

        foreach ($items as $item) {
            if ($item->getStatus() === DemoSeedBatchItem::STATUS_PURGED) {
                continue;
            }

            if ($item->getStatus() !== DemoSeedBatchItem::STATUS_EXPIRED) {
                $item
                    ->setStatus(DemoSeedBatchItem::STATUS_EXPIRED)
                    ->setExpiredAt($now)
                    ->setNotes($this->appendNote($item->getNotes(), sprintf('Expirado automáticamente el %s.', $now->format(DATE_ATOM))));
                $itemsTouched += 1;
            }

            $location = $item->getMerchantLocation();
            if ($this->hideDemoLocation($location)) {
                $locationsTouched += 1;
            }
        }

        $batch->setStatus(DemoSeedBatch::STATUS_EXPIRED);

        if ($flush) {
            $this->entityManager->flush();
        }

        return ['batches' => 1, 'items' => $itemsTouched, 'locations' => $locationsTouched];
    }

    /**
     * @return array{batches:int, items:int, locations:int}
     */
    public function purgeBatch(DemoSeedBatch $batch, ?\DateTimeImmutable $now = null, bool $flush = true): array
    {
        $now ??= new \DateTimeImmutable();
        if ($batch->getStatus() === DemoSeedBatch::STATUS_PURGED) {
            return ['batches' => 0, 'items' => 0, 'locations' => 0];
        }

        $items = $this->itemsForBatch($batch);
        $itemsTouched = 0;
        $locationsTouched = 0;

        foreach ($items as $item) {
            if ($item->getStatus() !== DemoSeedBatchItem::STATUS_PURGED) {
                $item
                    ->setStatus(DemoSeedBatchItem::STATUS_PURGED)
                    ->setPurgedAt($now)
                    ->setNotes($this->appendNote($item->getNotes(), sprintf('Purgado lógicamente el %s.', $now->format(DATE_ATOM))));
                $itemsTouched += 1;
            }

            $location = $item->getMerchantLocation();
            if ($this->archiveDemoLocation($location)) {
                $locationsTouched += 1;
            }
        }

        $batch
            ->setStatus(DemoSeedBatch::STATUS_PURGED)
            ->setPurgedAt($now);

        if ($flush) {
            $this->entityManager->flush();
        }

        return ['batches' => 1, 'items' => $itemsTouched, 'locations' => $locationsTouched];
    }

    /**
     * @return list<DemoSeedBatchItem>
     */
    private function itemsForBatch(DemoSeedBatch $batch): array
    {
        return $this->entityManager->getRepository(DemoSeedBatchItem::class)->findBy([
            'demoSeedBatch' => $batch,
        ]);
    }

    private function hideDemoLocation(MerchantLocation $location): bool
    {
        if ($location->getSourceType() !== MerchantLocation::SOURCE_TYPE_FAKE_SEED) {
            return false;
        }

        $changed = false;
        if ($location->getStatus() !== MerchantLocation::STATUS_INACTIVE) {
            $location->setStatus(MerchantLocation::STATUS_INACTIVE);
            $changed = true;
        }

        if ($location->getPublicationState() === MerchantLocation::PUBLICATION_STATE_ARCHIVED) {
            return $changed;
        }

        if ($location->getPublicationState() === MerchantLocation::PUBLICATION_STATE_PENDING_VISIBLE) {
            $location->setPublicationState(MerchantLocation::PUBLICATION_STATE_HIDDEN);
            $changed = true;
        } elseif ($location->getPublicationState() !== MerchantLocation::PUBLICATION_STATE_HIDDEN) {
            $location->setPublicationState(MerchantLocation::PUBLICATION_STATE_HIDDEN);
            $changed = true;
        }

        return $changed;
    }

    private function archiveDemoLocation(MerchantLocation $location): bool
    {
        if ($location->getSourceType() !== MerchantLocation::SOURCE_TYPE_FAKE_SEED) {
            return false;
        }

        $changed = false;
        if ($location->getStatus() !== MerchantLocation::STATUS_INACTIVE) {
            $location->setStatus(MerchantLocation::STATUS_INACTIVE);
            $changed = true;
        }

        if ($location->getPublicationState() === MerchantLocation::PUBLICATION_STATE_PENDING_VISIBLE) {
            $location->setPublicationState(MerchantLocation::PUBLICATION_STATE_HIDDEN);
            $changed = true;
        }

        if ($location->getPublicationState() !== MerchantLocation::PUBLICATION_STATE_ARCHIVED) {
            $location->setPublicationState(MerchantLocation::PUBLICATION_STATE_ARCHIVED);
            $changed = true;
        }

        return $changed;
    }

    private function appendNote(?string $current, string $note): string
    {
        $current = trim((string) $current);

        return $current === '' ? $note : $current . "\n" . $note;
    }
}
