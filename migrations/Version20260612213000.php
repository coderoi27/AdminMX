<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds operational takedown requests for canonical locations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE location_takedown_requests (id INT AUTO_INCREMENT NOT NULL, location_id INT NOT NULL, status VARCHAR(32) NOT NULL, reason_category VARCHAR(64) NOT NULL, reason_text LONGTEXT NOT NULL, reported_by_type VARCHAR(32) NOT NULL, reported_by_email VARCHAR(180) DEFAULT NULL, resolution_action VARCHAR(32) NOT NULL, resolution_notes LONGTEXT DEFAULT NULL, resolved_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_9E1D8E6464D218E (location_id), INDEX IDX_9E1D8E647B00651C (status), INDEX IDX_9E1D8E648B8E8428 (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE location_takedown_requests ADD CONSTRAINT FK_9E1D8E6464D218E FOREIGN KEY (location_id) REFERENCES merchant_locations (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE location_takedown_requests DROP FOREIGN KEY FK_9E1D8E6464D218E');
        $this->addSql('DROP TABLE location_takedown_requests');
    }
}
