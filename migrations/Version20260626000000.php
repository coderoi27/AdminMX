<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reconciles installations where the historical Claim migrations stopped after
 * adding only part of their DDL. It never drops data or alters old migrations.
 */
final class Version20260626000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reconciles partial Claim schema installations for MariaDB/MySQL without data loss.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->platform instanceof AbstractMySQLPlatform, 'Claim reconciliation supports MariaDB/MySQL only.');

        if (!$this->sm->tablesExist(['location_claim_requests'])) {
            $this->addSql($this->createClaimRequestsTableSql());
        } else {
            $this->reconcileClaimRequests($this->sm->introspectTable('location_claim_requests'));
        }

        if (!$this->sm->tablesExist(['location_claim_evidences'])) {
            $this->addSql($this->createEvidencesTableSql());
        } else {
            $this->reconcileChildTable('location_claim_evidences', $this->evidenceColumns(), 'IDX_LOCATION_CLAIM_EVIDENCES_CLAIM', 'FK_LOCATION_CLAIM_EVIDENCES_CLAIM', 'claim_id', 'location_claim_requests', 'CASCADE');
        }

        if (!$this->sm->tablesExist(['location_claim_otps'])) {
            $this->addSql($this->createOtpsTableSql());
        } else {
            $this->reconcileChildTable('location_claim_otps', $this->otpColumns(), 'IDX_LOCATION_CLAIM_OTPS_CLAIM', 'FK_LOCATION_CLAIM_OTPS_CLAIM', 'claim_id', 'location_claim_requests', 'CASCADE');
        }

        if (!$this->sm->tablesExist(['location_claim_access_sessions'])) {
            $this->addSql($this->createSessionsTableSql());
        } else {
            $this->reconcileSessions($this->sm->introspectTable('location_claim_access_sessions'));
        }
    }

    private function reconcileClaimRequests(Table $table): void
    {
        foreach ($this->claimRequestColumns() as $name => $definition) {
            if (!$table->hasColumn($name)) {
                $this->addSql(sprintf('ALTER TABLE location_claim_requests ADD %s %s', $name, $definition));
            }
        }

        foreach (['claimant_name' => 'VARCHAR(160) DEFAULT NULL', 'email' => 'VARCHAR(180) DEFAULT NULL'] as $name => $definition) {
            if ($table->hasColumn($name)) {
                $column = $table->getColumn($name);
                if ($column->getNotnull() || $column->getLength() !== (int) preg_replace('/\\D+/', '', $definition)) {
                    $this->addSql(sprintf('ALTER TABLE location_claim_requests MODIFY %s %s', $name, $definition));
                }
            }
        }

        if (!$this->hasUniqueIndex($table, ['claim_uuid'])) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_LOCATION_CLAIM_REQUESTS_CLAIM_UUID ON location_claim_requests (claim_uuid)');
        }
    }

    /** @param array<string, string> $columns */
    private function reconcileChildTable(string $tableName, array $columns, string $indexName, string $foreignKeyName, string $localColumn, string $foreignTable, string $onDelete): void
    {
        $table = $this->sm->introspectTable($tableName);
        foreach ($columns as $name => $definition) {
            if (!$table->hasColumn($name)) {
                $this->addSql(sprintf('ALTER TABLE %s ADD %s %s', $tableName, $name, $definition));
            }
        }

        if (!$this->hasIndexForColumns($table, [$localColumn])) {
            $this->addSql(sprintf('CREATE INDEX %s ON %s (%s)', $indexName, $tableName, $localColumn));
        }

        if (!$this->hasForeignKey($table, [$localColumn], $foreignTable, ['id'])) {
            $this->addSql(sprintf('ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (id) ON DELETE %s', $tableName, $foreignKeyName, $localColumn, $foreignTable, $onDelete));
        }
    }

    private function reconcileSessions(Table $table): void
    {
        foreach ($this->sessionColumns() as $name => $definition) {
            if (!$table->hasColumn($name)) {
                $this->addSql(sprintf('ALTER TABLE location_claim_access_sessions ADD %s %s', $name, $definition));
            }
        }

        if (!$this->hasUniqueIndex($table, ['token_hash'])) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_LOCATION_CLAIM_ACCESS_SESSIONS_TOKEN_HASH ON location_claim_access_sessions (token_hash)');
        }

        foreach ([
            'idx_access_session_claim' => ['claim_id'],
            'idx_access_session_expires' => ['expires_at'],
            'idx_access_session_revoked' => ['revoked_at'],
            'IDX_LOCATION_CLAIM_ACCESS_SESSIONS_OTP' => ['created_from_otp_id'],
        ] as $indexName => $columns) {
            if (!$this->hasIndexForColumns($table, $columns)) {
                $this->addSql(sprintf('CREATE INDEX %s ON location_claim_access_sessions (%s)', $indexName, implode(', ', $columns)));
            }
        }

        if (!$this->hasForeignKey($table, ['claim_id'], 'location_claim_requests', ['id'])) {
            $this->addSql('ALTER TABLE location_claim_access_sessions ADD CONSTRAINT FK_LOCATION_CLAIM_ACCESS_SESSIONS_CLAIM FOREIGN KEY (claim_id) REFERENCES location_claim_requests (id) ON DELETE CASCADE');
        }
        if (!$this->hasForeignKey($table, ['created_from_otp_id'], 'location_claim_otps', ['id'])) {
            $this->addSql('ALTER TABLE location_claim_access_sessions ADD CONSTRAINT FK_LOCATION_CLAIM_ACCESS_SESSIONS_OTP FOREIGN KEY (created_from_otp_id) REFERENCES location_claim_otps (id) ON DELETE SET NULL');
        }
    }

    /** @return array<string, string> */
    private function claimRequestColumns(): array
    {
        return [
            'claim_uuid' => 'CHAR(36) DEFAULT NULL', 'claimant_role' => 'VARCHAR(64) DEFAULT NULL',
            'claimant_phone_e164' => 'VARCHAR(32) DEFAULT NULL', 'business_phone_e164' => 'VARCHAR(32) DEFAULT NULL',
            'proposed_name' => 'VARCHAR(180) DEFAULT NULL', 'proposed_address_json' => 'JSON DEFAULT NULL',
            'confirmed_latitude' => 'DOUBLE DEFAULT NULL', 'confirmed_longitude' => 'DOUBLE DEFAULT NULL',
            'email_verified_at' => 'DATETIME DEFAULT NULL', 'legal_acceptance_reference' => 'VARCHAR(255) DEFAULT NULL',
            'resume_token_hash' => 'VARCHAR(64) DEFAULT NULL', 'resume_token_expires_at' => 'DATETIME DEFAULT NULL',
            'resume_token_revoked_at' => 'DATETIME DEFAULT NULL', 'last_completed_step' => 'VARCHAR(64) DEFAULT NULL',
            'submitted_at' => 'DATETIME DEFAULT NULL', 'under_review_at' => 'DATETIME DEFAULT NULL',
            'needs_info_at' => 'DATETIME DEFAULT NULL', 'approved_at' => 'DATETIME DEFAULT NULL',
            'rejected_at' => 'DATETIME DEFAULT NULL', 'converted_at' => 'DATETIME DEFAULT NULL',
            'expires_at' => 'DATETIME DEFAULT NULL', 'cancelled_at' => 'DATETIME DEFAULT NULL',
            'submission_mode' => 'VARCHAR(32) DEFAULT NULL',
        ];
    }

    /** @return array<string, string> */
    private function evidenceColumns(): array
    {
        return [
            'id' => 'INT AUTO_INCREMENT NOT NULL', 'claim_id' => 'INT NOT NULL', 'evidence_type' => 'VARCHAR(64) NOT NULL',
            'storage_provider' => 'VARCHAR(64) NOT NULL', 'bucket_name' => 'VARCHAR(120) NOT NULL', 'object_key' => 'VARCHAR(512) NOT NULL',
            'storage_object_id' => 'VARCHAR(255) DEFAULT NULL', 'original_filename' => 'VARCHAR(255) NOT NULL', 'mime_type' => 'VARCHAR(120) NOT NULL',
            'size_bytes' => 'INT NOT NULL', 'checksum_sha256' => 'VARCHAR(64) DEFAULT NULL', 'duration_seconds' => 'INT DEFAULT NULL',
            'status' => 'VARCHAR(32) NOT NULL', 'metadata_json' => 'JSON DEFAULT NULL', 'uploaded_at' => 'DATETIME DEFAULT NULL',
            'verified_at' => 'DATETIME DEFAULT NULL', 'replaced_at' => 'DATETIME DEFAULT NULL', 'deleted_at' => 'DATETIME DEFAULT NULL',
            'created_at' => 'DATETIME NOT NULL', 'updated_at' => 'DATETIME NOT NULL',
        ];
    }

    /** @return array<string, string> */
    private function otpColumns(): array
    {
        return [
            'id' => 'INT AUTO_INCREMENT NOT NULL', 'claim_id' => 'INT NOT NULL', 'purpose' => 'VARCHAR(64) NOT NULL',
            'code_hash' => 'VARCHAR(255) NOT NULL', 'expires_at' => 'DATETIME NOT NULL', 'attempt_count' => 'INT NOT NULL',
            'max_attempts' => 'INT NOT NULL', 'consumed_at' => 'DATETIME DEFAULT NULL', 'requested_at' => 'DATETIME NOT NULL',
            'last_attempt_at' => 'DATETIME DEFAULT NULL', 'created_at' => 'DATETIME NOT NULL',
        ];
    }

    /** @return array<string, string> */
    private function sessionColumns(): array
    {
        return [
            'id' => 'INT AUTO_INCREMENT NOT NULL', 'token_hash' => 'VARCHAR(64) NOT NULL', 'issued_at' => 'DATETIME NOT NULL',
            'expires_at' => 'DATETIME NOT NULL', 'last_used_at' => 'DATETIME DEFAULT NULL', 'revoked_at' => 'DATETIME DEFAULT NULL',
            'revocation_reason' => 'VARCHAR(120) DEFAULT NULL', 'scopes' => 'JSON NOT NULL', 'claim_id' => 'INT NOT NULL',
            'created_from_otp_id' => 'INT DEFAULT NULL',
        ];
    }

    private function createClaimRequestsTableSql(): string
    {
        return 'CREATE TABLE location_claim_requests (id INT AUTO_INCREMENT NOT NULL, source_type VARCHAR(32) NOT NULL, canonical_location_id INT DEFAULT NULL, external_source_key VARCHAR(120) DEFAULT NULL, location_name VARCHAR(180) NOT NULL, short_address LONGTEXT DEFAULT NULL, claimant_name VARCHAR(160) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, whatsapp_e164 VARCHAR(32) DEFAULT NULL, message LONGTEXT DEFAULT NULL, status VARCHAR(32) NOT NULL, prefill_payload_json JSON DEFAULT NULL, evidence_links_json JSON DEFAULT NULL, review_checklist_json JSON DEFAULT NULL, review_notes LONGTEXT DEFAULT NULL, reviewed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, claim_uuid CHAR(36) DEFAULT NULL, claimant_role VARCHAR(64) DEFAULT NULL, claimant_phone_e164 VARCHAR(32) DEFAULT NULL, business_phone_e164 VARCHAR(32) DEFAULT NULL, proposed_name VARCHAR(180) DEFAULT NULL, proposed_address_json JSON DEFAULT NULL, confirmed_latitude DOUBLE DEFAULT NULL, confirmed_longitude DOUBLE DEFAULT NULL, email_verified_at DATETIME DEFAULT NULL, legal_acceptance_reference VARCHAR(255) DEFAULT NULL, resume_token_hash VARCHAR(64) DEFAULT NULL, resume_token_expires_at DATETIME DEFAULT NULL, resume_token_revoked_at DATETIME DEFAULT NULL, last_completed_step VARCHAR(64) DEFAULT NULL, submitted_at DATETIME DEFAULT NULL, under_review_at DATETIME DEFAULT NULL, needs_info_at DATETIME DEFAULT NULL, approved_at DATETIME DEFAULT NULL, rejected_at DATETIME DEFAULT NULL, converted_at DATETIME DEFAULT NULL, expires_at DATETIME DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, submission_mode VARCHAR(32) DEFAULT NULL, UNIQUE INDEX UNIQ_LOCATION_CLAIM_REQUESTS_CLAIM_UUID (claim_uuid), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB';
    }

    private function createEvidencesTableSql(): string
    {
        return 'CREATE TABLE location_claim_evidences (id INT AUTO_INCREMENT NOT NULL, claim_id INT NOT NULL, evidence_type VARCHAR(64) NOT NULL, storage_provider VARCHAR(64) NOT NULL, bucket_name VARCHAR(120) NOT NULL, object_key VARCHAR(512) NOT NULL, storage_object_id VARCHAR(255) DEFAULT NULL, original_filename VARCHAR(255) NOT NULL, mime_type VARCHAR(120) NOT NULL, size_bytes INT NOT NULL, checksum_sha256 VARCHAR(64) DEFAULT NULL, duration_seconds INT DEFAULT NULL, status VARCHAR(32) NOT NULL, metadata_json JSON DEFAULT NULL, uploaded_at DATETIME DEFAULT NULL, verified_at DATETIME DEFAULT NULL, replaced_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_LOCATION_CLAIM_EVIDENCES_CLAIM (claim_id), PRIMARY KEY(id), CONSTRAINT FK_LOCATION_CLAIM_EVIDENCES_CLAIM FOREIGN KEY (claim_id) REFERENCES location_claim_requests (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB';
    }

    private function createOtpsTableSql(): string
    {
        return 'CREATE TABLE location_claim_otps (id INT AUTO_INCREMENT NOT NULL, claim_id INT NOT NULL, purpose VARCHAR(64) NOT NULL, code_hash VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL, attempt_count INT NOT NULL, max_attempts INT NOT NULL, consumed_at DATETIME DEFAULT NULL, requested_at DATETIME NOT NULL, last_attempt_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_LOCATION_CLAIM_OTPS_CLAIM (claim_id), PRIMARY KEY(id), CONSTRAINT FK_LOCATION_CLAIM_OTPS_CLAIM FOREIGN KEY (claim_id) REFERENCES location_claim_requests (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB';
    }

    private function createSessionsTableSql(): string
    {
        return 'CREATE TABLE location_claim_access_sessions (id INT AUTO_INCREMENT NOT NULL, token_hash VARCHAR(64) NOT NULL, issued_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, last_used_at DATETIME DEFAULT NULL, revoked_at DATETIME DEFAULT NULL, revocation_reason VARCHAR(120) DEFAULT NULL, scopes JSON NOT NULL, claim_id INT NOT NULL, created_from_otp_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_LOCATION_CLAIM_ACCESS_SESSIONS_TOKEN_HASH (token_hash), INDEX IDX_LOCATION_CLAIM_ACCESS_SESSIONS_OTP (created_from_otp_id), INDEX idx_access_session_claim (claim_id), INDEX idx_access_session_expires (expires_at), INDEX idx_access_session_revoked (revoked_at), PRIMARY KEY(id), CONSTRAINT FK_LOCATION_CLAIM_ACCESS_SESSIONS_CLAIM FOREIGN KEY (claim_id) REFERENCES location_claim_requests (id) ON DELETE CASCADE, CONSTRAINT FK_LOCATION_CLAIM_ACCESS_SESSIONS_OTP FOREIGN KEY (created_from_otp_id) REFERENCES location_claim_otps (id) ON DELETE SET NULL) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB';
    }

    private function hasIndexForColumns(Table $table, array $columns): bool
    {
        foreach ($table->getIndexes() as $index) {
            if ($index->getColumns() === $columns) {
                return true;
            }
        }
        return false;
    }

    private function hasUniqueIndex(Table $table, array $columns): bool
    {
        foreach ($table->getIndexes() as $index) {
            if ($index->isUnique() && $index->getColumns() === $columns) {
                return true;
            }
        }
        return false;
    }

    private function hasForeignKey(Table $table, array $localColumns, string $foreignTable, array $foreignColumns): bool
    {
        foreach ($table->getForeignKeys() as $foreignKey) {
            if ($foreignKey->getLocalColumns() === $localColumns && $foreignKey->getForeignTableName() === $foreignTable && $foreignKey->getForeignColumns() === $foreignColumns) {
                return true;
            }
        }
        return false;
    }
}
