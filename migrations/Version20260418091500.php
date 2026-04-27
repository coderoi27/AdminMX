<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418091500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds source_type to merchant locations for canonical feed contract versioning.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE merchant_locations ADD source_type VARCHAR(32) NOT NULL DEFAULT 'owner_registered' AFTER publication_state");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE merchant_locations DROP source_type');
    }
}
