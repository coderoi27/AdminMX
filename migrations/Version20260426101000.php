<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426101000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates plugin and demo batch tables to support canonical fake_seed orchestration.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE system_plugins (id INT AUTO_INCREMENT NOT NULL, plugin_key VARCHAR(64) NOT NULL, name VARCHAR(120) NOT NULL, is_enabled TINYINT(1) NOT NULL, status VARCHAR(32) NOT NULL, config_json JSON DEFAULT NULL, last_run_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_91FE8A7F4C54C8C5 (plugin_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE demo_seed_batches (id INT AUTO_INCREMENT NOT NULL, plugin_id INT NOT NULL, demo_city_id INT DEFAULT NULL, demo_zone_id INT DEFAULT NULL, created_by_admin_id INT DEFAULT NULL, name VARCHAR(160) NOT NULL, status VARCHAR(32) NOT NULL, country_code VARCHAR(2) NOT NULL, state VARCHAR(120) DEFAULT NULL, city VARCHAR(120) DEFAULT NULL, region_label VARCHAR(120) DEFAULT NULL, source_address LONGTEXT DEFAULT NULL, center_latitude NUMERIC(10, 7) DEFAULT NULL, center_longitude NUMERIC(10, 7) DEFAULT NULL, radius_meters INT NOT NULL, requested_locations_count INT NOT NULL, generated_locations_count INT NOT NULL, seed_value VARCHAR(64) DEFAULT NULL, dictionary_version VARCHAR(64) DEFAULT NULL, config_json JSON DEFAULT NULL, seeded_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', disabled_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', purged_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_B2E81C595845665A (plugin_id), INDEX IDX_B2E81C598BAC62AF (demo_city_id), INDEX IDX_B2E81C599F2C3FAB (demo_zone_id), INDEX IDX_B2E81C594B4BCCE3 (created_by_admin_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE demo_seed_batch_items (id INT AUTO_INCREMENT NOT NULL, demo_seed_batch_id INT NOT NULL, merchant_location_id INT NOT NULL, status VARCHAR(32) NOT NULL, generated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', expired_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', purged_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', notes LONGTEXT DEFAULT NULL, INDEX IDX_31CB7B7914738D70 (demo_seed_batch_id), INDEX IDX_31CB7B7955964FD (merchant_location_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE demo_seed_batches ADD CONSTRAINT FK_B2E81C595845665A FOREIGN KEY (plugin_id) REFERENCES system_plugins (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE demo_seed_batches ADD CONSTRAINT FK_B2E81C598BAC62AF FOREIGN KEY (demo_city_id) REFERENCES demo_cities (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE demo_seed_batches ADD CONSTRAINT FK_B2E81C599F2C3FAB FOREIGN KEY (demo_zone_id) REFERENCES demo_zones (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE demo_seed_batches ADD CONSTRAINT FK_B2E81C594B4BCCE3 FOREIGN KEY (created_by_admin_id) REFERENCES admin_users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE demo_seed_batch_items ADD CONSTRAINT FK_31CB7B7914738D70 FOREIGN KEY (demo_seed_batch_id) REFERENCES demo_seed_batches (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE demo_seed_batch_items ADD CONSTRAINT FK_31CB7B7955964FD FOREIGN KEY (merchant_location_id) REFERENCES merchant_locations (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demo_seed_batch_items DROP FOREIGN KEY FK_31CB7B7914738D70');
        $this->addSql('ALTER TABLE demo_seed_batch_items DROP FOREIGN KEY FK_31CB7B7955964FD');
        $this->addSql('ALTER TABLE demo_seed_batches DROP FOREIGN KEY FK_B2E81C595845665A');
        $this->addSql('ALTER TABLE demo_seed_batches DROP FOREIGN KEY FK_B2E81C598BAC62AF');
        $this->addSql('ALTER TABLE demo_seed_batches DROP FOREIGN KEY FK_B2E81C599F2C3FAB');
        $this->addSql('ALTER TABLE demo_seed_batches DROP FOREIGN KEY FK_B2E81C594B4BCCE3');
        $this->addSql('DROP TABLE demo_seed_batch_items');
        $this->addSql('DROP TABLE demo_seed_batches');
        $this->addSql('DROP TABLE system_plugins');
    }
}
