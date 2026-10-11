<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261011093531 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional branch.capacity and program.volunteers_needed (ADR 0049)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE branch ADD COLUMN capacity INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE program ADD COLUMN volunteers_needed INTEGER DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__branch AS SELECT id, name, physical_location, project_zones, program_focus, is_active, created_at, updated_at FROM branch');
        $this->addSql('DROP TABLE branch');
        $this->addSql('CREATE TABLE branch (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, physical_location VARCHAR(255) NOT NULL, project_zones CLOB DEFAULT NULL, program_focus CLOB DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO branch (id, name, physical_location, project_zones, program_focus, is_active, created_at, updated_at) SELECT id, name, physical_location, project_zones, program_focus, is_active, created_at, updated_at FROM __temp__branch');
        $this->addSql('DROP TABLE __temp__branch');
        $this->addSql('CREATE TEMPORARY TABLE __temp__program AS SELECT id, name, start_date, end_date, suggested_roles, beneficiaries_reached, created_at, updated_at, project_id, created_by_id, updated_by_id FROM program');
        $this->addSql('DROP TABLE program');
        $this->addSql('CREATE TABLE program (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, start_date DATE DEFAULT NULL, end_date DATE DEFAULT NULL, suggested_roles CLOB DEFAULT NULL, beneficiaries_reached CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, project_id INTEGER NOT NULL, created_by_id INTEGER DEFAULT NULL, updated_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_92ED7784166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_92ED7784B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_92ED7784896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO program (id, name, start_date, end_date, suggested_roles, beneficiaries_reached, created_at, updated_at, project_id, created_by_id, updated_by_id) SELECT id, name, start_date, end_date, suggested_roles, beneficiaries_reached, created_at, updated_at, project_id, created_by_id, updated_by_id FROM __temp__program');
        $this->addSql('DROP TABLE __temp__program');
        $this->addSql('CREATE INDEX IDX_92ED7784166D1F9C ON program (project_id)');
        $this->addSql('CREATE INDEX IDX_92ED7784B03A8386 ON program (created_by_id)');
        $this->addSql('CREATE INDEX IDX_92ED7784896DBBDE ON program (updated_by_id)');
    }
}
