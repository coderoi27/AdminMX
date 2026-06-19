<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260618001945 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega campos incrementales al Claim y entidades de OTP y Evidencia.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE location_claim_requests ADD claim_uuid CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', ADD claimant_role VARCHAR(64) DEFAULT NULL, ADD claimant_phone_e164 VARCHAR(32) DEFAULT NULL, ADD business_phone_e164 VARCHAR(32) DEFAULT NULL, ADD proposed_name VARCHAR(180) DEFAULT NULL, ADD proposed_address_json JSON DEFAULT NULL, ADD confirmed_latitude DOUBLE PRECISION DEFAULT NULL, ADD confirmed_longitude DOUBLE PRECISION DEFAULT NULL, ADD email_verified_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD legal_acceptance_reference VARCHAR(255) DEFAULT NULL, ADD resume_token_hash VARCHAR(64) DEFAULT NULL, ADD resume_token_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD resume_token_revoked_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD last_completed_step VARCHAR(64) DEFAULT NULL, ADD submitted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD under_review_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD needs_info_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD approved_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD rejected_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD converted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD cancelled_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_LOCATION_CLAIM_REQUESTS_CLAIM_UUID ON location_claim_requests (claim_uuid)');

        $this->addSql('CREATE TABLE location_claim_evidences (id INT AUTO_INCREMENT NOT NULL, claim_id INT NOT NULL, evidence_type VARCHAR(64) NOT NULL, storage_provider VARCHAR(64) NOT NULL, bucket_name VARCHAR(120) NOT NULL, object_key VARCHAR(512) NOT NULL, storage_object_id VARCHAR(255) DEFAULT NULL, original_filename VARCHAR(255) NOT NULL, mime_type VARCHAR(120) NOT NULL, size_bytes INT NOT NULL, checksum_sha256 VARCHAR(64) DEFAULT NULL, duration_seconds INT DEFAULT NULL, status VARCHAR(32) NOT NULL, metadata_json JSON DEFAULT NULL, uploaded_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', verified_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', replaced_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_LOCATION_CLAIM_EVIDENCES_CLAIM (claim_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE location_claim_evidences ADD CONSTRAINT FK_LOCATION_CLAIM_EVIDENCES_CLAIM FOREIGN KEY (claim_id) REFERENCES location_claim_requests (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE location_claim_otps (id INT AUTO_INCREMENT NOT NULL, claim_id INT NOT NULL, purpose VARCHAR(64) NOT NULL, code_hash VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', attempt_count INT NOT NULL, max_attempts INT NOT NULL, consumed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', requested_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_attempt_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_LOCATION_CLAIM_OTPS_CLAIM (claim_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE location_claim_otps ADD CONSTRAINT FK_LOCATION_CLAIM_OTPS_CLAIM FOREIGN KEY (claim_id) REFERENCES location_claim_requests (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE location_claim_evidences DROP FOREIGN KEY FK_LOCATION_CLAIM_EVIDENCES_CLAIM');
        $this->addSql('DROP TABLE location_claim_evidences');
        $this->addSql('ALTER TABLE location_claim_otps DROP FOREIGN KEY FK_LOCATION_CLAIM_OTPS_CLAIM');
        $this->addSql('DROP TABLE location_claim_otps');
        $this->addSql('DROP INDEX UNIQ_LOCATION_CLAIM_REQUESTS_CLAIM_UUID ON location_claim_requests');
        $this->addSql('ALTER TABLE location_claim_requests DROP claim_uuid, DROP claimant_role, DROP claimant_phone_e164, DROP business_phone_e164, DROP proposed_name, DROP proposed_address_json, DROP confirmed_latitude, DROP confirmed_longitude, DROP email_verified_at, DROP legal_acceptance_reference, DROP resume_token_hash, DROP resume_token_expires_at, DROP resume_token_revoked_at, DROP last_completed_step, DROP submitted_at, DROP under_review_at, DROP needs_info_at, DROP approved_at, DROP rejected_at, DROP converted_at, DROP expires_at, DROP cancelled_at');
    }
}
