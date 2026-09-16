<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The Sign-ins table behind /usage (ADR 0028).
 */
final class Version20260916195355 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create login_attempt (ADR 0028)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE login_attempt (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, occurred_at DATETIME NOT NULL, identifier VARCHAR(180) DEFAULT NULL, succeeded BOOLEAN NOT NULL, ip VARCHAR(45) DEFAULT NULL)');
        $this->addSql('CREATE INDEX idx_login_attempt_occurred_at ON login_attempt (occurred_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE login_attempt');
    }
}
