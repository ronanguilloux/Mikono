<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Achievements on a volunteer's stay. See ADR 0038.
 */
final class Version20260928204354 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add achievement, belonging to a stay and a project (ADR 0038)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE achievement (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, achieved_on DATE NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, stay_id INTEGER NOT NULL, project_id INTEGER NOT NULL, CONSTRAINT FK_96737FF1FB3AF7D6 FOREIGN KEY (stay_id) REFERENCES stay (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_96737FF1166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_96737FF1FB3AF7D6 ON achievement (stay_id)');
        $this->addSql('CREATE INDEX IDX_96737FF1166D1F9C ON achievement (project_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE achievement');
    }
}
