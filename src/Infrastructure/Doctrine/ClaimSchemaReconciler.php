<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Table;

final class ClaimSchemaReconciler
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClaimSchemaInspector $inspector,
        private readonly bool $allowPortablePlatform = false,
    ) {
    }

    /** @return array<string, mixed> */
    public function reconcile(bool $dryRun): array
    {
        try {
            if (!$this->allowPortablePlatform && !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
                return [
                    'ok' => false,
                    'error_code' => 'unsupported_database_platform',
                    'message' => 'Claim schema reconciliation only supports MariaDB/MySQL.',
                    'dry_run' => $dryRun,
                    'plan' => [],
                    'executed' => [],
                ];
            }

            $before = $this->inspector->inspect();
            $plan = [];
            $executed = [];

            $metadataPlan = $this->metadataStoragePlan();
            if (($metadataPlan['error_code'] ?? null) !== null) {
                return $this->failure((string) $metadataPlan['error_code'], (string) $metadataPlan['message'], $dryRun, $metadataPlan['plan'] ?? [], $executed, $before);
            }
            $plan = array_merge($plan, $this->schemaActions($metadataPlan['plan']));

            $claimPlan = $this->claimSchemaPlan();
            if (($claimPlan['error_code'] ?? null) !== null) {
                return $this->failure((string) $claimPlan['error_code'], (string) $claimPlan['message'], $dryRun, array_merge($plan, $this->schemaActions($claimPlan['plan'] ?? [])), $executed, $before, $claimPlan['details'] ?? []);
            }
            $plan = array_merge($plan, $this->schemaActions($claimPlan['plan']));

            if ($dryRun) {
                if ($plan === []) {
                    $registrationPlan = $this->migrationRegistrationPlan($before);
                    if (($registrationPlan['error_code'] ?? null) !== null) {
                        return $this->failure((string) $registrationPlan['error_code'], (string) $registrationPlan['message'], true, array_merge($plan, $registrationPlan['plan'] ?? []), [], $before);
                    }
                    $plan = array_merge($plan, $registrationPlan['plan']);
                }

                return [
                    'ok' => true,
                    'changed' => $plan !== [],
                    'dry_run' => true,
                    'plan' => $plan,
                    'executed' => [],
                    'before' => $before,
                    'after' => $before,
                    'differences' => $before['differences'] ?? [],
                ];
            }

            if (!$dryRun) {
                foreach ($plan as $action) {
                    $this->executeAction($action);
                    $executed[] = $action;
                }
            }

            $afterStructure = $this->inspector->inspect();
            $registrationPlan = $this->migrationRegistrationPlan($afterStructure);
            if (($registrationPlan['error_code'] ?? null) !== null) {
                return $this->failure((string) $registrationPlan['error_code'], (string) $registrationPlan['message'], $dryRun, array_merge($plan, $registrationPlan['plan'] ?? []), $executed, $afterStructure);
            }
            $plan = array_merge($plan, $registrationPlan['plan']);

            foreach ($registrationPlan['plan'] as $action) {
                $this->executeAction($action);
                $executed[] = $action;
            }

            $final = $this->inspector->inspect();

            return [
                'ok' => $final['ready'] === true,
                'changed' => $executed !== [],
                'dry_run' => false,
                'plan' => $plan,
                'executed' => $executed,
                'before' => $before,
                'after' => $final,
                'differences' => $final['differences'] ?? [],
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'error_code' => 'claim_schema_reconciliation_failed',
                'message' => 'No fue posible reconciliar el schema Claim.',
                'exception_class' => $exception::class,
                'dry_run' => $dryRun,
                'plan' => [],
                'executed' => [],
            ];
        }
    }

    /** @return array{plan: list<string>, error_code?: string, message?: string} */
    private function metadataStoragePlan(): array
    {
        $plan = [];
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([ClaimSchemaInspector::MIGRATION_TABLE])) {
            return [
                'plan' => [
                    'CREATE TABLE doctrine_migration_versions (version VARCHAR(191) NOT NULL, executed_at DATETIME DEFAULT NULL, execution_time INT DEFAULT NULL, PRIMARY KEY(version)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB',
                ],
            ];
        }

        $table = $schemaManager->introspectTable(ClaimSchemaInspector::MIGRATION_TABLE);
        if (!$table->hasColumn('version')) {
            $plan[] = 'ALTER TABLE doctrine_migration_versions ADD version VARCHAR(191) NOT NULL';
        } else {
            $version = $table->getColumn('version');
            if ($version->getLength() !== 191 || !$version->getNotnull()) {
                $plan[] = 'ALTER TABLE doctrine_migration_versions MODIFY version VARCHAR(191) NOT NULL';
            }
        }

        if (!$table->hasColumn('executed_at')) {
            $plan[] = 'ALTER TABLE doctrine_migration_versions ADD executed_at DATETIME DEFAULT NULL';
        } elseif ($table->getColumn('executed_at')->getNotnull()) {
            $plan[] = 'ALTER TABLE doctrine_migration_versions MODIFY executed_at DATETIME DEFAULT NULL';
        }

        if (!$table->hasColumn('execution_time')) {
            $plan[] = 'ALTER TABLE doctrine_migration_versions ADD execution_time INT DEFAULT NULL';
        } elseif ($table->getColumn('execution_time')->getNotnull()) {
            $plan[] = 'ALTER TABLE doctrine_migration_versions MODIFY execution_time INT DEFAULT NULL';
        }

        if ($table->hasColumn('version') && !$this->hasPrimaryKeyForColumns($table, ['version'])) {
            $duplicates = $this->connection->fetchAllAssociative('SELECT version, COUNT(*) duplicate_count FROM doctrine_migration_versions GROUP BY version HAVING COUNT(*) > 1 LIMIT 10');
            if ($duplicates !== []) {
                return [
                    'plan' => $plan,
                    'error_code' => 'migration_metadata_duplicates_detected',
                    'message' => 'Doctrine migration metadata contains duplicate versions.',
                ];
            }
            $plan[] = 'ALTER TABLE doctrine_migration_versions ADD PRIMARY KEY (version)';
        }

        return ['plan' => $plan];
    }

    /** @return array{plan: list<string>, error_code?: string, message?: string, details?: mixed} */
    private function claimSchemaPlan(): array
    {
        $plan = [];
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['location_claim_requests'])) {
            $plan[] = $this->createClaimRequestsTableSql();
        } else {
            $table = $schemaManager->introspectTable('location_claim_requests');
            foreach ($this->claimRequestColumns() as $name => $definition) {
                if (!$table->hasColumn($name)) {
                    $plan[] = sprintf('ALTER TABLE location_claim_requests ADD %s %s', $name, $definition);
                }
            }
            foreach (['claimant_name' => 'VARCHAR(160) DEFAULT NULL', 'email' => 'VARCHAR(180) DEFAULT NULL'] as $name => $definition) {
                if ($table->hasColumn($name) && ($table->getColumn($name)->getNotnull() || $table->getColumn($name)->getLength() !== (int) preg_replace('/\\D+/', '', $definition))) {
                    $plan[] = sprintf('ALTER TABLE location_claim_requests MODIFY %s %s', $name, $definition);
                }
            }
            if (!$this->hasUniqueIndex($table, ['claim_uuid'])) {
                $duplicates = $this->claimUuidDuplicates();
                if ($duplicates !== []) {
                    return [
                        'plan' => $plan,
                        'error_code' => 'claim_uuid_duplicates_detected',
                        'message' => 'Duplicate non-null claim_uuid values were detected. Resolve them manually before creating the unique index.',
                        'details' => ['duplicates' => $duplicates],
                    ];
                }
                $plan[] = 'CREATE UNIQUE INDEX UNIQ_LOCATION_CLAIM_REQUESTS_CLAIM_UUID ON location_claim_requests (claim_uuid)';
            }
        }

        $plan = array_merge($plan, $this->childTablePlan('location_claim_evidences', $this->evidenceColumns(), $this->createEvidencesTableSql(), 'IDX_LOCATION_CLAIM_EVIDENCES_CLAIM', 'FK_LOCATION_CLAIM_EVIDENCES_CLAIM', 'claim_id', 'location_claim_requests', 'CASCADE'));
        $plan = array_merge($plan, $this->childTablePlan('location_claim_otps', $this->otpColumns(), $this->createOtpsTableSql(), 'IDX_LOCATION_CLAIM_OTPS_CLAIM', 'FK_LOCATION_CLAIM_OTPS_CLAIM', 'claim_id', 'location_claim_requests', 'CASCADE'));
        $plan = array_merge($plan, $this->sessionsTablePlan());

        return ['plan' => $plan];
    }

    /** @return list<string> */
    private function childTablePlan(string $tableName, array $columns, string $createSql, string $indexName, string $foreignKeyName, string $localColumn, string $foreignTable, string $onDelete): array
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist([$tableName])) {
            return [$createSql];
        }

        $table = $schemaManager->introspectTable($tableName);
        $plan = [];
        foreach ($columns as $name => $definition) {
            if (!$table->hasColumn($name)) {
                $plan[] = sprintf('ALTER TABLE %s ADD %s %s', $tableName, $name, $definition);
            }
        }
        if (!$this->hasIndexForColumns($table, [$localColumn])) {
            $plan[] = sprintf('CREATE INDEX %s ON %s (%s)', $indexName, $tableName, $localColumn);
        }
        if (!$this->hasForeignKey($table, [$localColumn], $foreignTable, ['id'])) {
            $plan[] = sprintf('ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (id) ON DELETE %s', $tableName, $foreignKeyName, $localColumn, $foreignTable, $onDelete);
        }

        return $plan;
    }

    /** @return list<string> */
    private function sessionsTablePlan(): array
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['location_claim_access_sessions'])) {
            return [$this->createSessionsTableSql()];
        }

        $table = $schemaManager->introspectTable('location_claim_access_sessions');
        $plan = [];
        foreach ($this->sessionColumns() as $name => $definition) {
            if (!$table->hasColumn($name)) {
                $plan[] = sprintf('ALTER TABLE location_claim_access_sessions ADD %s %s', $name, $definition);
            }
        }
        if (!$this->hasUniqueIndex($table, ['token_hash'])) {
            $plan[] = 'CREATE UNIQUE INDEX UNIQ_LOCATION_CLAIM_ACCESS_SESSIONS_TOKEN_HASH ON location_claim_access_sessions (token_hash)';
        }
        foreach ([
            'idx_access_session_claim' => ['claim_id'],
            'idx_access_session_expires' => ['expires_at'],
            'idx_access_session_revoked' => ['revoked_at'],
            'IDX_LOCATION_CLAIM_ACCESS_SESSIONS_OTP' => ['created_from_otp_id'],
        ] as $indexName => $columns) {
            if (!$this->hasIndexForColumns($table, $columns)) {
                $plan[] = sprintf('CREATE INDEX %s ON location_claim_access_sessions (%s)', $indexName, implode(', ', $columns));
            }
        }
        if (!$this->hasForeignKey($table, ['claim_id'], 'location_claim_requests', ['id'])) {
            $plan[] = 'ALTER TABLE location_claim_access_sessions ADD CONSTRAINT FK_LOCATION_CLAIM_ACCESS_SESSIONS_CLAIM FOREIGN KEY (claim_id) REFERENCES location_claim_requests (id) ON DELETE CASCADE';
        }
        if (!$this->hasForeignKey($table, ['created_from_otp_id'], 'location_claim_otps', ['id'])) {
            $plan[] = 'ALTER TABLE location_claim_access_sessions ADD CONSTRAINT FK_LOCATION_CLAIM_ACCESS_SESSIONS_OTP FOREIGN KEY (created_from_otp_id) REFERENCES location_claim_otps (id) ON DELETE SET NULL';
        }

        return $plan;
    }

    /** @return array{plan: list<array<string, mixed>>, error_code?: string, message?: string} */
    private function migrationRegistrationPlan(array $inspection): array
    {
        if (($inspection['metadata_table']['ready'] ?? false) !== true) {
            return [
                'plan' => [],
                'error_code' => 'migration_metadata_not_ready',
                'message' => 'Doctrine migration metadata storage is not structurally ready.',
            ];
        }

        $rows = array_map('strval', $this->connection->fetchFirstColumn('SELECT version FROM doctrine_migration_versions'));
        $plan = [];
        foreach (ClaimSchemaInspector::CLAIM_MIGRATION_VERSIONS as $version) {
            $canonicalExists = in_array($version, $rows, true);
            $legacyRows = array_values(array_intersect($rows, ClaimSchemaInspector::CLAIM_MIGRATION_LEGACY_ALIASES[$version] ?? []));
            $contract = $inspection['migration_contracts'][$version] ?? ['ready' => false, 'differences' => ['Missing contract']];
            if (($contract['ready'] ?? false) !== true && (!$canonicalExists || $legacyRows !== [])) {
                return [
                    'plan' => $plan,
                    'error_code' => 'migration_contract_incomplete',
                    'message' => sprintf('Cannot register %s because its structural contract is incomplete.', $version),
                ];
            }

            if (!$canonicalExists) {
                $plan[] = [
                    'type' => 'add_canonical_migration_version',
                    'version' => $version,
                    'sql' => 'INSERT INTO doctrine_migration_versions (version, executed_at, execution_time) VALUES (?, ?, 0)',
                    'params' => [$version, (new \DateTimeImmutable())->format('Y-m-d H:i:s')],
                ];
            }

            foreach ($legacyRows as $legacyRow) {
                $plan[] = [
                    'type' => 'remove_legacy_migration_version',
                    'version' => $version,
                    'legacy_version' => $legacyRow,
                    'sql' => 'DELETE FROM doctrine_migration_versions WHERE version = ?',
                    'params' => [$legacyRow],
                ];
            }
        }

        return ['plan' => $plan];
    }

    /** @return list<array{claim_uuid: string, count: int}> */
    private function claimUuidDuplicates(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT claim_uuid, COUNT(*) duplicate_count FROM location_claim_requests WHERE claim_uuid IS NOT NULL GROUP BY claim_uuid HAVING COUNT(*) > 1 LIMIT 10');

        return array_map(fn (array $row): array => [
            'claim_uuid' => $this->maskUuid((string) $row['claim_uuid']),
            'count' => (int) $row['duplicate_count'],
        ], $rows);
    }

    private function maskUuid(string $uuid): string
    {
        if (strlen($uuid) <= 12) {
            return substr($uuid, 0, 4) . '...';
        }

        return substr($uuid, 0, 8) . '...' . substr($uuid, -4);
    }

    /** @return array<string, string> */
    private function claimRequestColumns(): array
    {
        return [
            'claim_uuid' => 'CHAR(36) DEFAULT NULL',
            'claimant_role' => 'VARCHAR(64) DEFAULT NULL',
            'claimant_phone_e164' => 'VARCHAR(32) DEFAULT NULL',
            'business_phone_e164' => 'VARCHAR(32) DEFAULT NULL',
            'proposed_name' => 'VARCHAR(180) DEFAULT NULL',
            'proposed_address_json' => 'JSON DEFAULT NULL',
            'confirmed_latitude' => 'DOUBLE DEFAULT NULL',
            'confirmed_longitude' => 'DOUBLE DEFAULT NULL',
            'email_verified_at' => 'DATETIME DEFAULT NULL',
            'legal_acceptance_reference' => 'VARCHAR(255) DEFAULT NULL',
            'resume_token_hash' => 'VARCHAR(64) DEFAULT NULL',
            'resume_token_expires_at' => 'DATETIME DEFAULT NULL',
            'resume_token_revoked_at' => 'DATETIME DEFAULT NULL',
            'last_completed_step' => 'VARCHAR(64) DEFAULT NULL',
            'submitted_at' => 'DATETIME DEFAULT NULL',
            'under_review_at' => 'DATETIME DEFAULT NULL',
            'needs_info_at' => 'DATETIME DEFAULT NULL',
            'approved_at' => 'DATETIME DEFAULT NULL',
            'rejected_at' => 'DATETIME DEFAULT NULL',
            'converted_at' => 'DATETIME DEFAULT NULL',
            'expires_at' => 'DATETIME DEFAULT NULL',
            'cancelled_at' => 'DATETIME DEFAULT NULL',
            'submission_mode' => 'VARCHAR(32) DEFAULT NULL',
        ];
    }

    /** @return array<string, string> */
    private function evidenceColumns(): array
    {
        return [
            'id' => 'INT AUTO_INCREMENT NOT NULL',
            'claim_id' => 'INT NOT NULL',
            'evidence_type' => 'VARCHAR(64) NOT NULL',
            'storage_provider' => 'VARCHAR(64) NOT NULL',
            'bucket_name' => 'VARCHAR(120) NOT NULL',
            'object_key' => 'VARCHAR(512) NOT NULL',
            'storage_object_id' => 'VARCHAR(255) DEFAULT NULL',
            'original_filename' => 'VARCHAR(255) NOT NULL',
            'mime_type' => 'VARCHAR(120) NOT NULL',
            'size_bytes' => 'INT NOT NULL',
            'checksum_sha256' => 'VARCHAR(64) DEFAULT NULL',
            'duration_seconds' => 'INT DEFAULT NULL',
            'status' => 'VARCHAR(32) NOT NULL',
            'metadata_json' => 'JSON DEFAULT NULL',
            'uploaded_at' => 'DATETIME DEFAULT NULL',
            'verified_at' => 'DATETIME DEFAULT NULL',
            'replaced_at' => 'DATETIME DEFAULT NULL',
            'deleted_at' => 'DATETIME DEFAULT NULL',
            'created_at' => 'DATETIME NOT NULL',
            'updated_at' => 'DATETIME NOT NULL',
        ];
    }

    /** @return array<string, string> */
    private function otpColumns(): array
    {
        return [
            'id' => 'INT AUTO_INCREMENT NOT NULL',
            'claim_id' => 'INT NOT NULL',
            'purpose' => 'VARCHAR(64) NOT NULL',
            'code_hash' => 'VARCHAR(255) NOT NULL',
            'expires_at' => 'DATETIME NOT NULL',
            'attempt_count' => 'INT NOT NULL',
            'max_attempts' => 'INT NOT NULL',
            'consumed_at' => 'DATETIME DEFAULT NULL',
            'requested_at' => 'DATETIME NOT NULL',
            'last_attempt_at' => 'DATETIME DEFAULT NULL',
            'created_at' => 'DATETIME NOT NULL',
        ];
    }

    /** @return array<string, string> */
    private function sessionColumns(): array
    {
        return [
            'id' => 'INT AUTO_INCREMENT NOT NULL',
            'token_hash' => 'VARCHAR(64) NOT NULL',
            'issued_at' => 'DATETIME NOT NULL',
            'expires_at' => 'DATETIME NOT NULL',
            'last_used_at' => 'DATETIME DEFAULT NULL',
            'revoked_at' => 'DATETIME DEFAULT NULL',
            'revocation_reason' => 'VARCHAR(120) DEFAULT NULL',
            'scopes' => 'JSON NOT NULL',
            'claim_id' => 'INT NOT NULL',
            'created_from_otp_id' => 'INT DEFAULT NULL',
        ];
    }

    private function createClaimRequestsTableSql(): string
    {
        return 'CREATE TABLE location_claim_requests (id INT AUTO_INCREMENT NOT NULL, source_type VARCHAR(32) NOT NULL, canonical_location_id INT DEFAULT NULL, external_source_key VARCHAR(120) DEFAULT NULL, location_name VARCHAR(180) NOT NULL, short_address LONGTEXT DEFAULT NULL, claimant_name VARCHAR(160) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, whatsapp_e164 VARCHAR(32) DEFAULT NULL, message LONGTEXT DEFAULT NULL, status VARCHAR(32) NOT NULL, prefill_payload_json JSON DEFAULT NULL, evidence_links_json JSON DEFAULT NULL, review_checklist_json JSON DEFAULT NULL, review_notes LONGTEXT DEFAULT NULL, reviewed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, claim_uuid CHAR(36) DEFAULT NULL, claimant_role VARCHAR(64) DEFAULT NULL, claimant_phone_e164 VARCHAR(32) DEFAULT NULL, business_phone_e164 VARCHAR(32) DEFAULT NULL, proposed_name VARCHAR(180) DEFAULT NULL, proposed_address_json JSON DEFAULT NULL, confirmed_latitude DOUBLE DEFAULT NULL, confirmed_longitude DOUBLE DEFAULT NULL, email_verified_at DATETIME DEFAULT NULL, legal_acceptance_reference VARCHAR(255) DEFAULT NULL, resume_token_hash VARCHAR(64) DEFAULT NULL, resume_token_expires_at DATETIME DEFAULT NULL, resume_token_revoked_at DATETIME DEFAULT NULL, last_completed_step VARCHAR(64) DEFAULT NULL, submitted_at DATETIME DEFAULT NULL, under_review_at DATETIME DEFAULT NULL, needs_info_at DATETIME DEFAULT NULL, approved_at DATETIME DEFAULT NULL, rejected_at DATETIME DEFAULT NULL, converted_at DATETIME DEFAULT NULL, expires_at DATETIME DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, submission_mode VARCHAR(32) DEFAULT NULL, UNIQUE INDEX UNIQ_LOCATION_CLAIM_REQUESTS_CLAIM_UUID (claim_uuid), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB';
    }

    private function createEvidencesTableSql(): string
    {
        return 'CREATE TABLE location_claim_evidences (id INT AUTO_INCREMENT NOT NULL, claim_id INT NOT NULL, evidence_type VARCHAR(64) NOT NULL, storage_provider VARCHAR(64) NOT NULL, bucket_name VARCHAR(120) NOT NULL, object_key VARCHAR(512) NOT NULL, storage_object_id VARCHAR(255) DEFAULT NULL, original_filename VARCHAR(255) NOT NULL, mime_type VARCHAR(120) NOT NULL, size_bytes INT NOT NULL, checksum_sha256 VARCHAR(64) DEFAULT NULL, duration_seconds INT DEFAULT NULL, status VARCHAR(32) NOT NULL, metadata_json JSON DEFAULT NULL, uploaded_at DATETIME DEFAULT NULL, verified_at DATETIME DEFAULT NULL, replaced_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_LOCATION_CLAIM_EVIDENCES_CLAIM (claim_id), PRIMARY KEY(id), CONSTRAINT FK_LOCATION_CLAIM_EVIDENCES_CLAIM FOREIGN KEY (claim_id) REFERENCES location_claim_requests (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB';
    }

    private function createOtpsTableSql(): string
    {
        return 'CREATE TABLE location_claim_otps (id INT AUTO_INCREMENT NOT NULL, claim_id INT NOT NULL, purpose VARCHAR(64) NOT NULL, code_hash VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL, attempt_count INT NOT NULL, max_attempts INT NOT NULL, consumed_at DATETIME DEFAULT NULL, requested_at DATETIME NOT NULL, last_attempt_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_LOCATION_CLAIM_OTPS_CLAIM (claim_id), PRIMARY KEY(id), CONSTRAINT FK_LOCATION_CLAIM_OTPS_CLAIM FOREIGN KEY (claim_id) REFERENCES location_claim_requests (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB';
    }

    private function createSessionsTableSql(): string
    {
        return 'CREATE TABLE location_claim_access_sessions (id INT AUTO_INCREMENT NOT NULL, token_hash VARCHAR(64) NOT NULL, issued_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, last_used_at DATETIME DEFAULT NULL, revoked_at DATETIME DEFAULT NULL, revocation_reason VARCHAR(120) DEFAULT NULL, scopes JSON NOT NULL, claim_id INT NOT NULL, created_from_otp_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_LOCATION_CLAIM_ACCESS_SESSIONS_TOKEN_HASH (token_hash), INDEX IDX_LOCATION_CLAIM_ACCESS_SESSIONS_OTP (created_from_otp_id), INDEX idx_access_session_claim (claim_id), INDEX idx_access_session_expires (expires_at), INDEX idx_access_session_revoked (revoked_at), PRIMARY KEY(id), CONSTRAINT FK_LOCATION_CLAIM_ACCESS_SESSIONS_CLAIM FOREIGN KEY (claim_id) REFERENCES location_claim_requests (id) ON DELETE CASCADE, CONSTRAINT FK_LOCATION_CLAIM_ACCESS_SESSIONS_OTP FOREIGN KEY (created_from_otp_id) REFERENCES location_claim_otps (id) ON DELETE SET NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB';
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

    private function hasPrimaryKeyForColumns(Table $table, array $columns): bool
    {
        foreach ($table->getIndexes() as $index) {
            if ($index->isPrimary() && $index->getColumns() === $columns) {
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

    private function failure(string $errorCode, string $message, bool $dryRun, array $plan, array $executed, array $inspection, array $details = []): array
    {
        return [
            'ok' => false,
            'error_code' => $errorCode,
            'message' => $message,
            'dry_run' => $dryRun,
            'plan' => $plan,
            'executed' => $executed,
            'inspection' => $inspection,
            'details' => $details,
        ];
    }

    /** @param list<string> $statements @return list<array<string, mixed>> */
    private function schemaActions(array $statements): array
    {
        return array_map(fn (string $statement): array => [
            'type' => str_starts_with($statement, 'CREATE ') ? 'create_schema_object' : 'alter_schema_object',
            'sql' => $statement,
            'params' => [],
        ], $statements);
    }

    /** @param array<string, mixed> $action */
    private function executeAction(array $action): void
    {
        if (($action['type'] ?? null) === 'add_canonical_migration_version') {
            try {
                $this->connection->executeStatement((string) $action['sql'], $action['params'] ?? []);
            } catch (UniqueConstraintViolationException $exception) {
                if (!$this->metadataVersionExists((string) $action['version'])) {
                    throw $exception;
                }
            }

            return;
        }

        $this->connection->executeStatement((string) $action['sql'], $action['params'] ?? []);
    }

    private function metadataVersionExists(string $version): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM doctrine_migration_versions WHERE version = ?',
            [$version],
        );
    }
}
