<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\AccessToken;
use League\Bundle\OAuth2ServerBundle\Model\AuthorizationCode;
use League\Bundle\OAuth2ServerBundle\Model\RefreshToken;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Deletes expired OAuth rows, for the time limit in ADR 0034 rule 6. Use this,
 * not the bundle's league:oauth2-server:clear-expired-tokens.
 *
 * An access token outlives its hour here for as long as a refresh token points
 * at it. That link is the only way from a refresh token back to its user, and
 * the bundle's command cuts it: revoking a user would then miss the refresh
 * token, so an assistant could quietly sign itself back in after a Disconnect.
 */
#[AsCommand(name: 'app:oauth:prune', description: 'Delete expired OAuth tokens, keeping those a refresh token still needs')]
final class PruneOAuthTokensCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new \DateTimeImmutable();

        $this->deleteExpired(RefreshToken::class, $now);
        $this->deleteExpired(AuthorizationCode::class, $now);

        $stillNeeded = \sprintf('SELECT IDENTITY(rt.accessToken) FROM %s rt WHERE rt.accessToken IS NOT NULL', RefreshToken::class);
        $this->entityManager
            ->createQuery(\sprintf('DELETE FROM %s t WHERE t.expiry < :now AND t.identifier NOT IN (%s)', AccessToken::class, $stillNeeded))
            ->setParameter('now', $now)
            ->execute();

        $output->writeln('Expired OAuth tokens deleted.');

        return Command::SUCCESS;
    }

    /** @param class-string $class */
    private function deleteExpired(string $class, \DateTimeImmutable $now): void
    {
        $this->entityManager
            ->createQuery(\sprintf('DELETE FROM %s t WHERE t.expiry < :now', $class))
            ->setParameter('now', $now)
            ->execute();
    }
}
