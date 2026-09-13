<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * UCESCO's branches, seeded here rather than in the fixtures: they are real
 * reference data, so production must get them on deploy. Foundry resets dev
 * and test by replaying migrations, so both get them too. See ADR 0025.
 */
final class Version20260913204837 extends AbstractMigration
{
    private const array BRANCHES = [
        ['Nairobi (HQ)', 'Kibera Plaza, Off Ngong Road', 'Kibera, Mathare, Kayole, Kariobangi', 'Urban education, youth development, medical outreach, NGO administration.'],
        ['Mombasa', 'UCESCO Africa House, Tudor', 'Makupa, Malindi, Kilifi', 'Marine conservation (Plastic-Free Ocean), coastal medical camps, agriculture/fish farming.'],
        ['Samburu', 'Ichingei Village', 'Maralal, rural pastoralist communities', "School construction/renovation, women's empowerment, cultural preservation."],
        ['Uganda', 'Ggaba, Kampala (Wakiso)', 'Kampala and surrounds', 'Sports coaching, cross-border development, media and photography documentation.'],
        ['USA (Global)', 'Croydon, Pennsylvania', 'International', '501(c)(3) administration, fundraising, international volunteer coordination.'],
    ];

    public function getDescription(): string
    {
        return 'Add branch and seed UCESCO\'s five branches (ADR 0025)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE branch (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, physical_location VARCHAR(255) NOT NULL, project_zones CLOB DEFAULT NULL, program_focus CLOB DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');

        foreach (self::BRANCHES as [$name, $physicalLocation, $projectZones, $programFocus]) {
            $this->addSql(
                'INSERT INTO branch (name, physical_location, project_zones, program_focus, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
                [$name, $physicalLocation, $projectZones, $programFocus],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE branch');
    }
}
