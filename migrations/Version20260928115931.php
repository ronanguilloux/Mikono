<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Skills become a managed list shared by volunteers and programs, seeded here
 * so production gets it on deploy. The volunteer's free-text column is
 * dropped, not converted: free text can't be mapped onto the list reliably.
 * DROP COLUMN (SQLite >= 3.35) rather than Doctrine's table rebuild, whose
 * DROP TABLE volunteer would cascade into stays. See ADR 0036.
 */
final class Version20260928115931 extends AbstractMigration
{
    /** Condensed from the programs' suggested roles in docs/fixtures/rosters.yaml. */
    private const array SKILLS = [
        'Art & crafts',
        'Beauty & nail art',
        'Computer & digital skills',
        'Construction',
        'Counselling',
        'Dance',
        'Dentistry',
        'Early-childhood care',
        'Entrepreneurship & business',
        'Environmental education',
        'Farming & gardening',
        'Financial literacy',
        'First aid',
        'Fundraising & events',
        'Graphic design',
        'Language teaching',
        'Marine conservation',
        'Medicine',
        'Music',
        'Nursing & midwifery',
        'Office administration',
        'Painting & decoration',
        'Photography & video',
        'Public-health education',
        'Social media & content',
        'Sports coaching',
        'Swimming & lifeguarding',
        'Tailoring & fashion',
        'Teaching & tutoring',
        'Web design',
        'Yoga & fitness',
    ];

    public function getDescription(): string
    {
        return 'Add skill, attach it to volunteers and programs, seed the list, drop volunteer.skills (ADR 0036)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE skill (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uniq_skill_name ON skill (name)');
        $this->addSql('CREATE TABLE program_skill (program_id INTEGER NOT NULL, skill_id INTEGER NOT NULL, PRIMARY KEY (program_id, skill_id), CONSTRAINT FK_C05497813EB8070A FOREIGN KEY (program_id) REFERENCES program (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C05497815585C142 FOREIGN KEY (skill_id) REFERENCES skill (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C05497813EB8070A ON program_skill (program_id)');
        $this->addSql('CREATE INDEX IDX_C05497815585C142 ON program_skill (skill_id)');
        $this->addSql('CREATE TABLE volunteer_skill (volunteer_id INTEGER NOT NULL, skill_id INTEGER NOT NULL, PRIMARY KEY (volunteer_id, skill_id), CONSTRAINT FK_6C5339858EFAB6B1 FOREIGN KEY (volunteer_id) REFERENCES volunteer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6C5339855585C142 FOREIGN KEY (skill_id) REFERENCES skill (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_6C5339858EFAB6B1 ON volunteer_skill (volunteer_id)');
        $this->addSql('CREATE INDEX IDX_6C5339855585C142 ON volunteer_skill (skill_id)');
        $this->addSql('ALTER TABLE volunteer DROP COLUMN skills');

        foreach (self::SKILLS as $name) {
            $this->addSql('INSERT INTO skill (name) VALUES (?)', [$name]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE program_skill');
        $this->addSql('DROP TABLE volunteer_skill');
        $this->addSql('DROP TABLE skill');
        $this->addSql('ALTER TABLE volunteer ADD COLUMN skills CLOB DEFAULT NULL');
    }
}
