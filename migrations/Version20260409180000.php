<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates admin and core bootstrap tables for the alpha runtime.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE admin_users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, full_name VARCHAR(120) NOT NULL, password_hash VARCHAR(255) NOT NULL, status VARCHAR(32) NOT NULL, role_key VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_F18AAD9EE7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE merchants (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(160) NOT NULL, slug VARCHAR(180) NOT NULL, legal_name VARCHAR(180) DEFAULT NULL, description LONGTEXT DEFAULT NULL, contact_email VARCHAR(180) DEFAULT NULL, contact_phone_e164 VARCHAR(32) DEFAULT NULL, status VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_6A6B2DC3989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE merchant_locations (id INT AUTO_INCREMENT NOT NULL, merchant_id INT NOT NULL, name VARCHAR(160) NOT NULL, slug VARCHAR(180) NOT NULL, location_type VARCHAR(16) NOT NULL, status VARCHAR(32) NOT NULL, publication_state VARCHAR(32) NOT NULL, phone_e164 VARCHAR(32) DEFAULT NULL, whatsapp_e164 VARCHAR(32) DEFAULT NULL, whatsapp_enabled TINYINT(1) NOT NULL, short_description LONGTEXT DEFAULT NULL, is_claimable TINYINT(1) NOT NULL, claimed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_483F3F33727ACA70 (merchant_id), UNIQUE INDEX UNIQ_483F3F33989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE place_addresses (id INT AUTO_INCREMENT NOT NULL, location_id INT NOT NULL, label VARCHAR(80) DEFAULT NULL, country_code VARCHAR(2) NOT NULL, state VARCHAR(120) DEFAULT NULL, city VARCHAR(120) DEFAULT NULL, neighborhood VARCHAR(120) DEFAULT NULL, street VARCHAR(180) DEFAULT NULL, ext_number VARCHAR(20) DEFAULT NULL, int_number VARCHAR(20) DEFAULT NULL, zip_code VARCHAR(12) DEFAULT NULL, reference LONGTEXT DEFAULT NULL, latitude NUMERIC(10, 7) NOT NULL, longitude NUMERIC(10, 7) NOT NULL, is_primary TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_5AA59D8764D218E (location_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE owner_leads (id INT AUTO_INCREMENT NOT NULL, owner_name VARCHAR(160) NOT NULL, business_name VARCHAR(160) NOT NULL, city VARCHAR(120) NOT NULL, email VARCHAR(180) NOT NULL, whatsapp_e164 VARCHAR(32) DEFAULT NULL, business_type VARCHAR(80) DEFAULT NULL, message LONGTEXT DEFAULT NULL, status VARCHAR(32) NOT NULL, source_channel VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE owner_invitations (id INT AUTO_INCREMENT NOT NULL, owner_lead_id INT DEFAULT NULL, email VARCHAR(180) NOT NULL, token_hash VARCHAR(255) NOT NULL, invite_code VARCHAR(32) DEFAULT NULL, invitation_type VARCHAR(32) NOT NULL, message_subject VARCHAR(180) NOT NULL, message_body LONGTEXT NOT NULL, status VARCHAR(32) NOT NULL, expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', opened_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', used_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_A4CF76CF3B682539 (owner_lead_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE public_invitations (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, token_hash VARCHAR(255) NOT NULL, invite_code VARCHAR(32) DEFAULT NULL, campaign_name VARCHAR(120) DEFAULT NULL, campaign_type VARCHAR(32) NOT NULL, message_subject VARCHAR(180) NOT NULL, message_body LONGTEXT NOT NULL, status VARCHAR(32) NOT NULL, expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', opened_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', used_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE demo_cities (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, slug VARCHAR(160) NOT NULL, state_code VARCHAR(12) DEFAULT NULL, country_code VARCHAR(2) NOT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_70D5EF9D989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE demo_zones (id INT AUTO_INCREMENT NOT NULL, city_id INT NOT NULL, name VARCHAR(120) NOT NULL, slug VARCHAR(160) NOT NULL, zone_type VARCHAR(16) NOT NULL, polygon_json JSON DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_DA4E35498BAC62AF (city_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE narrative_campaigns (id INT AUTO_INCREMENT NOT NULL, city_id INT DEFAULT NULL, zone_id INT DEFAULT NULL, name VARCHAR(160) NOT NULL, slug VARCHAR(180) NOT NULL, campaign_type VARCHAR(32) NOT NULL, status VARCHAR(32) NOT NULL, starts_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ends_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_BDCA528E8BAC62AF (city_id), INDEX IDX_BDCA528E9F2C3FAB (zone_id), UNIQUE INDEX UNIQ_BDCA528E989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE event_logs (id INT AUTO_INCREMENT NOT NULL, event_name VARCHAR(120) NOT NULL, actor_type VARCHAR(32) NOT NULL, actor_id INT DEFAULT NULL, entity_type VARCHAR(64) NOT NULL, entity_id INT DEFAULT NULL, source_app VARCHAR(32) NOT NULL, metadata_json JSON DEFAULT NULL, occurred_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE merchant_locations ADD CONSTRAINT FK_483F3F33727ACA70 FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE place_addresses ADD CONSTRAINT FK_5AA59D8764D218E FOREIGN KEY (location_id) REFERENCES merchant_locations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE owner_invitations ADD CONSTRAINT FK_A4CF76CF3B682539 FOREIGN KEY (owner_lead_id) REFERENCES owner_leads (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE demo_zones ADD CONSTRAINT FK_DA4E35498BAC62AF FOREIGN KEY (city_id) REFERENCES demo_cities (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE narrative_campaigns ADD CONSTRAINT FK_BDCA528E8BAC62AF FOREIGN KEY (city_id) REFERENCES demo_cities (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE narrative_campaigns ADD CONSTRAINT FK_BDCA528E9F2C3FAB FOREIGN KEY (zone_id) REFERENCES demo_zones (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE merchant_locations DROP FOREIGN KEY FK_483F3F33727ACA70');
        $this->addSql('ALTER TABLE place_addresses DROP FOREIGN KEY FK_5AA59D8764D218E');
        $this->addSql('ALTER TABLE owner_invitations DROP FOREIGN KEY FK_A4CF76CF3B682539');
        $this->addSql('ALTER TABLE demo_zones DROP FOREIGN KEY FK_DA4E35498BAC62AF');
        $this->addSql('ALTER TABLE narrative_campaigns DROP FOREIGN KEY FK_BDCA528E8BAC62AF');
        $this->addSql('ALTER TABLE narrative_campaigns DROP FOREIGN KEY FK_BDCA528E9F2C3FAB');
        $this->addSql('DROP TABLE event_logs');
        $this->addSql('DROP TABLE narrative_campaigns');
        $this->addSql('DROP TABLE demo_zones');
        $this->addSql('DROP TABLE demo_cities');
        $this->addSql('DROP TABLE public_invitations');
        $this->addSql('DROP TABLE owner_invitations');
        $this->addSql('DROP TABLE owner_leads');
        $this->addSql('DROP TABLE place_addresses');
        $this->addSql('DROP TABLE merchant_locations');
        $this->addSql('DROP TABLE merchants');
        $this->addSql('DROP TABLE admin_users');
    }
}
