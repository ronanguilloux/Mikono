<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Recruitment sources become a managed list held by volunteers, seeded here
 * so production gets it on deploy. See ADR 0041.
 */
final class Version20261003111421 extends AbstractMigration
{
    /** The VM's channels, verbatim (2026-10-03). */
    private const array SOURCES = [
        'Volunteer World',
        'Website/Email',
        'WhatsApp',
        'TikTok',
        'Instagram',
        'Facebook',
        'YouTube',
    ];

    public function getDescription(): string
    {
        return 'Add source, attach it to volunteers, seed the list (ADR 0041)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE source (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uniq_source_name ON source (name)');
        $this->addSql('CREATE TABLE volunteer_source (volunteer_id INTEGER NOT NULL, source_id INTEGER NOT NULL, PRIMARY KEY (volunteer_id, source_id), CONSTRAINT FK_C0B829E8EFAB6B1 FOREIGN KEY (volunteer_id) REFERENCES volunteer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C0B829E953C1C61 FOREIGN KEY (source_id) REFERENCES source (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C0B829E8EFAB6B1 ON volunteer_source (volunteer_id)');
        $this->addSql('CREATE INDEX IDX_C0B829E953C1C61 ON volunteer_source (source_id)');

        foreach (self::SOURCES as $name) {
            $this->addSql('INSERT INTO source (name) VALUES (?)', [$name]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE volunteer_source');
        $this->addSql('DROP TABLE source');
    }
}
