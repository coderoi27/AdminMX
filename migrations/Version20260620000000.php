<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Modifica location_claim_requests para flujo Assisted.
 */
final class Version20260620000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hacer claimant_name y email nullables, añadir submission_mode a location_claim_requests';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE location_claim_requests CHANGE claimant_name claimant_name VARCHAR(120) DEFAULT NULL, CHANGE email email VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE location_claim_requests ADD submission_mode VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE location_claim_requests DROP submission_mode');
        $this->addSql('ALTER TABLE location_claim_requests CHANGE claimant_name claimant_name VARCHAR(120) NOT NULL, CHANGE email email VARCHAR(180) NOT NULL');
    }
}
