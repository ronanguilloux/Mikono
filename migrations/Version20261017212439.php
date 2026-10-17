<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The social media link duplicated the dedicated Instagram, Facebook and
 * LinkedIn fields word for word; the one value with no other home was
 * re-entered by hand. A plain DROP COLUMN, not Doctrine's table rebuild.
 */
final class Version20261017212439 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop volunteer.social_media_url, duplicated by the dedicated social fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE volunteer DROP COLUMN social_media_url');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE volunteer ADD COLUMN social_media_url VARCHAR(255) DEFAULT NULL');
    }
}
