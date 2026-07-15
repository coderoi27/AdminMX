<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260707051000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates public catalog media library tables separated from claim evidence storage.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE catalog_media_assets (id INT AUTO_INCREMENT NOT NULL, created_by_id INT DEFAULT NULL, uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', original_filename VARCHAR(255) NOT NULL, storage_provider VARCHAR(64) NOT NULL, logical_bucket VARCHAR(120) DEFAULT NULL, object_key VARCHAR(1024) NOT NULL, public_url VARCHAR(1024) DEFAULT NULL, mime_type VARCHAR(120) NOT NULL, media_type VARCHAR(16) NOT NULL, width INT DEFAULT NULL, height INT DEFAULT NULL, duration_seconds INT DEFAULT NULL, bytes INT NOT NULL, checksum VARCHAR(128) DEFAULT NULL, title VARCHAR(180) NOT NULL, alt_text VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, author VARCHAR(180) DEFAULT NULL, source_type VARCHAR(32) NOT NULL, source_url VARCHAR(1024) DEFAULT NULL, license_name VARCHAR(160) DEFAULT NULL, attribution LONGTEXT DEFAULT NULL, rights_verified TINYINT(1) NOT NULL, ai_generated TINYINT(1) NOT NULL, ai_tool VARCHAR(120) DEFAULT NULL, status VARCHAR(32) NOT NULL, moderation_status VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_CATALOG_MEDIA_ASSETS_UUID (uuid), INDEX IDX_CATALOG_MEDIA_ASSETS_CREATED_BY (created_by_id), INDEX IDX_CATALOG_MEDIA_ASSETS_STATUS (status), INDEX IDX_CATALOG_MEDIA_ASSETS_MEDIA_TYPE (media_type), INDEX IDX_CATALOG_MEDIA_ASSETS_OBJECT_KEY (object_key(191)), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE catalog_media_albums (id INT AUTO_INCREMENT NOT NULL, cover_asset_id INT DEFAULT NULL, uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', name VARCHAR(160) NOT NULL, slug VARCHAR(160) NOT NULL, description LONGTEXT DEFAULT NULL, active TINYINT(1) NOT NULL, sort_order INT NOT NULL, version INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_CATALOG_MEDIA_ALBUMS_UUID (uuid), UNIQUE INDEX UNIQ_CATALOG_MEDIA_ALBUMS_SLUG (slug), INDEX IDX_CATALOG_MEDIA_ALBUMS_COVER_ASSET (cover_asset_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE catalog_media_tags (id INT AUTO_INCREMENT NOT NULL, uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', name VARCHAR(120) NOT NULL, slug VARCHAR(120) NOT NULL, tag_group VARCHAR(80) DEFAULT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_CATALOG_MEDIA_TAGS_UUID (uuid), UNIQUE INDEX UNIQ_CATALOG_MEDIA_TAGS_SLUG (slug), INDEX IDX_CATALOG_MEDIA_TAGS_GROUP (tag_group), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE catalog_media_asset_tags (id INT AUTO_INCREMENT NOT NULL, asset_id INT NOT NULL, tag_id INT NOT NULL, UNIQUE INDEX UNIQ_CATALOG_MEDIA_ASSET_TAG (asset_id, tag_id), INDEX IDX_CATALOG_MEDIA_ASSET_TAG_ASSET (asset_id), INDEX IDX_CATALOG_MEDIA_ASSET_TAG_TAG (tag_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE catalog_media_usage_pools (id INT AUTO_INCREMENT NOT NULL, uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', name VARCHAR(160) NOT NULL, slug VARCHAR(160) NOT NULL, description LONGTEXT DEFAULT NULL, usage_slot VARCHAR(40) NOT NULL, active TINYINT(1) NOT NULL, pool_version INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_CATALOG_MEDIA_USAGE_POOLS_UUID (uuid), UNIQUE INDEX UNIQ_CATALOG_MEDIA_USAGE_POOLS_SLUG (slug), INDEX IDX_CATALOG_MEDIA_USAGE_POOLS_SLOT (usage_slot), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE catalog_media_category_assignments (id INT AUTO_INCREMENT NOT NULL, category_id INT NOT NULL, pool_id INT DEFAULT NULL, asset_id INT DEFAULT NULL, usage_slot VARCHAR(40) NOT NULL, priority INT NOT NULL, weight INT NOT NULL, active TINYINT(1) NOT NULL, valid_from DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', valid_to DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CATALOG_MEDIA_CATEGORY_SLOT (category_id, usage_slot), INDEX IDX_CATALOG_MEDIA_CATEGORY_ASSIGNMENT_POOL (pool_id), INDEX IDX_CATALOG_MEDIA_CATEGORY_ASSIGNMENT_ASSET (asset_id), INDEX IDX_CATALOG_MEDIA_CATEGORY_ASSIGNMENT_ACTIVE (active), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE catalog_media_assets ADD CONSTRAINT FK_CATALOG_MEDIA_ASSETS_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES admin_users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE catalog_media_albums ADD CONSTRAINT FK_CATALOG_MEDIA_ALBUMS_COVER_ASSET FOREIGN KEY (cover_asset_id) REFERENCES catalog_media_assets (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE catalog_media_asset_tags ADD CONSTRAINT FK_CATALOG_MEDIA_ASSET_TAG_ASSET FOREIGN KEY (asset_id) REFERENCES catalog_media_assets (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_media_asset_tags ADD CONSTRAINT FK_CATALOG_MEDIA_ASSET_TAG_TAG FOREIGN KEY (tag_id) REFERENCES catalog_media_tags (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_media_category_assignments ADD CONSTRAINT FK_CATALOG_MEDIA_CATEGORY_ASSIGNMENT_CATEGORY FOREIGN KEY (category_id) REFERENCES location_categories (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_media_category_assignments ADD CONSTRAINT FK_CATALOG_MEDIA_CATEGORY_ASSIGNMENT_POOL FOREIGN KEY (pool_id) REFERENCES catalog_media_usage_pools (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_media_category_assignments ADD CONSTRAINT FK_CATALOG_MEDIA_CATEGORY_ASSIGNMENT_ASSET FOREIGN KEY (asset_id) REFERENCES catalog_media_assets (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE catalog_media_category_assignments DROP FOREIGN KEY FK_CATALOG_MEDIA_CATEGORY_ASSIGNMENT_CATEGORY');
        $this->addSql('ALTER TABLE catalog_media_category_assignments DROP FOREIGN KEY FK_CATALOG_MEDIA_CATEGORY_ASSIGNMENT_POOL');
        $this->addSql('ALTER TABLE catalog_media_category_assignments DROP FOREIGN KEY FK_CATALOG_MEDIA_CATEGORY_ASSIGNMENT_ASSET');
        $this->addSql('ALTER TABLE catalog_media_asset_tags DROP FOREIGN KEY FK_CATALOG_MEDIA_ASSET_TAG_ASSET');
        $this->addSql('ALTER TABLE catalog_media_asset_tags DROP FOREIGN KEY FK_CATALOG_MEDIA_ASSET_TAG_TAG');
        $this->addSql('ALTER TABLE catalog_media_albums DROP FOREIGN KEY FK_CATALOG_MEDIA_ALBUMS_COVER_ASSET');
        $this->addSql('ALTER TABLE catalog_media_assets DROP FOREIGN KEY FK_CATALOG_MEDIA_ASSETS_CREATED_BY');
        $this->addSql('DROP TABLE catalog_media_category_assignments');
        $this->addSql('DROP TABLE catalog_media_usage_pools');
        $this->addSql('DROP TABLE catalog_media_asset_tags');
        $this->addSql('DROP TABLE catalog_media_tags');
        $this->addSql('DROP TABLE catalog_media_albums');
        $this->addSql('DROP TABLE catalog_media_assets');
    }
}
