<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds Places category translation rules for Google type and name keyword classification.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE place_category_rules (id INT AUTO_INCREMENT NOT NULL, category_id INT NOT NULL, rule_type VARCHAR(32) NOT NULL, match_value VARCHAR(160) NOT NULL, priority INT NOT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_BBF04F8912469DE2 (category_id), INDEX IDX_PLACE_CATEGORY_RULES_ACTIVE_PRIORITY (is_active, priority), UNIQUE INDEX UNIQ_PLACE_CATEGORY_RULE (category_id, rule_type, match_value), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE place_category_rules ADD CONSTRAINT FK_BBF04F8912469DE2 FOREIGN KEY (category_id) REFERENCES location_categories (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE place_category_rules DROP FOREIGN KEY FK_BBF04F8912469DE2');
        $this->addSql('DROP TABLE place_category_rules');
    }
}
