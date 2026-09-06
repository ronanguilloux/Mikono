<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Browser-side gestures that leave no HTTP request behind, so the access log
 * behind /usage cannot see them. See ADR 0021.
 *
 * Three columns on purpose — no user, no IP, no context payload. See the
 * UsageEvent entity docblock for why that is a data-protection decision and
 * not an oversight.
 */
final class Version20260906112820 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add usage_event: client-only gestures for the /usage screen (ADR 0021)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE usage_event (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(64) NOT NULL, occurred_at DATETIME NOT NULL)');
        $this->addSql('CREATE INDEX idx_usage_event_name ON usage_event (name)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE usage_event');
    }
}
