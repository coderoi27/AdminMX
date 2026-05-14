<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260511120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds persisted daily metric rollups for alpha analytics.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE metric_rollups_daily (id INT AUTO_INCREMENT NOT NULL, rollup_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', event_name VARCHAR(120) NOT NULL, source_app VARCHAR(32) NOT NULL, event_count INT NOT NULL, computed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_metric_rollup_day_event_source (rollup_date, event_name, source_app), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE metric_rollups_daily');
    }
}
