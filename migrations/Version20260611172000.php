<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611172000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds operational evidence fields to location claim requests.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE location_claim_requests ADD evidence_links_json JSON DEFAULT NULL, ADD review_checklist_json JSON DEFAULT NULL, ADD review_notes LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE location_claim_requests DROP evidence_links_json, DROP review_checklist_json, DROP review_notes');
    }
}
