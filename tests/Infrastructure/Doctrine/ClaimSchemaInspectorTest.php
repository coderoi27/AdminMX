<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine;

use App\Command\ClaimDiagnoseSchemaCommand;
use App\Command\ClaimReconcileSchemaCommand;
use App\Infrastructure\Doctrine\ClaimSchemaInspector;
use App\Infrastructure\Doctrine\ClaimSchemaReconciler;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ClaimSchemaInspectorTest extends TestCase
{
    public function testConfigurationKeepsServerVersionInDatabaseUrlOnly(): void
    {
        $projectDir = dirname(__DIR__, 3);
        $doctrineConfig = (string) file_get_contents($projectDir . '/config/packages/doctrine.yaml');

        self::assertStringContainsString('DATABASE_URL is the only source of truth', $doctrineConfig);
        self::assertStringNotContainsString('server_version:', $doctrineConfig);
    }

    public function testInspectorReportsIncompleteWhenOnlyPartialClaimStructureExists(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('CREATE TABLE location_claim_requests (id INTEGER PRIMARY KEY, claim_uuid CHAR(36), submission_mode VARCHAR(32), claimant_name VARCHAR(160) NOT NULL, email VARCHAR(180) NOT NULL)');

        $diagnosis = (new ClaimSchemaInspector($connection))->inspect();

        self::assertFalse($diagnosis['ready']);
        self::assertContains('Column must be nullable: location_claim_requests.claimant_name', $diagnosis['differences']);
        self::assertContains('Column must be nullable: location_claim_requests.email', $diagnosis['differences']);
        self::assertContains('Missing table: location_claim_otps', $diagnosis['differences']);
        self::assertContains('Missing table: location_claim_access_sessions', $diagnosis['differences']);
    }

    public function testInspectorReportsIncompatibleMetadataStorage(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('CREATE TABLE doctrine_migration_versions (version VARCHAR(1024), executed_at DATETIME NOT NULL)');

        $diagnosis = (new ClaimSchemaInspector($connection))->inspect();

        self::assertFalse($diagnosis['metadata_table']['ready']);
        self::assertContains('Metadata column length must be 191: doctrine_migration_versions.version', $diagnosis['metadata_table']['differences']);
        self::assertContains('Metadata column must be not null: doctrine_migration_versions.version', $diagnosis['metadata_table']['differences']);
        self::assertContains('Metadata column must be nullable: doctrine_migration_versions.executed_at', $diagnosis['metadata_table']['differences']);
        self::assertContains('Missing metadata column: doctrine_migration_versions.execution_time', $diagnosis['metadata_table']['differences']);
        self::assertContains('Missing primary key: doctrine_migration_versions.version', $diagnosis['metadata_table']['differences']);
    }

    public function testInspectorAndCommandReportReadySchemaWithoutSecrets(): void
    {
        $connection = $this->connection();
        $this->createReadyClaimSchema($connection);
        $this->insertCanonicalClaimMigrations($connection);
        $inspector = new ClaimSchemaInspector($connection);

        self::assertTrue($inspector->inspect()['ready']);

        $tester = new CommandTester(new ClaimDiagnoseSchemaCommand($inspector));
        $exitCode = $tester->execute(['--json' => true]);
        $output = $tester->getDisplay();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('"ready":true', $output);
        self::assertStringNotContainsString('DATABASE_URL', $output);
        self::assertStringNotContainsString('password', strtolower($output));
    }

    public function testCanonicalizationPreservesBackslashAndDetectsLegacy(): void
    {
        self::assertSame(
            'DoctrineMigrations\\Version20260618001945',
            ClaimSchemaInspector::canonicalizeClaimMigrationVersion('DoctrineMigrations\\Version20260618001945'),
        );
        self::assertSame(
            'DoctrineMigrations\\Version20260618001945',
            ClaimSchemaInspector::canonicalizeClaimMigrationVersion('DoctrineMigrationsVersion20260618001945'),
        );
        self::assertTrue(ClaimSchemaInspector::isCanonicalClaimMigrationVersion('DoctrineMigrations\\Version20260618001945'));
        self::assertTrue(ClaimSchemaInspector::isLegacyClaimMigrationVersion('DoctrineMigrationsVersion20260618001945'));
        self::assertFalse(ClaimSchemaInspector::isCanonicalClaimMigrationVersion('DoctrineMigrationsVersion20260618001945'));
    }

    public function testDiagnoseSeparatesCanonicalAndLegacyAndKeepsReadyFalseWhileLegacyExists(): void
    {
        $connection = $this->connection();
        $this->createReadyClaimSchema($connection);
        $this->insertCanonicalClaimMigrations($connection);
        $connection->insert('doctrine_migration_versions', [
            'version' => 'DoctrineMigrationsVersion20260618001945',
            'executed_at' => '2026-06-26 00:00:00',
            'execution_time' => 0,
        ]);

        $diagnosis = (new ClaimSchemaInspector($connection))->inspect();

        self::assertFalse($diagnosis['ready']);
        self::assertFalse($diagnosis['metadata_consistent']);
        self::assertContains('DoctrineMigrations\\Version20260618001945', $diagnosis['claim_migrations_canonical']);
        self::assertContains('DoctrineMigrationsVersion20260618001945', $diagnosis['claim_migrations_legacy']);
        self::assertContains('Legacy migration version row present: DoctrineMigrationsVersion20260618001945', $diagnosis['differences']);
    }

    public function testDiagnoseReadyFalseWhenCanonicalMigrationIsMissing(): void
    {
        $connection = $this->connection();
        $this->createReadyClaimSchema($connection);

        $diagnosis = (new ClaimSchemaInspector($connection))->inspect();

        self::assertFalse($diagnosis['ready']);
        self::assertContains('Missing canonical migration version: DoctrineMigrations\\Version20260618001945', $diagnosis['differences']);
    }

    public function testCommandReturnsOneForIncompleteSchema(): void
    {
        $tester = new CommandTester(new ClaimDiagnoseSchemaCommand(new ClaimSchemaInspector($this->connection())));

        self::assertSame(1, $tester->execute(['--json' => true]));
        self::assertStringContainsString('"ready":false', $tester->getDisplay());
    }

    public function testReconcileCommandRequiresExplicitConfirmationForMutation(): void
    {
        $connection = $this->connection();
        $inspector = new ClaimSchemaInspector($connection);
        $tester = new CommandTester(new ClaimReconcileSchemaCommand(new ClaimSchemaReconciler($connection, $inspector)));

        self::assertSame(1, $tester->execute(['--json' => true]));
        self::assertStringContainsString('claim_reconciliation_confirmation_required', $tester->getDisplay());
    }

    public function testReconcileCommandRejectsUnsupportedPlatformWithoutSecrets(): void
    {
        $connection = $this->connection();
        $inspector = new ClaimSchemaInspector($connection);
        $tester = new CommandTester(new ClaimReconcileSchemaCommand(new ClaimSchemaReconciler($connection, $inspector)));

        self::assertSame(1, $tester->execute(['--dry-run' => true, '--json' => true]));
        self::assertStringContainsString('unsupported_database_platform', $tester->getDisplay());
        self::assertStringNotContainsString('DATABASE_URL', $tester->getDisplay());
        self::assertStringNotContainsString('password', strtolower($tester->getDisplay()));
    }

    public function testReconcileRepairsOnlyLegacyRowsWithCanonicalRows(): void
    {
        $connection = $this->connection();
        $this->createReadyClaimSchema($connection);
        foreach (ClaimSchemaInspector::CLAIM_MIGRATION_LEGACY_ALIASES as $aliases) {
            $connection->insert('doctrine_migration_versions', [
                'version' => $aliases[0],
                'executed_at' => '2026-06-26 00:00:00',
                'execution_time' => 0,
            ]);
        }

        $reconciler = new ClaimSchemaReconciler($connection, new ClaimSchemaInspector($connection), true);
        $result = $reconciler->reconcile(false);

        self::assertTrue($result['ok']);
        self::assertTrue($result['changed']);
        self::assertNotEmpty($result['executed']);
        $versions = $connection->fetchFirstColumn('SELECT version FROM doctrine_migration_versions ORDER BY version');
        foreach (ClaimSchemaInspector::CLAIM_MIGRATION_VERSIONS as $version) {
            self::assertContains($version, $versions);
        }
        foreach (ClaimSchemaInspector::CLAIM_MIGRATION_LEGACY_ALIASES as $aliases) {
            self::assertNotContains($aliases[0], $versions);
        }
    }

    public function testReconcileRemovesLegacyWhenCanonicalAlreadyExists(): void
    {
        $connection = $this->connection();
        $this->createReadyClaimSchema($connection);
        $this->insertCanonicalClaimMigrations($connection);
        $connection->insert('doctrine_migration_versions', [
            'version' => 'DoctrineMigrationsVersion20260620000000',
            'executed_at' => '2026-06-26 00:00:00',
            'execution_time' => 0,
        ]);

        $result = (new ClaimSchemaReconciler($connection, new ClaimSchemaInspector($connection), true))->reconcile(false);

        self::assertTrue($result['ok']);
        self::assertTrue($result['changed']);
        self::assertFalse((bool) $connection->fetchOne('SELECT 1 FROM doctrine_migration_versions WHERE version = ?', ['DoctrineMigrationsVersion20260620000000']));
        self::assertTrue((bool) $connection->fetchOne('SELECT 1 FROM doctrine_migration_versions WHERE version = ?', ['DoctrineMigrations\\Version20260620000000']));
    }

    public function testReconcileNoOpsWhenSchemaAndMetadataAreCanonical(): void
    {
        $connection = $this->connection();
        $this->createReadyClaimSchema($connection);
        $this->insertCanonicalClaimMigrations($connection);

        $result = (new ClaimSchemaReconciler($connection, new ClaimSchemaInspector($connection), true))->reconcile(false);

        self::assertTrue($result['ok']);
        self::assertFalse($result['changed']);
        self::assertSame([], $result['plan']);
        self::assertSame([], $result['executed']);
    }

    public function testReconcileSecondExecutionIsNoOp(): void
    {
        $connection = $this->connection();
        $this->createReadyClaimSchema($connection);
        $connection->insert('doctrine_migration_versions', [
            'version' => 'DoctrineMigrationsVersion20260626000000',
            'executed_at' => '2026-06-26 00:00:00',
            'execution_time' => 0,
        ]);
        foreach (array_slice(ClaimSchemaInspector::CLAIM_MIGRATION_VERSIONS, 0, 3) as $version) {
            $connection->insert('doctrine_migration_versions', [
                'version' => $version,
                'executed_at' => '2026-06-26 00:00:00',
                'execution_time' => 0,
            ]);
        }

        $reconciler = new ClaimSchemaReconciler($connection, new ClaimSchemaInspector($connection), true);
        self::assertTrue($reconciler->reconcile(false)['changed']);
        $second = $reconciler->reconcile(false);

        self::assertTrue($second['ok']);
        self::assertFalse($second['changed']);
        self::assertSame([], $second['executed']);
    }

    public function testDryRunDoesNotModifyLegacyMetadata(): void
    {
        $connection = $this->connection();
        $this->createReadyClaimSchema($connection);
        $connection->insert('doctrine_migration_versions', [
            'version' => 'DoctrineMigrationsVersion20260618001945',
            'executed_at' => '2026-06-26 00:00:00',
            'execution_time' => 0,
        ]);

        $result = (new ClaimSchemaReconciler($connection, new ClaimSchemaInspector($connection), true))->reconcile(true);

        self::assertTrue($result['ok']);
        self::assertTrue($result['changed']);
        self::assertSame('add_canonical_migration_version', $result['plan'][0]['type']);
        self::assertSame([], $result['executed']);
        self::assertTrue((bool) $connection->fetchOne('SELECT 1 FROM doctrine_migration_versions WHERE version = ?', ['DoctrineMigrationsVersion20260618001945']));
        self::assertFalse((bool) $connection->fetchOne('SELECT 1 FROM doctrine_migration_versions WHERE version = ?', ['DoctrineMigrations\\Version20260618001945']));
    }

    public function testReconcileDoesNotRegisterOrDeleteLegacyWhenContractIsIncomplete(): void
    {
        $connection = $this->connection();
        $this->createReadyClaimSchema($connection);
        $connection->executeStatement('DROP INDEX uniq_claim_uuid');
        $connection->insert('location_claim_requests', ['id' => 1, 'claim_uuid' => '11111111-1111-1111-1111-111111111111']);
        $connection->insert('location_claim_requests', ['id' => 2, 'claim_uuid' => '11111111-1111-1111-1111-111111111111']);
        $connection->insert('doctrine_migration_versions', [
            'version' => 'DoctrineMigrationsVersion20260618001945',
            'executed_at' => '2026-06-26 00:00:00',
            'execution_time' => 0,
        ]);

        $result = (new ClaimSchemaReconciler($connection, new ClaimSchemaInspector($connection), true))->reconcile(false);

        self::assertFalse($result['ok']);
        self::assertSame('claim_uuid_duplicates_detected', $result['error_code']);
        self::assertTrue((bool) $connection->fetchOne('SELECT 1 FROM doctrine_migration_versions WHERE version = ?', ['DoctrineMigrationsVersion20260618001945']));
        self::assertFalse((bool) $connection->fetchOne('SELECT 1 FROM doctrine_migration_versions WHERE version = ?', ['DoctrineMigrations\\Version20260618001945']));
    }

    private function connection(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    private function createReadyClaimSchema(Connection $connection): void
    {
        $connection->executeStatement('CREATE TABLE location_claim_requests (id INTEGER PRIMARY KEY, source_type VARCHAR(32), canonical_location_id INTEGER, external_source_key VARCHAR(120), location_name VARCHAR(180), short_address TEXT, claimant_name VARCHAR(160), email VARCHAR(180), whatsapp_e164 VARCHAR(32), message TEXT, status VARCHAR(32), prefill_payload_json TEXT, evidence_links_json TEXT, review_checklist_json TEXT, review_notes TEXT, reviewed_at DATETIME, created_at DATETIME, updated_at DATETIME, claim_uuid CHAR(36), claimant_role VARCHAR(64), claimant_phone_e164 VARCHAR(32), business_phone_e164 VARCHAR(32), proposed_name VARCHAR(180), proposed_address_json TEXT, confirmed_latitude DOUBLE, confirmed_longitude DOUBLE, email_verified_at DATETIME, legal_acceptance_reference VARCHAR(255), resume_token_hash VARCHAR(64), resume_token_expires_at DATETIME, resume_token_revoked_at DATETIME, last_completed_step VARCHAR(64), submitted_at DATETIME, under_review_at DATETIME, needs_info_at DATETIME, approved_at DATETIME, rejected_at DATETIME, converted_at DATETIME, expires_at DATETIME, cancelled_at DATETIME, submission_mode VARCHAR(32))');
        $connection->executeStatement('CREATE UNIQUE INDEX uniq_claim_uuid ON location_claim_requests (claim_uuid)');
        $connection->executeStatement('CREATE TABLE location_claim_otps (id INTEGER PRIMARY KEY, claim_id INTEGER, purpose VARCHAR(64), code_hash VARCHAR(255), expires_at DATETIME, attempt_count INTEGER, max_attempts INTEGER, consumed_at DATETIME, requested_at DATETIME, last_attempt_at DATETIME, created_at DATETIME, FOREIGN KEY (claim_id) REFERENCES location_claim_requests(id))');
        $connection->executeStatement('CREATE INDEX idx_claim_otps_claim ON location_claim_otps (claim_id)');
        $connection->executeStatement('CREATE TABLE location_claim_evidences (id INTEGER PRIMARY KEY, claim_id INTEGER, evidence_type VARCHAR(64), storage_provider VARCHAR(64), bucket_name VARCHAR(120), object_key VARCHAR(512), storage_object_id VARCHAR(255), original_filename VARCHAR(255), mime_type VARCHAR(120), size_bytes INTEGER, checksum_sha256 VARCHAR(64), duration_seconds INTEGER, status VARCHAR(32), metadata_json TEXT, uploaded_at DATETIME, verified_at DATETIME, replaced_at DATETIME, deleted_at DATETIME, created_at DATETIME, updated_at DATETIME, FOREIGN KEY (claim_id) REFERENCES location_claim_requests(id))');
        $connection->executeStatement('CREATE INDEX idx_claim_evidences_claim ON location_claim_evidences (claim_id)');
        $connection->executeStatement('CREATE TABLE location_claim_access_sessions (id INTEGER PRIMARY KEY, claim_id INTEGER, token_hash VARCHAR(64), issued_at DATETIME, expires_at DATETIME, last_used_at DATETIME, revoked_at DATETIME, revocation_reason VARCHAR(120), created_from_otp_id INTEGER, scopes TEXT, FOREIGN KEY (claim_id) REFERENCES location_claim_requests(id), FOREIGN KEY (created_from_otp_id) REFERENCES location_claim_otps(id))');
        $connection->executeStatement('CREATE UNIQUE INDEX uniq_access_token_hash ON location_claim_access_sessions (token_hash)');
        $connection->executeStatement('CREATE INDEX idx_access_session_claim ON location_claim_access_sessions (claim_id)');
        $connection->executeStatement('CREATE INDEX idx_access_session_expires ON location_claim_access_sessions (expires_at)');
        $connection->executeStatement('CREATE INDEX idx_access_session_revoked ON location_claim_access_sessions (revoked_at)');
        $connection->executeStatement('CREATE INDEX idx_access_session_otp ON location_claim_access_sessions (created_from_otp_id)');
        $connection->executeStatement('CREATE TABLE doctrine_migration_versions (version VARCHAR(191) NOT NULL PRIMARY KEY, executed_at DATETIME, execution_time INTEGER)');
    }

    private function insertCanonicalClaimMigrations(Connection $connection): void
    {
        foreach (ClaimSchemaInspector::CLAIM_MIGRATION_VERSIONS as $version) {
            $connection->insert('doctrine_migration_versions', [
                'version' => $version,
                'executed_at' => '2026-06-26 00:00:00',
                'execution_time' => 0,
            ]);
        }
    }
}
