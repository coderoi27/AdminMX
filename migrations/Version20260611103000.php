<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds canonical media items and social links for locations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE location_media_items (id INT AUTO_INCREMENT NOT NULL, location_id INT NOT NULL, media_type VARCHAR(32) NOT NULL, url VARCHAR(1024) NOT NULL, title VARCHAR(160) DEFAULT NULL, alt_text VARCHAR(180) DEFAULT NULL, sort_order INT NOT NULL, is_primary TINYINT(1) NOT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_LOCATION_MEDIA_ITEMS_LOCATION (location_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE location_social_links (id INT AUTO_INCREMENT NOT NULL, location_id INT NOT NULL, platform VARCHAR(32) NOT NULL, url VARCHAR(1024) NOT NULL, label VARCHAR(120) DEFAULT NULL, sort_order INT NOT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_LOCATION_SOCIAL_LINKS_LOCATION (location_id), UNIQUE INDEX uniq_location_social_platform (location_id, platform), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE location_media_items ADD CONSTRAINT FK_LOCATION_MEDIA_ITEMS_LOCATION FOREIGN KEY (location_id) REFERENCES merchant_locations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE location_social_links ADD CONSTRAINT FK_LOCATION_SOCIAL_LINKS_LOCATION FOREIGN KEY (location_id) REFERENCES merchant_locations (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE location_media_items DROP FOREIGN KEY FK_LOCATION_MEDIA_ITEMS_LOCATION');
        $this->addSql('ALTER TABLE location_social_links DROP FOREIGN KEY FK_LOCATION_SOCIAL_LINKS_LOCATION');
        $this->addSql('DROP TABLE location_media_items');
        $this->addSql('DROP TABLE location_social_links');
    }
}
