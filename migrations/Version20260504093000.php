<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260504093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds location claim requests and blocked email domains.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE location_claim_requests (id INT AUTO_INCREMENT NOT NULL, source_type VARCHAR(32) NOT NULL, canonical_location_id INT DEFAULT NULL, external_source_key VARCHAR(120) DEFAULT NULL, location_name VARCHAR(180) NOT NULL, short_address LONGTEXT DEFAULT NULL, claimant_name VARCHAR(160) NOT NULL, email VARCHAR(180) NOT NULL, whatsapp_e164 VARCHAR(32) DEFAULT NULL, message LONGTEXT DEFAULT NULL, status VARCHAR(32) NOT NULL, prefill_payload_json JSON DEFAULT NULL, reviewed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE blocked_email_domains (id INT AUTO_INCREMENT NOT NULL, domain VARCHAR(180) NOT NULL, provider_name VARCHAR(180) DEFAULT NULL, reason LONGTEXT DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_F9BA8F873A5E5B4D (domain), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE location_claim_requests');
        $this->addSql('DROP TABLE blocked_email_domains');
    }
}
