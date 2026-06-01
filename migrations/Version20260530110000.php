<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds typed Google Places blacklist rules so entries can target exact IDs or name keywords.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE google_place_blacklist ADD match_type VARCHAR(32) NOT NULL DEFAULT 'place_id' AFTER id");
        $this->addSql('DROP INDEX UNIQ_70A13471C19A213A ON google_place_blacklist');
        $this->addSql('CREATE UNIQUE INDEX uniq_google_place_blacklist_rule ON google_place_blacklist (match_type, external_source_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_google_place_blacklist_rule ON google_place_blacklist');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_70A13471C19A213A ON google_place_blacklist (external_source_key)');
        $this->addSql('ALTER TABLE google_place_blacklist DROP match_type');
    }
}
