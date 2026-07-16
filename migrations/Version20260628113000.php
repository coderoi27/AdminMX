<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260628113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds operational moderation fields to canonical location media items.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE location_media_items ADD moderation_status VARCHAR(32) NOT NULL DEFAULT 'approved', ADD moderation_reason_category VARCHAR(64) DEFAULT NULL, ADD moderation_notes LONGTEXT DEFAULT NULL, ADD moderated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE INDEX IDX_LOCATION_MEDIA_MODERATION_STATUS ON location_media_items (moderation_status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_LOCATION_MEDIA_MODERATION_STATUS ON location_media_items');
        $this->addSql('ALTER TABLE location_media_items DROP moderation_status, DROP moderation_reason_category, DROP moderation_notes, DROP moderated_at');
    }
}
