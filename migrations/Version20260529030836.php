<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260529030836 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE google_place_blacklist (id INT AUTO_INCREMENT NOT NULL, external_source_key VARCHAR(255) NOT NULL, reason LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_70A13471C19A213A (external_source_key), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE admin_users CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE admin_users RENAME INDEX uniq_f18aad9ee7927c74 TO UNIQ_B4A95E13E7927C74');
        $this->addSql('ALTER TABLE blocked_email_domains CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE blocked_email_domains RENAME INDEX uniq_f9ba8f873a5e5b4d TO UNIQ_1DE0A557A7A91E0B');
        $this->addSql('ALTER TABLE demo_cities CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE demo_cities RENAME INDEX uniq_70d5ef9d989d9b62 TO UNIQ_A5A1C610989D9B62');
        $this->addSql('ALTER TABLE demo_seed_batch_items CHANGE generated_at generated_at DATETIME NOT NULL, CHANGE expired_at expired_at DATETIME DEFAULT NULL, CHANGE purged_at purged_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE demo_seed_batch_items RENAME INDEX idx_31cb7b7914738d70 TO IDX_5D1B9BC82E0627C4');
        $this->addSql('ALTER TABLE demo_seed_batch_items RENAME INDEX idx_31cb7b7955964fd TO IDX_5D1B9BC880FC9C9D');
        $this->addSql('ALTER TABLE demo_seed_batches CHANGE config_json config_json JSON DEFAULT NULL, CHANGE seeded_at seeded_at DATETIME DEFAULT NULL, CHANGE expires_at expires_at DATETIME DEFAULT NULL, CHANGE disabled_at disabled_at DATETIME DEFAULT NULL, CHANGE purged_at purged_at DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE category_slugs category_slugs JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE demo_seed_batches RENAME INDEX idx_b2e81c595845665a TO IDX_658B123CEC942BCF');
        $this->addSql('ALTER TABLE demo_seed_batches RENAME INDEX idx_b2e81c598bac62af TO IDX_658B123C4C07366C');
        $this->addSql('ALTER TABLE demo_seed_batches RENAME INDEX idx_b2e81c599f2c3fab TO IDX_658B123C58876B68');
        $this->addSql('ALTER TABLE demo_seed_batches RENAME INDEX idx_b2e81c594b4bcce3 TO IDX_658B123C64F1F4EE');
        $this->addSql('ALTER TABLE demo_zones CHANGE polygon_json polygon_json JSON DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE demo_zones RENAME INDEX idx_da4e35498bac62af TO IDX_A5527DA68BAC62AF');
        $this->addSql('ALTER TABLE event_logs CHANGE metadata_json metadata_json JSON DEFAULT NULL, CHANGE occurred_at occurred_at DATETIME NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE legal_documents CHANGE effective_at effective_at DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE legal_documents RENAME INDEX uniq_46d83f5989d9b62 TO UNIQ_4E794DD9989D9B62');
        $this->addSql('ALTER TABLE location_categories CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE google_place_type_mappings google_place_type_mappings JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE location_categories RENAME INDEX uniq_e86a604d989d9b62 TO UNIQ_83DB47B7989D9B62');
        $this->addSql('ALTER TABLE location_claim_requests CHANGE prefill_payload_json prefill_payload_json JSON DEFAULT NULL, CHANGE reviewed_at reviewed_at DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE merchant_locations CHANGE source_type source_type VARCHAR(32) NOT NULL, CHANGE claimed_at claimed_at DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE gem_status gem_status VARCHAR(24) NOT NULL, CHANGE gem_reason_tags gem_reason_tags JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE merchant_locations RENAME INDEX uniq_483f3f33989d9b62 TO UNIQ_A53C45E6989D9B62');
        $this->addSql('ALTER TABLE merchant_locations RENAME INDEX idx_483f3f33727aca70 TO IDX_A53C45E66796D554');
        $this->addSql('ALTER TABLE merchant_locations RENAME INDEX idx_8b91e12c775c3d57 TO IDX_A53C45E6B6A9FD63');
        $this->addSql('ALTER TABLE merchants CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE merchants RENAME INDEX uniq_6a6b2dc3989d9b62 TO UNIQ_CC77B6C0989D9B62');
        $this->addSql('ALTER TABLE metric_rollups_daily CHANGE rollup_date rollup_date DATE NOT NULL, CHANGE computed_at computed_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE narrative_campaigns CHANGE starts_at starts_at DATETIME DEFAULT NULL, CHANGE ends_at ends_at DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE narrative_campaigns RENAME INDEX uniq_bdca528e989d9b62 TO UNIQ_E3DF222C989D9B62');
        $this->addSql('ALTER TABLE narrative_campaigns RENAME INDEX idx_bdca528e8bac62af TO IDX_E3DF222C8BAC62AF');
        $this->addSql('ALTER TABLE narrative_campaigns RENAME INDEX idx_bdca528e9f2c3fab TO IDX_E3DF222C9F2C3FAB');
        $this->addSql('ALTER TABLE owner_invitations CHANGE expires_at expires_at DATETIME NOT NULL, CHANGE sent_at sent_at DATETIME DEFAULT NULL, CHANGE opened_at opened_at DATETIME DEFAULT NULL, CHANGE used_at used_at DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE owner_invitations RENAME INDEX idx_a4cf76cf3b682539 TO IDX_25C3CF288C23C352');
        $this->addSql('ALTER TABLE owner_leads CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE place_addresses CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE place_addresses RENAME INDEX idx_5aa59d8764d218e TO IDX_1B51FC6B64D218E');
        $this->addSql('ALTER TABLE public_invitations CHANGE expires_at expires_at DATETIME NOT NULL, CHANGE sent_at sent_at DATETIME DEFAULT NULL, CHANGE opened_at opened_at DATETIME DEFAULT NULL, CHANGE used_at used_at DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE system_plugins CHANGE config_json config_json JSON DEFAULT NULL, CHANGE last_run_at last_run_at DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE system_plugins RENAME INDEX uniq_91fe8a7f4c54c8c5 TO UNIQ_F68AE2EA738830');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE google_place_blacklist');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE admin_users CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE admin_users RENAME INDEX uniq_b4a95e13e7927c74 TO UNIQ_F18AAD9EE7927C74');
        $this->addSql('ALTER TABLE blocked_email_domains CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE blocked_email_domains RENAME INDEX uniq_1de0a557a7a91e0b TO UNIQ_F9BA8F873A5E5B4D');
        $this->addSql('ALTER TABLE demo_cities CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE demo_cities RENAME INDEX uniq_a5a1c610989d9b62 TO UNIQ_70D5EF9D989D9B62');
        $this->addSql('ALTER TABLE demo_seed_batch_items CHANGE generated_at generated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE expired_at expired_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE purged_at purged_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE demo_seed_batch_items RENAME INDEX idx_5d1b9bc82e0627c4 TO IDX_31CB7B7914738D70');
        $this->addSql('ALTER TABLE demo_seed_batch_items RENAME INDEX idx_5d1b9bc880fc9c9d TO IDX_31CB7B7955964FD');
        $this->addSql('ALTER TABLE demo_seed_batches CHANGE config_json config_json LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE category_slugs category_slugs LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE seeded_at seeded_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE expires_at expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE disabled_at disabled_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE purged_at purged_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE demo_seed_batches RENAME INDEX idx_658b123c64f1f4ee TO IDX_B2E81C594B4BCCE3');
        $this->addSql('ALTER TABLE demo_seed_batches RENAME INDEX idx_658b123cec942bcf TO IDX_B2E81C595845665A');
        $this->addSql('ALTER TABLE demo_seed_batches RENAME INDEX idx_658b123c4c07366c TO IDX_B2E81C598BAC62AF');
        $this->addSql('ALTER TABLE demo_seed_batches RENAME INDEX idx_658b123c58876b68 TO IDX_B2E81C599F2C3FAB');
        $this->addSql('ALTER TABLE demo_zones CHANGE polygon_json polygon_json LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE demo_zones RENAME INDEX idx_a5527da68bac62af TO IDX_DA4E35498BAC62AF');
        $this->addSql('ALTER TABLE event_logs CHANGE metadata_json metadata_json LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE occurred_at occurred_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE legal_documents CHANGE effective_at effective_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE legal_documents RENAME INDEX uniq_4e794dd9989d9b62 TO UNIQ_46D83F5989D9B62');
        $this->addSql('ALTER TABLE location_categories CHANGE google_place_type_mappings google_place_type_mappings LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE location_categories RENAME INDEX uniq_83db47b7989d9b62 TO UNIQ_E86A604D989D9B62');
        $this->addSql('ALTER TABLE location_claim_requests CHANGE prefill_payload_json prefill_payload_json LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE reviewed_at reviewed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE merchant_locations CHANGE source_type source_type VARCHAR(32) DEFAULT \'owner_registered\' NOT NULL, CHANGE gem_status gem_status VARCHAR(24) DEFAULT \'none\' NOT NULL, CHANGE gem_reason_tags gem_reason_tags LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE claimed_at claimed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE merchant_locations RENAME INDEX idx_a53c45e66796d554 TO IDX_483F3F33727ACA70');
        $this->addSql('ALTER TABLE merchant_locations RENAME INDEX idx_a53c45e6b6a9fd63 TO IDX_8B91E12C775C3D57');
        $this->addSql('ALTER TABLE merchant_locations RENAME INDEX uniq_a53c45e6989d9b62 TO UNIQ_483F3F33989D9B62');
        $this->addSql('ALTER TABLE merchants CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE merchants RENAME INDEX uniq_cc77b6c0989d9b62 TO UNIQ_6A6B2DC3989D9B62');
        $this->addSql('ALTER TABLE metric_rollups_daily CHANGE rollup_date rollup_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', CHANGE computed_at computed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE narrative_campaigns CHANGE starts_at starts_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE ends_at ends_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE narrative_campaigns RENAME INDEX idx_e3df222c8bac62af TO IDX_BDCA528E8BAC62AF');
        $this->addSql('ALTER TABLE narrative_campaigns RENAME INDEX idx_e3df222c9f2c3fab TO IDX_BDCA528E9F2C3FAB');
        $this->addSql('ALTER TABLE narrative_campaigns RENAME INDEX uniq_e3df222c989d9b62 TO UNIQ_BDCA528E989D9B62');
        $this->addSql('ALTER TABLE owner_invitations CHANGE expires_at expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE sent_at sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE opened_at opened_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE used_at used_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE owner_invitations RENAME INDEX idx_25c3cf288c23c352 TO IDX_A4CF76CF3B682539');
        $this->addSql('ALTER TABLE owner_leads CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE place_addresses CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE place_addresses RENAME INDEX idx_1b51fc6b64d218e TO IDX_5AA59D8764D218E');
        $this->addSql('ALTER TABLE public_invitations CHANGE expires_at expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE sent_at sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE opened_at opened_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE used_at used_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE system_plugins CHANGE config_json config_json LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE last_run_at last_run_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE system_plugins RENAME INDEX uniq_f68ae2ea738830 TO UNIQ_91FE8A7F4C54C8C5');
    }
}
