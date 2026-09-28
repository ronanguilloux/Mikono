<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260928202804 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add an optional gender to volunteers (ADR 0037)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE volunteer ADD COLUMN gender VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__volunteer AS SELECT id, first_name, last_name, email, phone, notes, nationality, country_of_residence, date_of_birth, profession, interests, emergency_contacts, accommodation_preference, pickup_airport, social_media_url, supervisor, passport_number_ciphertext, passport_expires_on, created_at, updated_at, photo_id FROM volunteer');
        $this->addSql('DROP TABLE volunteer');
        $this->addSql('CREATE TABLE volunteer (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, notes CLOB DEFAULT NULL, nationality VARCHAR(2) DEFAULT NULL, country_of_residence VARCHAR(2) DEFAULT NULL, date_of_birth DATE DEFAULT NULL, profession VARCHAR(255) DEFAULT NULL, interests CLOB DEFAULT NULL, emergency_contacts CLOB DEFAULT NULL, accommodation_preference VARCHAR(255) DEFAULT NULL, pickup_airport VARCHAR(255) DEFAULT NULL, social_media_url VARCHAR(255) DEFAULT NULL, supervisor VARCHAR(255) DEFAULT NULL, passport_number_ciphertext CLOB DEFAULT NULL, passport_expires_on DATE DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, photo_id INTEGER DEFAULT NULL, CONSTRAINT FK_5140DEDB7E9E4C8C FOREIGN KEY (photo_id) REFERENCES volunteer_photo (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO volunteer (id, first_name, last_name, email, phone, notes, nationality, country_of_residence, date_of_birth, profession, interests, emergency_contacts, accommodation_preference, pickup_airport, social_media_url, supervisor, passport_number_ciphertext, passport_expires_on, created_at, updated_at, photo_id) SELECT id, first_name, last_name, email, phone, notes, nationality, country_of_residence, date_of_birth, profession, interests, emergency_contacts, accommodation_preference, pickup_airport, social_media_url, supervisor, passport_number_ciphertext, passport_expires_on, created_at, updated_at, photo_id FROM __temp__volunteer');
        $this->addSql('DROP TABLE __temp__volunteer');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5140DEDB7E9E4C8C ON volunteer (photo_id)');
    }
}
