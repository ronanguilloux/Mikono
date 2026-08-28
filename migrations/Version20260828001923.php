<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260828001923 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity ADD COLUMN duration_other VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__activity AS SELECT id, date, duration, notes, created_at, updated_at, volunteer_id, project_id, activity_type_id, logged_by_id FROM activity');
        $this->addSql('DROP TABLE activity');
        $this->addSql('CREATE TABLE activity (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, date DATE NOT NULL, duration VARCHAR(20) NOT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, volunteer_id INTEGER NOT NULL, project_id INTEGER NOT NULL, activity_type_id INTEGER NOT NULL, logged_by_id INTEGER NOT NULL, CONSTRAINT FK_AC74095A8EFAB6B1 FOREIGN KEY (volunteer_id) REFERENCES volunteer (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095A166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AC51EFA73 FOREIGN KEY (activity_type_id) REFERENCES activity_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AE877DD6E FOREIGN KEY (logged_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO activity (id, date, duration, notes, created_at, updated_at, volunteer_id, project_id, activity_type_id, logged_by_id) SELECT id, date, duration, notes, created_at, updated_at, volunteer_id, project_id, activity_type_id, logged_by_id FROM __temp__activity');
        $this->addSql('DROP TABLE __temp__activity');
        $this->addSql('CREATE INDEX IDX_AC74095A8EFAB6B1 ON activity (volunteer_id)');
        $this->addSql('CREATE INDEX IDX_AC74095A166D1F9C ON activity (project_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AC51EFA73 ON activity (activity_type_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AE877DD6E ON activity (logged_by_id)');
    }
}
