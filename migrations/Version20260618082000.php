<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260618082000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create location_claim_access_sessions for A4.5 Contextual Authorization';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE location_claim_access_sessions (id INT AUTO_INCREMENT NOT NULL, token_hash VARCHAR(64) NOT NULL, issued_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, last_used_at DATETIME DEFAULT NULL, revoked_at DATETIME DEFAULT NULL, revocation_reason VARCHAR(120) DEFAULT NULL, scopes JSON NOT NULL, claim_id INT NOT NULL, created_from_otp_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_821CB69DB3BC57DA (token_hash), INDEX IDX_821CB69D9A4C8A3A (created_from_otp_id), INDEX idx_access_session_claim (claim_id), INDEX idx_access_session_expires (expires_at), INDEX idx_access_session_revoked (revoked_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE location_claim_access_sessions ADD CONSTRAINT FK_821CB69D7096A49F FOREIGN KEY (claim_id) REFERENCES location_claim_requests (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE location_claim_access_sessions ADD CONSTRAINT FK_821CB69D9A4C8A3A FOREIGN KEY (created_from_otp_id) REFERENCES location_claim_otps (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE location_claim_access_sessions DROP FOREIGN KEY FK_821CB69D7096A49F');
        $this->addSql('ALTER TABLE location_claim_access_sessions DROP FOREIGN KEY FK_821CB69D9A4C8A3A');
        $this->addSql('DROP TABLE location_claim_access_sessions');
    }
}
