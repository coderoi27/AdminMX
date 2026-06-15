<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260613103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligns location claim request statuses with the canonical state machine.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE location_claim_requests SET status = 'submitted' WHERE status = 'pending'");
        $this->addSql("UPDATE location_claim_requests SET status = 'under_review' WHERE status = 'reviewing'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE location_claim_requests SET status = 'pending' WHERE status = 'submitted'");
        $this->addSql("UPDATE location_claim_requests SET status = 'reviewing' WHERE status = 'under_review'");
    }
}
