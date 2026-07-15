<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Table;

final class ClaimSchemaInspector
{
    public const MIGRATION_TABLE = 'doctrine_migration_versions';
    public const VERSION_20260618001945 = 'DoctrineMigrations\\Version20260618001945';
    public const VERSION_20260618082000 = 'DoctrineMigrations\\Version20260618082000';
    public const VERSION_20260620000000 = 'DoctrineMigrations\\Version20260620000000';
    public const VERSION_20260626000000 = 'DoctrineMigrations\\Version20260626000000';

    /** @var array<string, list<string>> */
    private const REQUIRED_COLUMNS = [
        'location_claim_requests' => [
            'id', 'source_type', 'canonical_location_id', 'external_source_key', 'location_name', 'short_address',
            'claimant_name', 'email', 'whatsapp_e164', 'message', 'status', 'prefill_payload_json',
            'evidence_links_json', 'review_checklist_json', 'review_notes', 'reviewed_at', 'created_at', 'updated_at',
            'claim_uuid', 'claimant_role', 'claimant_phone_e164', 'business_phone_e164', 'proposed_name',
            'proposed_address_json', 'confirmed_latitude', 'confirmed_longitude', 'email_verified_at',
            'legal_acceptance_reference', 'resume_token_hash', 'resume_token_expires_at', 'resume_token_revoked_at',
            'last_completed_step', 'submitted_at', 'under_review_at', 'needs_info_at', 'approved_at', 'rejected_at',
            'converted_at', 'expires_at', 'cancelled_at', 'submission_mode',
        ],
        'location_claim_otps' => [
            'id', 'claim_id', 'purpose', 'code_hash', 'expires_at', 'attempt_count', 'max_attempts', 'consumed_at',
            'requested_at', 'last_attempt_at', 'created_at',
        ],
        'location_claim_evidences' => [
            'id', 'claim_id', 'evidence_type', 'storage_provider', 'bucket_name', 'object_key', 'storage_object_id',
            'original_filename', 'mime_type', 'size_bytes', 'checksum_sha256', 'duration_seconds', 'status',
            'metadata_json', 'uploaded_at', 'verified_at', 'replaced_at', 'deleted_at', 'created_at', 'updated_at',
        ],
        'location_claim_access_sessions' => [
            'id', 'claim_id', 'token_hash', 'issued_at', 'expires_at', 'last_used_at', 'revoked_at',
            'revocation_reason', 'created_from_otp_id', 'scopes',
        ],
    ];

    /** @var list<string> */
    public const CLAIM_MIGRATION_VERSIONS = [
        self::VERSION_20260618001945,
        self::VERSION_20260618082000,
        self::VERSION_20260620000000,
        self::VERSION_20260626000000,
    ];

    /** @var array<string, list<string>> */
    public const CLAIM_MIGRATION_LEGACY_ALIASES = [
        self::VERSION_20260618001945 => ['DoctrineMigrationsVersion20260618001945', 'Version20260618001945', '20260618001945'],
        self::VERSION_20260618082000 => ['DoctrineMigrationsVersion20260618082000', 'Version20260618082000', '20260618082000'],
        self::VERSION_20260620000000 => ['DoctrineMigrationsVersion20260620000000', 'Version20260620000000', '20260620000000'],
        self::VERSION_20260626000000 => ['DoctrineMigrationsVersion20260626000000', 'Version20260626000000', '20260626000000'],
    ];

    /** @var array<string, list<string>> */
    private const VERSION_COLUMN_CONTRACTS = [
        self::VERSION_20260618001945 => [
            'location_claim_requests.claim_uuid',
            'location_claim_requests.claimant_role',
            'location_claim_requests.claimant_phone_e164',
            'location_claim_requests.business_phone_e164',
            'location_claim_requests.proposed_name',
            'location_claim_requests.proposed_address_json',
            'location_claim_requests.confirmed_latitude',
            'location_claim_requests.confirmed_longitude',
            'location_claim_requests.email_verified_at',
            'location_claim_requests.legal_acceptance_reference',
            'location_claim_requests.resume_token_hash',
            'location_claim_requests.resume_token_expires_at',
            'location_claim_requests.resume_token_revoked_at',
            'location_claim_requests.last_completed_step',
            'location_claim_requests.submitted_at',
            'location_claim_requests.under_review_at',
            'location_claim_requests.needs_info_at',
            'location_claim_requests.approved_at',
            'location_claim_requests.rejected_at',
            'location_claim_requests.converted_at',
            'location_claim_requests.expires_at',
            'location_claim_requests.cancelled_at',
            'location_claim_evidences.id',
            'location_claim_otps.id',
        ],
        self::VERSION_20260618082000 => [
            'location_claim_access_sessions.id',
            'location_claim_access_sessions.claim_id',
            'location_claim_access_sessions.token_hash',
            'location_claim_access_sessions.issued_at',
            'location_claim_access_sessions.expires_at',
            'location_claim_access_sessions.last_used_at',
            'location_claim_access_sessions.revoked_at',
            'location_claim_access_sessions.revocation_reason',
            'location_claim_access_sessions.created_from_otp_id',
            'location_claim_access_sessions.scopes',
        ],
        self::VERSION_20260620000000 => [
            'location_claim_requests.submission_mode',
        ],
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, mixed> */
    public function inspect(): array
    {
        $schemaManager = $this->connection->createSchemaManager();
        $differences = [];
        $tables = [];

        foreach (self::REQUIRED_COLUMNS as $tableName => $requiredColumns) {
            $exists = $schemaManager->tablesExist([$tableName]);
            $tableData = ['exists' => $exists, 'columns' => [], 'indexes' => [], 'foreign_keys' => []];

            if (!$exists) {
                $differences[] = sprintf('Missing table: %s', $tableName);
                $tables[$tableName] = $tableData;
                continue;
            }

            $table = $schemaManager->introspectTable($tableName);
            $tableData = $this->tableData($table);
            $tableData['exists'] = true;

            foreach ($requiredColumns as $column) {
                if (!$table->hasColumn($column)) {
                    $differences[] = sprintf('Missing column: %s.%s', $tableName, $column);
                }
            }

            if ($tableName === 'location_claim_requests') {
                foreach (['claimant_name', 'email'] as $columnName) {
                    if ($table->hasColumn($columnName) && $table->getColumn($columnName)->getNotnull()) {
                        $differences[] = sprintf('Column must be nullable: %s.%s', $tableName, $columnName);
                    }
                }

                if (!$this->hasUniqueIndexForColumns($table, ['claim_uuid'])) {
                    $differences[] = 'Missing unique index: location_claim_requests.claim_uuid';
                }
            }

            foreach ($this->requiredForeignKeys($tableName) as [$local, $foreignTable, $foreignColumn]) {
                if (!$this->hasForeignKey($table, $local, $foreignTable, $foreignColumn)) {
                    $differences[] = sprintf('Missing foreign key: %s.%s -> %s.%s', $tableName, $local, $foreignTable, $foreignColumn);
                }
            }

            $tables[$tableName] = $tableData;
        }

        $metadata = $this->metadataStorageData();
        $metadataExists = $metadata['exists'];
        $migrations = [];
        if ($metadataExists) {
            $migrations = $this->connection->fetchFirstColumn(sprintf('SELECT version FROM %s ORDER BY version', self::MIGRATION_TABLE));
        }
        $migrationRows = $this->classifyMigrationRows($migrations);

        foreach ($metadata['differences'] as $difference) {
            $differences[] = $difference;
        }

        $contracts = $this->migrationContracts();
        foreach (self::CLAIM_MIGRATION_VERSIONS as $canonicalVersion) {
            if (!in_array($canonicalVersion, $migrationRows['canonical'], true)) {
                $differences[] = sprintf('Missing canonical migration version: %s', $canonicalVersion);
            }
        }
        foreach ($migrationRows['legacy'] as $legacyVersion) {
            $differences[] = sprintf('Legacy migration version row present: %s', $legacyVersion);
        }

        $metadataConsistent = $metadata['ready'] === true
            && count($migrationRows['canonical']) === count(self::CLAIM_MIGRATION_VERSIONS)
            && $migrationRows['legacy'] === [];

        return [
            'ready' => $differences === [],
            'database' => $this->connection->getDatabase(),
            'server_version' => $this->serverVersion(),
            'platform' => $this->connection->getDatabasePlatform()::class,
            'metadata_table' => $metadata,
            'claim_migrations' => array_values(array_filter($migrations, static fn (mixed $version): bool => str_contains((string) $version, '202606'))),
            'claim_migrations_canonical' => $migrationRows['canonical'],
            'claim_migrations_legacy' => $migrationRows['legacy'],
            'metadata_consistent' => $metadataConsistent,
            'migration_contracts' => $contracts,
            'tables' => $tables,
            'differences' => $differences,
        ];
    }

    /** @return array<string, mixed> */
    private function tableData(Table $table): array
    {
        $columns = [];
        foreach ($table->getColumns() as $column) {
            $columns[$column->getName()] = [
                'type' => $column->getType()::class,
                'length' => $column->getLength(),
                'notnull' => $column->getNotnull(),
            ];
        }

        return [
            'columns' => $columns,
            'indexes' => array_map(static fn ($index): array => [
                'name' => $index->getName(), 'columns' => $index->getColumns(), 'unique' => $index->isUnique(),
            ], $table->getIndexes()),
            'foreign_keys' => array_map(static fn ($foreignKey): array => [
                'name' => $foreignKey->getName(), 'local_columns' => $foreignKey->getLocalColumns(),
                'foreign_table' => $foreignKey->getForeignTableName(), 'foreign_columns' => $foreignKey->getForeignColumns(),
            ], $table->getForeignKeys()),
        ];
    }

    /** @return list<array{string, string, string}> */
    private function requiredForeignKeys(string $table): array
    {
        return match ($table) {
            'location_claim_otps', 'location_claim_evidences' => [['claim_id', 'location_claim_requests', 'id']],
            'location_claim_access_sessions' => [
                ['claim_id', 'location_claim_requests', 'id'],
                ['created_from_otp_id', 'location_claim_otps', 'id'],
            ],
            default => [],
        };
    }

    private function hasUniqueIndexForColumns(Table $table, array $columns): bool
    {
        foreach ($table->getIndexes() as $index) {
            if ($index->isUnique() && $index->getColumns() === $columns) {
                return true;
            }
        }

        return false;
    }

    private function hasForeignKey(Table $table, string $local, string $foreignTable, string $foreignColumn): bool
    {
        foreach ($table->getForeignKeys() as $foreignKey) {
            if ($foreignKey->getLocalColumns() === [$local]
                && $foreignKey->getForeignTableName() === $foreignTable
                && $foreignKey->getForeignColumns() === [$foreignColumn]) {
                return true;
            }
        }

        return false;
    }

    private function serverVersion(): string
    {
        return (string) $this->connection->fetchOne(
            $this->connection->getDatabasePlatform() instanceof SQLitePlatform ? 'SELECT sqlite_version()' : 'SELECT VERSION()',
        );
    }

    /** @return array{name: string, exists: bool, ready: bool, columns: array<string, mixed>, indexes: array<int|string, mixed>, differences: list<string>} */
    private function metadataStorageData(): array
    {
        $schemaManager = $this->connection->createSchemaManager();
        $differences = [];
        $data = [
            'name' => self::MIGRATION_TABLE,
            'exists' => false,
            'ready' => false,
            'columns' => [],
            'indexes' => [],
            'differences' => [],
        ];

        if (!$schemaManager->tablesExist([self::MIGRATION_TABLE])) {
            $data['differences'] = ['Missing Doctrine metadata table: ' . self::MIGRATION_TABLE];

            return $data;
        }

        $table = $schemaManager->introspectTable(self::MIGRATION_TABLE);
        $tableData = $this->tableData($table);
        $data['exists'] = true;
        $data['columns'] = $tableData['columns'];
        $data['indexes'] = $tableData['indexes'];

        foreach (['version', 'executed_at', 'execution_time'] as $columnName) {
            if (!$table->hasColumn($columnName)) {
                $differences[] = sprintf('Missing metadata column: %s.%s', self::MIGRATION_TABLE, $columnName);
            }
        }

        if ($table->hasColumn('version')) {
            $version = $table->getColumn('version');
            if ($version->getLength() !== 191) {
                $differences[] = sprintf('Metadata column length must be 191: %s.version', self::MIGRATION_TABLE);
            }
            if (!$version->getNotnull()) {
                $differences[] = sprintf('Metadata column must be not null: %s.version', self::MIGRATION_TABLE);
            }
        }

        if ($table->hasColumn('executed_at') && $table->getColumn('executed_at')->getNotnull()) {
            $differences[] = sprintf('Metadata column must be nullable: %s.executed_at', self::MIGRATION_TABLE);
        }

        if ($table->hasColumn('execution_time') && $table->getColumn('execution_time')->getNotnull()) {
            $differences[] = sprintf('Metadata column must be nullable: %s.execution_time', self::MIGRATION_TABLE);
        }

        if (!$this->hasPrimaryKeyForColumns($table, ['version'])) {
            $differences[] = sprintf('Missing primary key: %s.version', self::MIGRATION_TABLE);
        }

        $data['differences'] = $differences;
        $data['ready'] = $differences === [];

        return $data;
    }

    /** @return array<string, array{ready: bool, differences: list<string>}> */
    private function migrationContracts(): array
    {
        $contracts = [];
        foreach (self::CLAIM_MIGRATION_VERSIONS as $version) {
            $contracts[$version] = ['ready' => false, 'differences' => []];
        }

        $contracts[self::VERSION_20260618001945] = $this->contractResult(self::VERSION_20260618001945, [
            'Missing unique index: location_claim_requests.claim_uuid',
            'Missing foreign key: location_claim_evidences.claim_id -> location_claim_requests.id',
            'Missing foreign key: location_claim_otps.claim_id -> location_claim_requests.id',
        ]);
        $contracts[self::VERSION_20260618082000] = $this->contractResult(self::VERSION_20260618082000, [
            'Missing unique index: location_claim_access_sessions.token_hash',
            'Missing foreign key: location_claim_access_sessions.claim_id -> location_claim_requests.id',
            'Missing foreign key: location_claim_access_sessions.created_from_otp_id -> location_claim_otps.id',
        ]);
        $contracts[self::VERSION_20260620000000] = $this->contractResult(self::VERSION_20260620000000, [
            'Column must be nullable: location_claim_requests.claimant_name',
            'Column must be nullable: location_claim_requests.email',
        ]);

        $allDifferences = [];
        foreach ([self::VERSION_20260618001945, self::VERSION_20260618082000, self::VERSION_20260620000000] as $version) {
            $allDifferences = array_merge($allDifferences, $contracts[$version]['differences']);
        }
        $contracts[self::VERSION_20260626000000] = [
            'ready' => $allDifferences === [],
            'differences' => array_values(array_unique($allDifferences)),
        ];

        return $contracts;
    }

    /** @param list<string> $extraRequiredDifferences */
    private function contractResult(string $version, array $extraRequiredDifferences): array
    {
        $schemaManager = $this->connection->createSchemaManager();
        $differences = [];

        foreach (self::VERSION_COLUMN_CONTRACTS[$version] ?? [] as $columnReference) {
            [$tableName, $columnName] = explode('.', $columnReference, 2);
            if (!$schemaManager->tablesExist([$tableName])) {
                $differences[] = sprintf('Missing table: %s', $tableName);
                continue;
            }
            $table = $schemaManager->introspectTable($tableName);
            if (!$table->hasColumn($columnName)) {
                $differences[] = sprintf('Missing column: %s.%s', $tableName, $columnName);
            }
        }

        foreach ($extraRequiredDifferences as $requiredDifference) {
            if (!$this->currentSchemaSatisfies($requiredDifference)) {
                $differences[] = $requiredDifference;
            }
        }

        return [
            'ready' => $differences === [],
            'differences' => array_values(array_unique($differences)),
        ];
    }

    private function currentSchemaSatisfies(string $difference): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        return match ($difference) {
            'Missing unique index: location_claim_requests.claim_uuid' => $schemaManager->tablesExist(['location_claim_requests'])
                && $this->hasUniqueIndexForColumns($schemaManager->introspectTable('location_claim_requests'), ['claim_uuid']),
            'Missing unique index: location_claim_access_sessions.token_hash' => $schemaManager->tablesExist(['location_claim_access_sessions'])
                && $this->hasUniqueIndexForColumns($schemaManager->introspectTable('location_claim_access_sessions'), ['token_hash']),
            'Missing foreign key: location_claim_evidences.claim_id -> location_claim_requests.id' => $schemaManager->tablesExist(['location_claim_evidences'])
                && $this->hasForeignKey($schemaManager->introspectTable('location_claim_evidences'), 'claim_id', 'location_claim_requests', 'id'),
            'Missing foreign key: location_claim_otps.claim_id -> location_claim_requests.id' => $schemaManager->tablesExist(['location_claim_otps'])
                && $this->hasForeignKey($schemaManager->introspectTable('location_claim_otps'), 'claim_id', 'location_claim_requests', 'id'),
            'Missing foreign key: location_claim_access_sessions.claim_id -> location_claim_requests.id' => $schemaManager->tablesExist(['location_claim_access_sessions'])
                && $this->hasForeignKey($schemaManager->introspectTable('location_claim_access_sessions'), 'claim_id', 'location_claim_requests', 'id'),
            'Missing foreign key: location_claim_access_sessions.created_from_otp_id -> location_claim_otps.id' => $schemaManager->tablesExist(['location_claim_access_sessions'])
                && $this->hasForeignKey($schemaManager->introspectTable('location_claim_access_sessions'), 'created_from_otp_id', 'location_claim_otps', 'id'),
            'Column must be nullable: location_claim_requests.claimant_name' => $schemaManager->tablesExist(['location_claim_requests'])
                && $schemaManager->introspectTable('location_claim_requests')->hasColumn('claimant_name')
                && !$schemaManager->introspectTable('location_claim_requests')->getColumn('claimant_name')->getNotnull(),
            'Column must be nullable: location_claim_requests.email' => $schemaManager->tablesExist(['location_claim_requests'])
                && $schemaManager->introspectTable('location_claim_requests')->hasColumn('email')
                && !$schemaManager->introspectTable('location_claim_requests')->getColumn('email')->getNotnull(),
            default => false,
        };
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

    public static function canonicalizeClaimMigrationVersion(string $version): ?string
    {
        if (in_array($version, self::CLAIM_MIGRATION_VERSIONS, true)) {
            return $version;
        }

        foreach (self::CLAIM_MIGRATION_LEGACY_ALIASES as $canonical => $aliases) {
            if (in_array($version, $aliases, true)) {
                return $canonical;
            }
        }

        return null;
    }

    public static function isCanonicalClaimMigrationVersion(string $version): bool
    {
        return in_array($version, self::CLAIM_MIGRATION_VERSIONS, true);
    }

    public static function isLegacyClaimMigrationVersion(string $version): bool
    {
        $canonical = self::canonicalizeClaimMigrationVersion($version);

        return $canonical !== null && $canonical !== $version;
    }

    /** @param list<mixed> $versions @return array{canonical: list<string>, legacy: list<string>} */
    private function classifyMigrationRows(array $versions): array
    {
        $canonical = [];
        $legacy = [];

        foreach ($versions as $rawVersion) {
            $version = (string) $rawVersion;
            if (self::isCanonicalClaimMigrationVersion($version)) {
                $canonical[] = $version;
            } elseif (self::isLegacyClaimMigrationVersion($version)) {
                $legacy[] = $version;
            }
        }

        sort($canonical);
        sort($legacy);

        return [
            'canonical' => array_values(array_unique($canonical)),
            'legacy' => array_values(array_unique($legacy)),
        ];
    }
}
