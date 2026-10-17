<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Data only. Instagram answers that name no account become null, and a
 * typed "Instagram name:" prefix goes, so the profile can link the handle.
 * Matched on values, never ids or handles: this repo is public.
 */
final class Version20261017200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Null the non-answer Instagram values and strip an "Instagram name:" prefix';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE volunteer SET instagram = NULL WHERE LOWER(TRIM(instagram)) IN ('yes', 'i’m not on social media', 'i''m not on social media')");
        $this->addSql("UPDATE volunteer SET instagram = TRIM(SUBSTR(instagram, 16)) WHERE instagram LIKE 'Instagram name:%'");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('The nulled answers carried no account to restore.');
    }
}
