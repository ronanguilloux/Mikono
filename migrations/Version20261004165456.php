<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261004165456 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record who added and last edited volunteers, stays, achievements, activities, projects and programs (ADR 0043)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__achievement AS SELECT id, title, description, achieved_on, created_at, updated_at, stay_id, project_id FROM achievement');
        $this->addSql('DROP TABLE achievement');
        $this->addSql('CREATE TABLE achievement (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, achieved_on DATE NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, stay_id INTEGER NOT NULL, project_id INTEGER NOT NULL, created_by_id INTEGER DEFAULT NULL, updated_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_96737FF1FB3AF7D6 FOREIGN KEY (stay_id) REFERENCES stay (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_96737FF1166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_96737FF1B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_96737FF1896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO achievement (id, title, description, achieved_on, created_at, updated_at, stay_id, project_id) SELECT id, title, description, achieved_on, created_at, updated_at, stay_id, project_id FROM __temp__achievement');
        $this->addSql('DROP TABLE __temp__achievement');
        $this->addSql('CREATE INDEX IDX_96737FF1166D1F9C ON achievement (project_id)');
        $this->addSql('CREATE INDEX IDX_96737FF1FB3AF7D6 ON achievement (stay_id)');
        $this->addSql('CREATE INDEX IDX_96737FF1B03A8386 ON achievement (created_by_id)');
        $this->addSql('CREATE INDEX IDX_96737FF1896DBBDE ON achievement (updated_by_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__activity AS SELECT id, date, duration, duration_other, notes, created_at, updated_at, volunteer_id, program_id, activity_type_id, logged_by_id, stay_id FROM activity');
        $this->addSql('DROP TABLE activity');
        $this->addSql('CREATE TABLE activity (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, date DATE NOT NULL, duration VARCHAR(20) NOT NULL, duration_other VARCHAR(100) DEFAULT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, volunteer_id INTEGER NOT NULL, program_id INTEGER NOT NULL, activity_type_id INTEGER NOT NULL, logged_by_id INTEGER NOT NULL, stay_id INTEGER NOT NULL, updated_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_AC74095A8EFAB6B1 FOREIGN KEY (volunteer_id) REFERENCES volunteer (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AC51EFA73 FOREIGN KEY (activity_type_id) REFERENCES activity_type (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AE877DD6E FOREIGN KEY (logged_by_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AFB3AF7D6 FOREIGN KEY (stay_id) REFERENCES stay (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095A3EB8070A FOREIGN KEY (program_id) REFERENCES program (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095A896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO activity (id, date, duration, duration_other, notes, created_at, updated_at, volunteer_id, program_id, activity_type_id, logged_by_id, stay_id) SELECT id, date, duration, duration_other, notes, created_at, updated_at, volunteer_id, program_id, activity_type_id, logged_by_id, stay_id FROM __temp__activity');
        $this->addSql('DROP TABLE __temp__activity');
        $this->addSql('CREATE INDEX IDX_AC74095A3EB8070A ON activity (program_id)');
        $this->addSql('CREATE INDEX IDX_AC74095A8EFAB6B1 ON activity (volunteer_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AC51EFA73 ON activity (activity_type_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AE877DD6E ON activity (logged_by_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AFB3AF7D6 ON activity (stay_id)');
        $this->addSql('CREATE INDEX IDX_AC74095A896DBBDE ON activity (updated_by_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__program AS SELECT id, name, start_date, end_date, suggested_roles, created_at, updated_at, project_id, beneficiaries_reached FROM program');
        $this->addSql('DROP TABLE program');
        $this->addSql('CREATE TABLE program (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, start_date DATE DEFAULT NULL, end_date DATE DEFAULT NULL, suggested_roles CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, project_id INTEGER NOT NULL, beneficiaries_reached CLOB DEFAULT NULL, created_by_id INTEGER DEFAULT NULL, updated_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_92ED7784166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_92ED7784B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_92ED7784896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO program (id, name, start_date, end_date, suggested_roles, created_at, updated_at, project_id, beneficiaries_reached) SELECT id, name, start_date, end_date, suggested_roles, created_at, updated_at, project_id, beneficiaries_reached FROM __temp__program');
        $this->addSql('DROP TABLE __temp__program');
        $this->addSql('CREATE INDEX IDX_92ED7784166D1F9C ON program (project_id)');
        $this->addSql('CREATE INDEX IDX_92ED7784B03A8386 ON program (created_by_id)');
        $this->addSql('CREATE INDEX IDX_92ED7784896DBBDE ON program (updated_by_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__project AS SELECT id, name, ownership, partner_organization_name, description, is_active, created_at, updated_at, branch_id FROM project');
        $this->addSql('DROP TABLE project');
        $this->addSql('CREATE TABLE project (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, ownership VARCHAR(20) NOT NULL, partner_organization_name VARCHAR(255) DEFAULT NULL, description CLOB DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, branch_id INTEGER NOT NULL, created_by_id INTEGER DEFAULT NULL, updated_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_2FB3D0EEDCD6CC49 FOREIGN KEY (branch_id) REFERENCES branch (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2FB3D0EEB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2FB3D0EE896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO project (id, name, ownership, partner_organization_name, description, is_active, created_at, updated_at, branch_id) SELECT id, name, ownership, partner_organization_name, description, is_active, created_at, updated_at, branch_id FROM __temp__project');
        $this->addSql('DROP TABLE __temp__project');
        $this->addSql('CREATE INDEX IDX_2FB3D0EEDCD6CC49 ON project (branch_id)');
        $this->addSql('CREATE INDEX IDX_2FB3D0EEB03A8386 ON project (created_by_id)');
        $this->addSql('CREATE INDEX IDX_2FB3D0EE896DBBDE ON project (updated_by_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__stay AS SELECT id, start_date, end_date, created_at, updated_at, volunteer_id, branch_id FROM stay');
        $this->addSql('DROP TABLE stay');
        $this->addSql('CREATE TABLE stay (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, volunteer_id INTEGER NOT NULL, branch_id INTEGER NOT NULL, created_by_id INTEGER DEFAULT NULL, updated_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_5E09839C8EFAB6B1 FOREIGN KEY (volunteer_id) REFERENCES volunteer (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5E09839CDCD6CC49 FOREIGN KEY (branch_id) REFERENCES branch (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5E09839CB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5E09839C896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO stay (id, start_date, end_date, created_at, updated_at, volunteer_id, branch_id) SELECT id, start_date, end_date, created_at, updated_at, volunteer_id, branch_id FROM __temp__stay');
        $this->addSql('DROP TABLE __temp__stay');
        $this->addSql('CREATE INDEX IDX_5E09839CDCD6CC49 ON stay (branch_id)');
        $this->addSql('CREATE INDEX IDX_5E09839C8EFAB6B1 ON stay (volunteer_id)');
        $this->addSql('CREATE INDEX IDX_5E09839CB03A8386 ON stay (created_by_id)');
        $this->addSql('CREATE INDEX IDX_5E09839C896DBBDE ON stay (updated_by_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__volunteer AS SELECT id, first_name, last_name, email, phone, notes, created_at, updated_at, nationality, country_of_residence, date_of_birth, profession, interests, emergency_contacts, photo_id, accommodation_preference, pickup_airport, social_media_url, supervisor, passport_number_ciphertext, passport_expires_on, gender FROM volunteer');
        $this->addSql('DROP TABLE volunteer');
        $this->addSql('CREATE TABLE volunteer (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, nationality VARCHAR(2) DEFAULT NULL, country_of_residence VARCHAR(2) DEFAULT NULL, date_of_birth DATE DEFAULT NULL, profession VARCHAR(255) DEFAULT NULL, interests CLOB DEFAULT NULL, emergency_contacts CLOB DEFAULT NULL, photo_id INTEGER DEFAULT NULL, accommodation_preference VARCHAR(255) DEFAULT NULL, pickup_airport VARCHAR(255) DEFAULT NULL, social_media_url VARCHAR(255) DEFAULT NULL, supervisor VARCHAR(255) DEFAULT NULL, passport_number_ciphertext CLOB DEFAULT NULL, passport_expires_on DATE DEFAULT NULL, gender VARCHAR(20) DEFAULT NULL, created_by_id INTEGER DEFAULT NULL, updated_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_5140DEDB7E9E4C8C FOREIGN KEY (photo_id) REFERENCES volunteer_photo (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5140DEDBB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5140DEDB896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO volunteer (id, first_name, last_name, email, phone, notes, created_at, updated_at, nationality, country_of_residence, date_of_birth, profession, interests, emergency_contacts, photo_id, accommodation_preference, pickup_airport, social_media_url, supervisor, passport_number_ciphertext, passport_expires_on, gender) SELECT id, first_name, last_name, email, phone, notes, created_at, updated_at, nationality, country_of_residence, date_of_birth, profession, interests, emergency_contacts, photo_id, accommodation_preference, pickup_airport, social_media_url, supervisor, passport_number_ciphertext, passport_expires_on, gender FROM __temp__volunteer');
        $this->addSql('DROP TABLE __temp__volunteer');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5140DEDB7E9E4C8C ON volunteer (photo_id)');
        $this->addSql('CREATE INDEX IDX_5140DEDBB03A8386 ON volunteer (created_by_id)');
        $this->addSql('CREATE INDEX IDX_5140DEDB896DBBDE ON volunteer (updated_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__achievement AS SELECT id, title, description, achieved_on, created_at, updated_at, stay_id, project_id FROM achievement');
        $this->addSql('DROP TABLE achievement');
        $this->addSql('CREATE TABLE achievement (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, achieved_on DATE NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, stay_id INTEGER NOT NULL, project_id INTEGER NOT NULL, CONSTRAINT FK_96737FF1FB3AF7D6 FOREIGN KEY (stay_id) REFERENCES stay (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_96737FF1166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO achievement (id, title, description, achieved_on, created_at, updated_at, stay_id, project_id) SELECT id, title, description, achieved_on, created_at, updated_at, stay_id, project_id FROM __temp__achievement');
        $this->addSql('DROP TABLE __temp__achievement');
        $this->addSql('CREATE INDEX IDX_96737FF1FB3AF7D6 ON achievement (stay_id)');
        $this->addSql('CREATE INDEX IDX_96737FF1166D1F9C ON achievement (project_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__activity AS SELECT id, date, duration, duration_other, notes, created_at, updated_at, volunteer_id, stay_id, program_id, activity_type_id, logged_by_id FROM activity');
        $this->addSql('DROP TABLE activity');
        $this->addSql('CREATE TABLE activity (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, date DATE NOT NULL, duration VARCHAR(20) NOT NULL, duration_other VARCHAR(100) DEFAULT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, volunteer_id INTEGER NOT NULL, stay_id INTEGER NOT NULL, program_id INTEGER NOT NULL, activity_type_id INTEGER NOT NULL, logged_by_id INTEGER NOT NULL, CONSTRAINT FK_AC74095A8EFAB6B1 FOREIGN KEY (volunteer_id) REFERENCES volunteer (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AFB3AF7D6 FOREIGN KEY (stay_id) REFERENCES stay (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095A3EB8070A FOREIGN KEY (program_id) REFERENCES program (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AC51EFA73 FOREIGN KEY (activity_type_id) REFERENCES activity_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC74095AE877DD6E FOREIGN KEY (logged_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO activity (id, date, duration, duration_other, notes, created_at, updated_at, volunteer_id, stay_id, program_id, activity_type_id, logged_by_id) SELECT id, date, duration, duration_other, notes, created_at, updated_at, volunteer_id, stay_id, program_id, activity_type_id, logged_by_id FROM __temp__activity');
        $this->addSql('DROP TABLE __temp__activity');
        $this->addSql('CREATE INDEX IDX_AC74095A8EFAB6B1 ON activity (volunteer_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AFB3AF7D6 ON activity (stay_id)');
        $this->addSql('CREATE INDEX IDX_AC74095A3EB8070A ON activity (program_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AC51EFA73 ON activity (activity_type_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AE877DD6E ON activity (logged_by_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__program AS SELECT id, name, start_date, end_date, suggested_roles, beneficiaries_reached, created_at, updated_at, project_id FROM program');
        $this->addSql('DROP TABLE program');
        $this->addSql('CREATE TABLE program (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, start_date DATE DEFAULT NULL, end_date DATE DEFAULT NULL, suggested_roles CLOB DEFAULT NULL, beneficiaries_reached CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, project_id INTEGER NOT NULL, CONSTRAINT FK_92ED7784166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO program (id, name, start_date, end_date, suggested_roles, beneficiaries_reached, created_at, updated_at, project_id) SELECT id, name, start_date, end_date, suggested_roles, beneficiaries_reached, created_at, updated_at, project_id FROM __temp__program');
        $this->addSql('DROP TABLE __temp__program');
        $this->addSql('CREATE INDEX IDX_92ED7784166D1F9C ON program (project_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__project AS SELECT id, name, ownership, partner_organization_name, description, is_active, created_at, updated_at, branch_id FROM project');
        $this->addSql('DROP TABLE project');
        $this->addSql('CREATE TABLE project (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, ownership VARCHAR(20) NOT NULL, partner_organization_name VARCHAR(255) DEFAULT NULL, description CLOB DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, branch_id INTEGER NOT NULL, CONSTRAINT FK_2FB3D0EEDCD6CC49 FOREIGN KEY (branch_id) REFERENCES branch (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO project (id, name, ownership, partner_organization_name, description, is_active, created_at, updated_at, branch_id) SELECT id, name, ownership, partner_organization_name, description, is_active, created_at, updated_at, branch_id FROM __temp__project');
        $this->addSql('DROP TABLE __temp__project');
        $this->addSql('CREATE INDEX IDX_2FB3D0EEDCD6CC49 ON project (branch_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__stay AS SELECT id, start_date, end_date, created_at, updated_at, volunteer_id, branch_id FROM stay');
        $this->addSql('DROP TABLE stay');
        $this->addSql('CREATE TABLE stay (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, volunteer_id INTEGER NOT NULL, branch_id INTEGER NOT NULL, CONSTRAINT FK_5E09839C8EFAB6B1 FOREIGN KEY (volunteer_id) REFERENCES volunteer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5E09839CDCD6CC49 FOREIGN KEY (branch_id) REFERENCES branch (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO stay (id, start_date, end_date, created_at, updated_at, volunteer_id, branch_id) SELECT id, start_date, end_date, created_at, updated_at, volunteer_id, branch_id FROM __temp__stay');
        $this->addSql('DROP TABLE __temp__stay');
        $this->addSql('CREATE INDEX IDX_5E09839C8EFAB6B1 ON stay (volunteer_id)');
        $this->addSql('CREATE INDEX IDX_5E09839CDCD6CC49 ON stay (branch_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__volunteer AS SELECT id, first_name, last_name, email, phone, notes, nationality, country_of_residence, date_of_birth, gender, profession, interests, emergency_contacts, accommodation_preference, pickup_airport, social_media_url, supervisor, passport_number_ciphertext, passport_expires_on, created_at, updated_at, photo_id FROM volunteer');
        $this->addSql('DROP TABLE volunteer');
        $this->addSql('CREATE TABLE volunteer (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, notes CLOB DEFAULT NULL, nationality VARCHAR(2) DEFAULT NULL, country_of_residence VARCHAR(2) DEFAULT NULL, date_of_birth DATE DEFAULT NULL, gender VARCHAR(20) DEFAULT NULL, profession VARCHAR(255) DEFAULT NULL, interests CLOB DEFAULT NULL, emergency_contacts CLOB DEFAULT NULL, accommodation_preference VARCHAR(255) DEFAULT NULL, pickup_airport VARCHAR(255) DEFAULT NULL, social_media_url VARCHAR(255) DEFAULT NULL, supervisor VARCHAR(255) DEFAULT NULL, passport_number_ciphertext CLOB DEFAULT NULL, passport_expires_on DATE DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, photo_id INTEGER DEFAULT NULL, CONSTRAINT FK_5140DEDB7E9E4C8C FOREIGN KEY (photo_id) REFERENCES volunteer_photo (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO volunteer (id, first_name, last_name, email, phone, notes, nationality, country_of_residence, date_of_birth, gender, profession, interests, emergency_contacts, accommodation_preference, pickup_airport, social_media_url, supervisor, passport_number_ciphertext, passport_expires_on, created_at, updated_at, photo_id) SELECT id, first_name, last_name, email, phone, notes, nationality, country_of_residence, date_of_birth, gender, profession, interests, emergency_contacts, accommodation_preference, pickup_airport, social_media_url, supervisor, passport_number_ciphertext, passport_expires_on, created_at, updated_at, photo_id FROM __temp__volunteer');
        $this->addSql('DROP TABLE __temp__volunteer');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5140DEDB7E9E4C8C ON volunteer (photo_id)');
    }
}
