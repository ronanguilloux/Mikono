<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Projects get a branch in place of their location (ADR 0027). The backfill
 * maps each location onto its branch the way the stay migration did:
 * `mombasa` to "Mombasa", anything else to "Nairobi (HQ)".
 */
final class Version20260913235254 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace project.location with a required branch (ADR 0027)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__project AS SELECT id, name, location, ownership, partner_organization_name, description, is_active, created_at, updated_at FROM project');
        $this->addSql('DROP TABLE project');
        $this->addSql('CREATE TABLE project (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, ownership VARCHAR(20) NOT NULL, partner_organization_name VARCHAR(255) DEFAULT NULL, description CLOB DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, branch_id INTEGER NOT NULL, CONSTRAINT FK_2FB3D0EEDCD6CC49 FOREIGN KEY (branch_id) REFERENCES branch (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        // A subquery, not a JOIN: a renamed seed branch yields NULL and the
        // NOT NULL column fails the migration, rather than dropping projects.
        $this->addSql(
            "INSERT INTO project (id, name, ownership, partner_organization_name, description, is_active, created_at, updated_at, branch_id)
             SELECT t.id, t.name, t.ownership, t.partner_organization_name, t.description, t.is_active, t.created_at, t.updated_at,
                    (SELECT b.id FROM branch b WHERE b.name = CASE t.location WHEN 'mombasa' THEN 'Mombasa' ELSE 'Nairobi (HQ)' END)
             FROM __temp__project t",
        );
        $this->addSql('DROP TABLE __temp__project');
        $this->addSql('CREATE INDEX IDX_2FB3D0EEDCD6CC49 ON project (branch_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__project AS SELECT id, name, branch_id, ownership, partner_organization_name, description, is_active, created_at, updated_at FROM project');
        $this->addSql('DROP TABLE project');
        $this->addSql('CREATE TABLE project (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, ownership VARCHAR(20) NOT NULL, partner_organization_name VARCHAR(255) DEFAULT NULL, description CLOB DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, location VARCHAR(20) NOT NULL)');
        $this->addSql(
            "INSERT INTO project (id, name, ownership, partner_organization_name, description, is_active, created_at, updated_at, location)
             SELECT t.id, t.name, t.ownership, t.partner_organization_name, t.description, t.is_active, t.created_at, t.updated_at,
                    CASE (SELECT b.name FROM branch b WHERE b.id = t.branch_id) WHEN 'Mombasa' THEN 'mombasa' ELSE 'kibera' END
             FROM __temp__project t",
        );
        $this->addSql('DROP TABLE __temp__project');
    }
}
