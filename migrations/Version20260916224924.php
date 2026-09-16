<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Programs and the activity types each offers (ADR 0030).
 */
final class Version20260916224924 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create program and program_activity_type (ADR 0030)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE program (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, start_date DATE DEFAULT NULL, end_date DATE DEFAULT NULL, suggested_roles CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, project_id INTEGER NOT NULL, CONSTRAINT FK_92ED7784166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_92ED7784166D1F9C ON program (project_id)');
        $this->addSql('CREATE TABLE program_activity_type (program_id INTEGER NOT NULL, activity_type_id INTEGER NOT NULL, PRIMARY KEY (program_id, activity_type_id), CONSTRAINT FK_2A539C233EB8070A FOREIGN KEY (program_id) REFERENCES program (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2A539C23C51EFA73 FOREIGN KEY (activity_type_id) REFERENCES activity_type (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_2A539C233EB8070A ON program_activity_type (program_id)');
        $this->addSql('CREATE INDEX IDX_2A539C23C51EFA73 ON program_activity_type (activity_type_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE program_activity_type');
        $this->addSql('DROP TABLE program');
    }
}
