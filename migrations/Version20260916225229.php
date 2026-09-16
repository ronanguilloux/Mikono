<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Moves every activity from its project onto a program (ADR 0030): one
 * always-on program per existing project, named after it and offering the
 * types its activities used, then activity.project_id gives way to
 * program_id. Programs added by hand before this runs are left alone.
 */
final class Version20260916225229 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Attach activities to programs instead of projects (ADR 0030)';
    }

    public function up(Schema $schema): void
    {
        $before = (int) $this->connection->fetchOne('SELECT COALESCE(MAX(id), 0) FROM program');

        $this->addSql("INSERT INTO program (name, project_id, created_at, updated_at) SELECT name, id, datetime('now'), datetime('now') FROM project ORDER BY id");
        $this->addSql('INSERT INTO program_activity_type (program_id, activity_type_id) SELECT DISTINCT prg.id, a.activity_type_id FROM activity a JOIN program prg ON prg.project_id = a.project_id AND prg.id > ' . $before);
        $this->addSql('CREATE TEMPORARY TABLE __temp__activity AS SELECT id, date, duration, duration_other, notes, created_at, updated_at, volunteer_id, project_id, activity_type_id, logged_by_id, stay_id FROM activity');
        $this->addSql('DROP TABLE activity');
        $this->addSql('CREATE TABLE activity (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, date DATE NOT NULL, duration VARCHAR(20) NOT NULL, duration_other VARCHAR(100) DEFAULT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, volunteer_id INTEGER NOT NULL, program_id INTEGER NOT NULL, activity_type_id INTEGER NOT NULL, logged_by_id INTEGER NOT NULL, stay_id INTEGER NOT NULL, CONSTRAINT FK_AC74095A8EFAB6B1 FOREIGN KEY (volunteer_id) REFERENCES volunteer (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AC51EFA73 FOREIGN KEY (activity_type_id) REFERENCES activity_type (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AE877DD6E FOREIGN KEY (logged_by_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AFB3AF7D6 FOREIGN KEY (stay_id) REFERENCES stay (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095A3EB8070A FOREIGN KEY (program_id) REFERENCES program (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO activity (id, date, duration, duration_other, notes, created_at, updated_at, volunteer_id, program_id, activity_type_id, logged_by_id, stay_id) SELECT t.id, t.date, t.duration, t.duration_other, t.notes, t.created_at, t.updated_at, t.volunteer_id, prg.id, t.activity_type_id, t.logged_by_id, t.stay_id FROM __temp__activity t JOIN program prg ON prg.project_id = t.project_id AND prg.id > ' . $before);
        $this->addSql('DROP TABLE __temp__activity');
        $this->addSql('CREATE INDEX IDX_AC74095AFB3AF7D6 ON activity (stay_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AE877DD6E ON activity (logged_by_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AC51EFA73 ON activity (activity_type_id)');
        $this->addSql('CREATE INDEX IDX_AC74095A8EFAB6B1 ON activity (volunteer_id)');
        $this->addSql('CREATE INDEX IDX_AC74095A3EB8070A ON activity (program_id)');
    }

    public function down(Schema $schema): void
    {
        // Back to the program's project. Programs themselves are kept: the
        // previous migration's down() drops them.
        $this->addSql('CREATE TEMPORARY TABLE __temp__activity AS SELECT id, date, duration, duration_other, notes, created_at, updated_at, volunteer_id, stay_id, program_id, activity_type_id, logged_by_id FROM activity');
        $this->addSql('DROP TABLE activity');
        $this->addSql('CREATE TABLE activity (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, date DATE NOT NULL, duration VARCHAR(20) NOT NULL, duration_other VARCHAR(100) DEFAULT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, volunteer_id INTEGER NOT NULL, stay_id INTEGER NOT NULL, project_id INTEGER NOT NULL, activity_type_id INTEGER NOT NULL, logged_by_id INTEGER NOT NULL, CONSTRAINT FK_AC74095A8EFAB6B1 FOREIGN KEY (volunteer_id) REFERENCES volunteer (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AFB3AF7D6 FOREIGN KEY (stay_id) REFERENCES stay (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AC51EFA73 FOREIGN KEY (activity_type_id) REFERENCES activity_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AE877DD6E FOREIGN KEY (logged_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095A166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO activity (id, date, duration, duration_other, notes, created_at, updated_at, volunteer_id, stay_id, project_id, activity_type_id, logged_by_id) SELECT t.id, t.date, t.duration, t.duration_other, t.notes, t.created_at, t.updated_at, t.volunteer_id, t.stay_id, prg.project_id, t.activity_type_id, t.logged_by_id FROM __temp__activity t JOIN program prg ON prg.id = t.program_id');
        $this->addSql('DROP TABLE __temp__activity');
        $this->addSql('CREATE INDEX IDX_AC74095A8EFAB6B1 ON activity (volunteer_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AFB3AF7D6 ON activity (stay_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AC51EFA73 ON activity (activity_type_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AE877DD6E ON activity (logged_by_id)');
        $this->addSql('CREATE INDEX IDX_AC74095A166D1F9C ON activity (project_id)');
    }
}
