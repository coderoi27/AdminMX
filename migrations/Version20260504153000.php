<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260504153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add external source key to merchant locations and enrich category media/regional fields.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE merchant_locations ADD external_source_key VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE location_categories ADD default_photo_url VARCHAR(255) DEFAULT NULL, ADD cover_photo_url VARCHAR(255) DEFAULT NULL, ADD regional_strategy VARCHAR(32) DEFAULT NULL, ADD featured_region_scope VARCHAR(160) DEFAULT NULL, ADD google_place_type_mappings JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE merchant_locations DROP external_source_key');
        $this->addSql('ALTER TABLE location_categories DROP default_photo_url, DROP cover_photo_url, DROP regional_strategy, DROP featured_region_scope, DROP google_place_type_mappings');
    }
}
