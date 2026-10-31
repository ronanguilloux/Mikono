<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ChatGPT's OAuth client, public like Claude's, and which client made each read.
 * See ADR 0051.
 */
final class Version20261031160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed the ChatGPT OAuth client and record the client on api_access';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO oauth2_client (identifier, secret, name, redirect_uris, grants, scopes, active, allow_plain_text_pkce)
             VALUES ('chatgpt', NULL, 'ChatGPT', 'https://chatgpt.com/connector_platform_oauth_redirect', 'authorization_code refresh_token', 'read', 1, 0)",
        );
        $this->addSql("ALTER TABLE api_access ADD COLUMN client VARCHAR(32) DEFAULT 'claude' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_access DROP COLUMN client');
        $this->addSql("DELETE FROM oauth2_client WHERE identifier = 'chatgpt'");
    }
}
