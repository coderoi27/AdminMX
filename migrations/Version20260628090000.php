<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260628090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds managed visual icon URL for location categories.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE location_categories ADD icon_asset_url VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE location_categories DROP icon_asset_url');
    }
}
