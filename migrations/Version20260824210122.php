<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260824210122 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activity_type (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uniq_activity_type_name ON activity_type (name)');
        $this->addSql('CREATE TABLE project (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, location VARCHAR(20) NOT NULL, ownership VARCHAR(20) NOT NULL, partner_organization_name VARCHAR(255) DEFAULT NULL, description CLOB DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE activity_type');
        $this->addSql('DROP TABLE project');
    }
}
