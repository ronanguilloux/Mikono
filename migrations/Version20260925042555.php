<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Second slice of optional volunteer profile fields (ADR 0032) and the
 * encrypted passport number (ADR 0033). Every new column is nullable, so
 * existing volunteers need no backfill.
 */
final class Version20260925042555 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the second slice of volunteer profile fields and the encrypted passport number (ADR 0032, ADR 0033)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE volunteer ADD COLUMN accommodation_preference VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE volunteer ADD COLUMN pickup_airport VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE volunteer ADD COLUMN social_media_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE volunteer ADD COLUMN supervisor VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE volunteer ADD COLUMN passport_number_ciphertext CLOB DEFAULT NULL');
        $this->addSql('ALTER TABLE volunteer ADD COLUMN passport_expires_on DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__volunteer AS SELECT id, first_name, last_name, email, phone, notes, nationality, country_of_residence, date_of_birth, profession, skills, interests, emergency_contacts, created_at, updated_at, photo_id FROM volunteer');
        $this->addSql('DROP TABLE volunteer');
        $this->addSql('CREATE TABLE volunteer (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, notes CLOB DEFAULT NULL, nationality VARCHAR(2) DEFAULT NULL, country_of_residence VARCHAR(2) DEFAULT NULL, date_of_birth DATE DEFAULT NULL, profession VARCHAR(255) DEFAULT NULL, skills CLOB DEFAULT NULL, interests CLOB DEFAULT NULL, emergency_contacts CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, photo_id INTEGER DEFAULT NULL, CONSTRAINT FK_5140DEDB7E9E4C8C FOREIGN KEY (photo_id) REFERENCES volunteer_photo (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO volunteer (id, first_name, last_name, email, phone, notes, nationality, country_of_residence, date_of_birth, profession, skills, interests, emergency_contacts, created_at, updated_at, photo_id) SELECT id, first_name, last_name, email, phone, notes, nationality, country_of_residence, date_of_birth, profession, skills, interests, emergency_contacts, created_at, updated_at, photo_id FROM __temp__volunteer');
        $this->addSql('DROP TABLE __temp__volunteer');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5140DEDB7E9E4C8C ON volunteer (photo_id)');
    }
}
