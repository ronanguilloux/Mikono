<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ActivityFactory;
use App\Factory\ActivityTypeFactory;
use App\Factory\EscortFactory;
use App\Factory\ProjectFactory;
use App\Factory\UserFactory;
use App\Factory\VolunteerFactory;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Walks every GET route the router knows about, so a screen nobody wrote a
 * test for still cannot 500 or lose its login requirement unnoticed. A route
 * added tomorrow joins both passes for free.
 */
#[ResetDatabase]
final class RouteSmokeTest extends WebTestCase
{
    /**
     * Route-name prefix => the seeded entity whose id fills that route's
     * `{id}`. Longest first: `activity_type_` must win over `activity_`.
     */
    private const ID_PREFIXES = ['activity_type_', 'activity_', 'escort_', 'project_', 'user_', 'volunteer_'];

    #[Test]
    public function everyGetRouteRendersWithoutAServerError(): void
    {
        $client = static::createClient();
        // WebTestCase otherwise renders the error page and hands back a 500,
        // which reads as "assertion failed" with no stack trace.
        $client->catchExceptions(false);
        $ids = self::seedOneOfEach();
        // /users is #[IsGranted('ROLE_ADMIN')]; a plain ROLE_USER walk would
        // assert 403s and prove nothing about those four screens.
        $client->loginUser(UserFactory::new()->admin()->create());

        foreach (self::walkableRoutes($client, $ids) as $name => $url) {
            // Authenticated, /login redirects to app_home rather than rendering.
            if ('app_login' === $name) {
                continue;
            }

            $client->request('GET', $url);

            // 2xx, not merely "below 500": a missing fixture would 404 here,
            // and a `< 500` assertion would pass vacuously while hiding the
            // very server errors this walk exists to find.
            self::assertResponseIsSuccessful(\sprintf('Route "%s" (%s) did not render.', $name, $url));
        }
    }

    #[Test]
    public function everyGetRouteRedirectsAnonymousVisitorsToLogin(): void
    {
        $client = static::createClient();
        $ids = self::seedOneOfEach();

        foreach (self::walkableRoutes($client, $ids) as $name => $url) {
            $client->request('GET', $url);

            if ('app_login' === $name) {
                self::assertResponseIsSuccessful('The login page must stay public.');

                continue;
            }

            self::assertResponseRedirects(
                '/login',
                message: \sprintf('Route "%s" (%s) is reachable without logging in.', $name, $url),
            );
        }
    }

    /**
     * @return array<string, int> entity key => id, for `{id}` substitution
     */
    private static function seedOneOfEach(): array
    {
        $activity = ActivityFactory::createOne([
            'volunteer' => $volunteer = VolunteerFactory::createOne(),
            'project' => $project = ProjectFactory::createOne(),
            'activityType' => $activityType = ActivityTypeFactory::createOne(),
            'escorts' => [$escort = EscortFactory::createOne()],
        ]);

        return [
            'activity_type_' => (int) $activityType->getId(),
            'activity_' => (int) $activity->getId(),
            'escort_' => (int) $escort->getId(),
            'project_' => (int) $project->getId(),
            'user_' => (int) UserFactory::createOne()->getId(),
            'volunteer_' => (int) $volunteer->getId(),
        ];
    }

    /**
     * @param array<string, int> $ids
     *
     * @return array<string, string> route name => generated URL
     */
    private static function walkableRoutes(KernelBrowser $client, array $ids): array
    {
        $router = $client->getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        $urls = [];

        foreach ($router->getRouteCollection() as $name => $route) {
            $methods = $route->getMethods();

            if ([] !== $methods && !\in_array('GET', $methods, true)) {
                continue;
            }

            // Framework and UX internals (_profiler, _wdt, ux_live_component)
            // are not this app's screens, and app_logout would end the session
            // halfway through the loop.
            if (str_starts_with($name, '_') || str_starts_with($name, 'ux_') || 'app_logout' === $name) {
                continue;
            }

            $parameters = [];

            if (str_contains($route->getPath(), '{id}')) {
                $parameters['id'] = $ids[self::idPrefixFor($name)];
            }

            $urls[$name] = $router->generate($name, $parameters);
        }

        self::assertNotEmpty($urls, 'The router returned no walkable GET routes.');

        return $urls;
    }

    private static function idPrefixFor(string $routeName): string
    {
        foreach (self::ID_PREFIXES as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return $prefix;
            }
        }

        self::fail(\sprintf('Route "%s" takes an {id} but no fixture is seeded for it.', $routeName));
    }
}
