<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260928092413 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Program::beneficiariesReached, optional free text for the volunteer Impact panel (ADR 0030)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE program ADD COLUMN beneficiaries_reached CLOB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__program AS SELECT id, name, start_date, end_date, suggested_roles, created_at, updated_at, project_id FROM program');
        $this->addSql('DROP TABLE program');
        $this->addSql('CREATE TABLE program (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, start_date DATE DEFAULT NULL, end_date DATE DEFAULT NULL, suggested_roles CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, project_id INTEGER NOT NULL, CONSTRAINT FK_92ED7784166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO program (id, name, start_date, end_date, suggested_roles, created_at, updated_at, project_id) SELECT id, name, start_date, end_date, suggested_roles, created_at, updated_at, project_id FROM __temp__program');
        $this->addSql('DROP TABLE __temp__program');
        $this->addSql('CREATE INDEX IDX_92ED7784166D1F9C ON program (project_id)');
    }
}
