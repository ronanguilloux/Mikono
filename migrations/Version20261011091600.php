<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011091600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add indicator, unseeded, and achievement.indicator_id / quantity, both nullable (ADR 0047)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE indicator (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uniq_indicator_name ON indicator (name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__achievement AS SELECT id, title, description, achieved_on, created_at, updated_at, stay_id, project_id, created_by_id, updated_by_id FROM achievement');
        $this->addSql('DROP TABLE achievement');
        $this->addSql('CREATE TABLE achievement (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, achieved_on DATE NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, stay_id INTEGER NOT NULL, project_id INTEGER NOT NULL, created_by_id INTEGER DEFAULT NULL, updated_by_id INTEGER DEFAULT NULL, quantity INTEGER DEFAULT NULL, indicator_id INTEGER DEFAULT NULL, CONSTRAINT FK_96737FF1FB3AF7D6 FOREIGN KEY (stay_id) REFERENCES stay (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_96737FF1166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_96737FF1B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_96737FF1896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_96737FF14402854A FOREIGN KEY (indicator_id) REFERENCES indicator (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO achievement (id, title, description, achieved_on, created_at, updated_at, stay_id, project_id, created_by_id, updated_by_id) SELECT id, title, description, achieved_on, created_at, updated_at, stay_id, project_id, created_by_id, updated_by_id FROM __temp__achievement');
        $this->addSql('DROP TABLE __temp__achievement');
        $this->addSql('CREATE INDEX IDX_96737FF1896DBBDE ON achievement (updated_by_id)');
        $this->addSql('CREATE INDEX IDX_96737FF1B03A8386 ON achievement (created_by_id)');
        $this->addSql('CREATE INDEX IDX_96737FF1FB3AF7D6 ON achievement (stay_id)');
        $this->addSql('CREATE INDEX IDX_96737FF1166D1F9C ON achievement (project_id)');
        $this->addSql('CREATE INDEX IDX_96737FF14402854A ON achievement (indicator_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE indicator');
        $this->addSql('CREATE TEMPORARY TABLE __temp__achievement AS SELECT id, title, description, achieved_on, created_at, updated_at, stay_id, project_id, created_by_id, updated_by_id FROM achievement');
        $this->addSql('DROP TABLE achievement');
        $this->addSql('CREATE TABLE achievement (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, achieved_on DATE NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, stay_id INTEGER NOT NULL, project_id INTEGER NOT NULL, created_by_id INTEGER DEFAULT NULL, updated_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_96737FF1FB3AF7D6 FOREIGN KEY (stay_id) REFERENCES stay (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_96737FF1166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_96737FF1B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_96737FF1896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO achievement (id, title, description, achieved_on, created_at, updated_at, stay_id, project_id, created_by_id, updated_by_id) SELECT id, title, description, achieved_on, created_at, updated_at, stay_id, project_id, created_by_id, updated_by_id FROM __temp__achievement');
        $this->addSql('DROP TABLE __temp__achievement');
        $this->addSql('CREATE INDEX IDX_96737FF1FB3AF7D6 ON achievement (stay_id)');
        $this->addSql('CREATE INDEX IDX_96737FF1166D1F9C ON achievement (project_id)');
        $this->addSql('CREATE INDEX IDX_96737FF1B03A8386 ON achievement (created_by_id)');
        $this->addSql('CREATE INDEX IDX_96737FF1896DBBDE ON achievement (updated_by_id)');
    }
}
