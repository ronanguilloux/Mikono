<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The one OAuth client, public and seeded here, so an admin can connect Claude
 * with no console and no secret. See ADR 0050.
 */
final class Version20261031140000 extends AbstractMigration
{
    private const array CLIENT = [
        'Claude',
        'https://claude.ai/api/mcp/auth_callback http://localhost:8765/callback',
        'authorization_code refresh_token',
        'read',
    ];

    public function getDescription(): string
    {
        return 'Seed the public OAuth client and revoke refresh tokens that no user can be traced from';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE oauth2_client SET secret = NULL, name = ?, redirect_uris = ?, grants = ?, scopes = ?, active = 1, allow_plain_text_pkce = 0 WHERE identifier = 'claude'",
            self::CLIENT,
        );
        $this->addSql(
            "INSERT INTO oauth2_client (identifier, secret, name, redirect_uris, grants, scopes, active, allow_plain_text_pkce)
             SELECT 'claude', NULL, ?, ?, ?, ?, 1, 0
             WHERE NOT EXISTS (SELECT 1 FROM oauth2_client WHERE identifier = 'claude')",
            self::CLIENT,
        );
        // The bundle's clear-expired-tokens blanked this link, which is the only
        // way back to a user, so Disconnect couldn't reach these. Signing in
        // again is a small price: see App\Command\PruneOAuthTokensCommand.
        $this->addSql('UPDATE oauth2_refresh_token SET revoked = 1 WHERE access_token IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM oauth2_client WHERE identifier = 'claude'");
    }
}
