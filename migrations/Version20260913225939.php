<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Volunteers' stays at branches (ADR 0026): adds `stay`, ties every activity
 * to one, and drops `volunteer.is_active`, which stays now answer.
 *
 * Existing rows are backfilled, one stay per volunteer who has activities:
 * first to last activity date, stretched to today for a volunteer who was
 * active, at the branch of their projects' location. Volunteers with no
 * activity get no stay and read as inactive.
 *
 * Order matters: `volunteer` is rebuilt while `stay` is still empty, so its
 * DROP can't cascade into stays, and the backfill reads `is_active` from the
 * temporary copy.
 */
final class Version20260913225939 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add stay, tie activities to stays, derive volunteer active from stays (ADR 0026)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__volunteer AS SELECT id, first_name, last_name, email, phone, notes, is_active, created_at, updated_at FROM volunteer');
        $this->addSql('DROP TABLE volunteer');
        $this->addSql('CREATE TABLE volunteer (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO volunteer (id, first_name, last_name, email, phone, notes, created_at, updated_at) SELECT id, first_name, last_name, email, phone, notes, created_at, updated_at FROM __temp__volunteer');

        $this->addSql('CREATE TABLE stay (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, volunteer_id INTEGER NOT NULL, branch_id INTEGER NOT NULL, CONSTRAINT FK_5E09839C8EFAB6B1 FOREIGN KEY (volunteer_id) REFERENCES volunteer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5E09839CDCD6CC49 FOREIGN KEY (branch_id) REFERENCES branch (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_5E09839C8EFAB6B1 ON stay (volunteer_id)');
        $this->addSql('CREATE INDEX IDX_5E09839CDCD6CC49 ON stay (branch_id)');

        // ponytail: one stay per volunteer, at one branch — a volunteer who
        // worked both locations lands on whichever sorts first (none does in
        // the data). A renamed seed branch drops the row, and the NOT NULL
        // stay_id below then fails the migration loudly rather than silently.
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $this->addSql(
            "INSERT INTO stay (volunteer_id, branch_id, start_date, end_date, created_at, updated_at)
             SELECT g.volunteer_id, b.id, g.start_date,
                    CASE WHEN g.is_active = 1 AND g.end_date < ? THEN ? ELSE g.end_date END,
                    CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
             FROM (
                 SELECT a.volunteer_id, MIN(p.location) AS location, MIN(a.date) AS start_date, MAX(a.date) AS end_date, v.is_active
                 FROM activity a
                 JOIN project p ON p.id = a.project_id
                 JOIN __temp__volunteer v ON v.id = a.volunteer_id
                 GROUP BY a.volunteer_id, v.is_active
             ) g
             JOIN branch b ON b.name = CASE g.location WHEN 'mombasa' THEN 'Mombasa' ELSE 'Nairobi (HQ)' END",
            [$today, $today],
        );
        $this->addSql('DROP TABLE __temp__volunteer');

        $this->addSql('CREATE TEMPORARY TABLE __temp__activity AS SELECT id, date, duration, duration_other, notes, created_at, updated_at, volunteer_id, project_id, activity_type_id, logged_by_id FROM activity');
        $this->addSql('DROP TABLE activity');
        $this->addSql('CREATE TABLE activity (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, date DATE NOT NULL, duration VARCHAR(20) NOT NULL, duration_other VARCHAR(100) DEFAULT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, volunteer_id INTEGER NOT NULL, project_id INTEGER NOT NULL, activity_type_id INTEGER NOT NULL, logged_by_id INTEGER NOT NULL, stay_id INTEGER NOT NULL, CONSTRAINT FK_AC74095A8EFAB6B1 FOREIGN KEY (volunteer_id) REFERENCES volunteer (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095A166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AC51EFA73 FOREIGN KEY (activity_type_id) REFERENCES activity_type (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AE877DD6E FOREIGN KEY (logged_by_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AFB3AF7D6 FOREIGN KEY (stay_id) REFERENCES stay (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO activity (id, date, duration, duration_other, notes, created_at, updated_at, volunteer_id, project_id, activity_type_id, logged_by_id, stay_id) SELECT t.id, t.date, t.duration, t.duration_other, t.notes, t.created_at, t.updated_at, t.volunteer_id, t.project_id, t.activity_type_id, t.logged_by_id, (SELECT s.id FROM stay s WHERE s.volunteer_id = t.volunteer_id) FROM __temp__activity t');
        $this->addSql('DROP TABLE __temp__activity');
        $this->addSql('CREATE INDEX IDX_AC74095A8EFAB6B1 ON activity (volunteer_id)');
        $this->addSql('CREATE INDEX IDX_AC74095A166D1F9C ON activity (project_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AC51EFA73 ON activity (activity_type_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AE877DD6E ON activity (logged_by_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AFB3AF7D6 ON activity (stay_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__activity AS SELECT id, date, duration, duration_other, notes, created_at, updated_at, volunteer_id, project_id, activity_type_id, logged_by_id FROM activity');
        $this->addSql('DROP TABLE activity');
        $this->addSql('CREATE TABLE activity (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, date DATE NOT NULL, duration VARCHAR(20) NOT NULL, duration_other VARCHAR(100) DEFAULT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, volunteer_id INTEGER NOT NULL, project_id INTEGER NOT NULL, activity_type_id INTEGER NOT NULL, logged_by_id INTEGER NOT NULL, CONSTRAINT FK_AC74095A8EFAB6B1 FOREIGN KEY (volunteer_id) REFERENCES volunteer (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095A166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AC51EFA73 FOREIGN KEY (activity_type_id) REFERENCES activity_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AE877DD6E FOREIGN KEY (logged_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO activity (id, date, duration, duration_other, notes, created_at, updated_at, volunteer_id, project_id, activity_type_id, logged_by_id) SELECT id, date, duration, duration_other, notes, created_at, updated_at, volunteer_id, project_id, activity_type_id, logged_by_id FROM __temp__activity');
        $this->addSql('DROP TABLE __temp__activity');
        $this->addSql('CREATE INDEX IDX_AC74095A8EFAB6B1 ON activity (volunteer_id)');
        $this->addSql('CREATE INDEX IDX_AC74095A166D1F9C ON activity (project_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AC51EFA73 ON activity (activity_type_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AE877DD6E ON activity (logged_by_id)');
        // Active again for anyone whose stay covers today; the rest default off.
        $this->addSql('ALTER TABLE volunteer ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT 0');
        $this->addSql("UPDATE volunteer SET is_active = 1 WHERE id IN (SELECT volunteer_id FROM stay WHERE start_date <= date('now') AND end_date >= date('now'))");
        $this->addSql('DROP TABLE stay');
    }
}
