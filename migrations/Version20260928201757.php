<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Beneficiary groups become a managed list programs are tagged with.
 * Deliberately not seeded, unlike branch and skill: the VM enters the list in
 * production. See ADR 0030.
 */
final class Version20260928201757 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add beneficiary_group and program_beneficiary_group, unseeded (ADR 0030)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE beneficiary_group (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uniq_beneficiary_group_name ON beneficiary_group (name)');
        $this->addSql('CREATE TABLE program_beneficiary_group (program_id INTEGER NOT NULL, beneficiary_group_id INTEGER NOT NULL, PRIMARY KEY (program_id, beneficiary_group_id), CONSTRAINT FK_1C4600B63EB8070A FOREIGN KEY (program_id) REFERENCES program (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1C4600B6C88A4AE4 FOREIGN KEY (beneficiary_group_id) REFERENCES beneficiary_group (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_1C4600B63EB8070A ON program_beneficiary_group (program_id)');
        $this->addSql('CREATE INDEX IDX_1C4600B6C88A4AE4 ON program_beneficiary_group (beneficiary_group_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE program_beneficiary_group');
        $this->addSql('DROP TABLE beneficiary_group');
    }
}
