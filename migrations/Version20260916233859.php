<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Optional volunteer profile fields and a volunteer_photo table (ADR 0032).
 * Every new column is nullable, so existing volunteers need no backfill. The
 * volunteer table rebuild is safe because DBAL leaves SQLite's foreign_keys
 * off, so dropping it cascades nothing to stay or activity.
 */
final class Version20260916233859 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional volunteer profile fields and volunteer photos (ADR 0032)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE volunteer_photo (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, bytes BLOB NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__volunteer AS SELECT id, first_name, last_name, email, phone, notes, created_at, updated_at FROM volunteer');
        $this->addSql('DROP TABLE volunteer');
        $this->addSql('CREATE TABLE volunteer (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, nationality VARCHAR(2) DEFAULT NULL, country_of_residence VARCHAR(2) DEFAULT NULL, date_of_birth DATE DEFAULT NULL, profession VARCHAR(255) DEFAULT NULL, skills CLOB DEFAULT NULL, interests CLOB DEFAULT NULL, emergency_contacts CLOB DEFAULT NULL, photo_id INTEGER DEFAULT NULL, CONSTRAINT FK_5140DEDB7E9E4C8C FOREIGN KEY (photo_id) REFERENCES volunteer_photo (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO volunteer (id, first_name, last_name, email, phone, notes, created_at, updated_at) SELECT id, first_name, last_name, email, phone, notes, created_at, updated_at FROM __temp__volunteer');
        $this->addSql('DROP TABLE __temp__volunteer');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5140DEDB7E9E4C8C ON volunteer (photo_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE volunteer_photo');
        $this->addSql('CREATE TEMPORARY TABLE __temp__volunteer AS SELECT id, first_name, last_name, email, phone, notes, created_at, updated_at FROM volunteer');
        $this->addSql('DROP TABLE volunteer');
        $this->addSql('CREATE TABLE volunteer (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO volunteer (id, first_name, last_name, email, phone, notes, created_at, updated_at) SELECT id, first_name, last_name, email, phone, notes, created_at, updated_at FROM __temp__volunteer');
        $this->addSql('DROP TABLE __temp__volunteer');
    }
}
