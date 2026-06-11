<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260610213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds canonical service profile, weekly opening hours and opening exceptions for locations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE location_service_profiles (id INT AUTO_INCREMENT NOT NULL, location_id INT NOT NULL, offers_delivery TINYINT(1) NOT NULL, offers_takeaway TINYINT(1) NOT NULL, offers_dine_in TINYINT(1) NOT NULL, delivery_notes LONGTEXT DEFAULT NULL, service_notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_LOCATION_SERVICE_PROFILE_LOCATION (location_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE location_opening_hours (id INT AUTO_INCREMENT NOT NULL, location_id INT NOT NULL, day_of_week SMALLINT NOT NULL, opens_at TIME DEFAULT NULL COMMENT \'(DC2Type:time_immutable)\', closes_at TIME DEFAULT NULL COMMENT \'(DC2Type:time_immutable)\', is_closed TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_LOCATION_OPENING_HOURS_LOCATION (location_id), UNIQUE INDEX uniq_location_opening_day (location_id, day_of_week), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE location_opening_exceptions (id INT AUTO_INCREMENT NOT NULL, location_id INT NOT NULL, exception_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', label VARCHAR(120) DEFAULT NULL, opens_at TIME DEFAULT NULL COMMENT \'(DC2Type:time_immutable)\', closes_at TIME DEFAULT NULL COMMENT \'(DC2Type:time_immutable)\', is_closed TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_LOCATION_OPENING_EXCEPTIONS_LOCATION (location_id), UNIQUE INDEX uniq_location_opening_exception_date (location_id, exception_date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE location_service_profiles ADD CONSTRAINT FK_LOCATION_SERVICE_PROFILE_LOCATION FOREIGN KEY (location_id) REFERENCES merchant_locations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE location_opening_hours ADD CONSTRAINT FK_LOCATION_OPENING_HOURS_LOCATION FOREIGN KEY (location_id) REFERENCES merchant_locations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE location_opening_exceptions ADD CONSTRAINT FK_LOCATION_OPENING_EXCEPTIONS_LOCATION FOREIGN KEY (location_id) REFERENCES merchant_locations (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE location_service_profiles DROP FOREIGN KEY FK_LOCATION_SERVICE_PROFILE_LOCATION');
        $this->addSql('ALTER TABLE location_opening_hours DROP FOREIGN KEY FK_LOCATION_OPENING_HOURS_LOCATION');
        $this->addSql('ALTER TABLE location_opening_exceptions DROP FOREIGN KEY FK_LOCATION_OPENING_EXCEPTIONS_LOCATION');
        $this->addSql('DROP TABLE location_service_profiles');
        $this->addSql('DROP TABLE location_opening_hours');
        $this->addSql('DROP TABLE location_opening_exceptions');
    }
}
