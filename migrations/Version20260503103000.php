<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds location categories catalog, links canonical locations to categories, and stores demo batch category slugs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE location_categories (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, slug VARCHAR(120) NOT NULL, icon_key VARCHAR(64) DEFAULT NULL, color_hex VARCHAR(7) DEFAULT NULL, sort_order INT NOT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_E86A604D989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE merchant_locations ADD primary_category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE merchant_locations ADD CONSTRAINT FK_8B91E12C775C3D57 FOREIGN KEY (primary_category_id) REFERENCES location_categories (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_8B91E12C775C3D57 ON merchant_locations (primary_category_id)');
        $this->addSql('ALTER TABLE demo_seed_batches ADD category_slugs JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE merchant_locations DROP FOREIGN KEY FK_8B91E12C775C3D57');
        $this->addSql('DROP TABLE location_categories');
        $this->addSql('DROP INDEX IDX_8B91E12C775C3D57 ON merchant_locations');
        $this->addSql('ALTER TABLE merchant_locations DROP primary_category_id');
        $this->addSql('ALTER TABLE demo_seed_batches DROP category_slugs');
    }
}
