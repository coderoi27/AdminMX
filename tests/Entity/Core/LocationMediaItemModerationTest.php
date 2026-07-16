<?php

declare(strict_types=1);

namespace App\Tests\Entity\Core;

use App\Entity\Core\LocationMediaItem;
use PHPUnit\Framework\TestCase;

final class LocationMediaItemModerationTest extends TestCase
{
    public function testApprovedModerationKeepsMediaActive(): void
    {
        $media = new LocationMediaItem();

        $media->moderate(LocationMediaItem::MODERATION_APPROVED, 'ok', 'Apta para publicarse');

        self::assertSame(LocationMediaItem::MODERATION_APPROVED, $media->getModerationStatus());
        self::assertTrue($media->isActive());
        self::assertSame('ok', $media->getModerationReasonCategory());
        self::assertSame('Apta para publicarse', $media->getModerationNotes());
        self::assertInstanceOf(\DateTimeImmutable::class, $media->getModeratedAt());
    }

    public function testRejectedOrQuarantinedModerationDisablesMedia(): void
    {
        $media = new LocationMediaItem();

        $media->moderate(LocationMediaItem::MODERATION_REJECTED, 'spam', 'Contenido basura');
        self::assertFalse($media->isActive());

        $media->moderate(LocationMediaItem::MODERATION_QUARANTINED, 'unsafe_url', 'Revisar URL');
        self::assertFalse($media->isActive());
    }

    public function testInvalidModerationStatusIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new LocationMediaItem())->moderate('unsafe');
    }
}
