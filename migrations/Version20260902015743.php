<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260902015743 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'An activity can have several escorts (ADR 0013); a volunteer\'s last name is optional (ADR 0014).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE activity_escort (activity_id INTEGER NOT NULL, escort_id INTEGER NOT NULL, PRIMARY KEY (activity_id, escort_id), CONSTRAINT FK_25863FF481C06096 FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_25863FF46F913587 FOREIGN KEY (escort_id) REFERENCES escort (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_25863FF481C06096 ON activity_escort (activity_id)');
        $this->addSql('CREATE INDEX IDX_25863FF46F913587 ON activity_escort (escort_id)');

        // Carry every already-logged escort across before the column that
        // holds it is dropped by the table rebuild below.
        $this->addSql('INSERT INTO activity_escort (activity_id, escort_id) SELECT id, accompanied_by_id FROM activity WHERE accompanied_by_id IS NOT NULL');

        $this->addSql('CREATE TEMPORARY TABLE __temp__activity AS SELECT id, date, duration, duration_other, notes, created_at, updated_at, volunteer_id, project_id, activity_type_id, logged_by_id FROM activity');
        $this->addSql('DROP TABLE activity');
        $this->addSql('CREATE TABLE activity (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, date DATE NOT NULL, duration VARCHAR(20) NOT NULL, duration_other VARCHAR(100) DEFAULT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, volunteer_id INTEGER NOT NULL, project_id INTEGER NOT NULL, activity_type_id INTEGER NOT NULL, logged_by_id INTEGER NOT NULL, CONSTRAINT FK_AC74095A8EFAB6B1 FOREIGN KEY (volunteer_id) REFERENCES volunteer (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095A166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AC51EFA73 FOREIGN KEY (activity_type_id) REFERENCES activity_type (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AE877DD6E FOREIGN KEY (logged_by_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO activity (id, date, duration, duration_other, notes, created_at, updated_at, volunteer_id, project_id, activity_type_id, logged_by_id) SELECT id, date, duration, duration_other, notes, created_at, updated_at, volunteer_id, project_id, activity_type_id, logged_by_id FROM __temp__activity');
        $this->addSql('DROP TABLE __temp__activity');
        $this->addSql('CREATE INDEX IDX_AC74095AE877DD6E ON activity (logged_by_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AC51EFA73 ON activity (activity_type_id)');
        $this->addSql('CREATE INDEX IDX_AC74095A166D1F9C ON activity (project_id)');
        $this->addSql('CREATE INDEX IDX_AC74095A8EFAB6B1 ON activity (volunteer_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__volunteer AS SELECT id, first_name, last_name, email, phone, notes, is_active, created_at, updated_at FROM volunteer');
        $this->addSql('DROP TABLE volunteer');
        $this->addSql('CREATE TABLE volunteer (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, notes CLOB DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO volunteer (id, first_name, last_name, email, phone, notes, is_active, created_at, updated_at) SELECT id, first_name, last_name, email, phone, notes, is_active, created_at, updated_at FROM __temp__volunteer');
        $this->addSql('DROP TABLE __temp__volunteer');

        // One representation of "no surname recorded", not two.
        $this->addSql("UPDATE volunteer SET last_name = NULL WHERE last_name = ''");
    }

    public function down(Schema $schema): void
    {
        // Lossy on purpose: an activity with two escorts can only keep one
        // once the column is back. The lowest escort id wins.
        $this->addSql('CREATE TEMPORARY TABLE __temp__activity_escort AS SELECT activity_id, MIN(escort_id) AS escort_id FROM activity_escort GROUP BY activity_id');
        $this->addSql('DROP TABLE activity_escort');
        $this->addSql('CREATE TEMPORARY TABLE __temp__activity AS SELECT id, date, duration, duration_other, notes, created_at, updated_at, volunteer_id, project_id, activity_type_id, logged_by_id FROM activity');
        $this->addSql('DROP TABLE activity');
        $this->addSql('CREATE TABLE activity (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, date DATE NOT NULL, duration VARCHAR(20) NOT NULL, duration_other VARCHAR(100) DEFAULT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, volunteer_id INTEGER NOT NULL, project_id INTEGER NOT NULL, activity_type_id INTEGER NOT NULL, logged_by_id INTEGER NOT NULL, accompanied_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_AC74095A8EFAB6B1 FOREIGN KEY (volunteer_id) REFERENCES volunteer (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095A166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AC51EFA73 FOREIGN KEY (activity_type_id) REFERENCES activity_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AE877DD6E FOREIGN KEY (logged_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095A11E19AC2 FOREIGN KEY (accompanied_by_id) REFERENCES escort (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO activity (id, date, duration, duration_other, notes, created_at, updated_at, volunteer_id, project_id, activity_type_id, logged_by_id) SELECT id, date, duration, duration_other, notes, created_at, updated_at, volunteer_id, project_id, activity_type_id, logged_by_id FROM __temp__activity');
        $this->addSql('DROP TABLE __temp__activity');
        $this->addSql('CREATE INDEX IDX_AC74095A8EFAB6B1 ON activity (volunteer_id)');
        $this->addSql('CREATE INDEX IDX_AC74095A166D1F9C ON activity (project_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AC51EFA73 ON activity (activity_type_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AE877DD6E ON activity (logged_by_id)');
        $this->addSql('CREATE INDEX IDX_AC74095A11E19AC2 ON activity (accompanied_by_id)');
        $this->addSql('UPDATE activity SET accompanied_by_id = (SELECT escort_id FROM __temp__activity_escort WHERE activity_id = activity.id)');
        $this->addSql('DROP TABLE __temp__activity_escort');

        $this->addSql("UPDATE volunteer SET last_name = '' WHERE last_name IS NULL");
        $this->addSql('CREATE TEMPORARY TABLE __temp__volunteer AS SELECT id, first_name, last_name, email, phone, notes, is_active, created_at, updated_at FROM volunteer');
        $this->addSql('DROP TABLE volunteer');
        $this->addSql('CREATE TABLE volunteer (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, notes CLOB DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO volunteer (id, first_name, last_name, email, phone, notes, is_active, created_at, updated_at) SELECT id, first_name, last_name, email, phone, notes, is_active, created_at, updated_at FROM __temp__volunteer');
        $this->addSql('DROP TABLE __temp__volunteer');
    }
}
