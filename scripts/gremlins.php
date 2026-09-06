#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Symfony\Component\Panther\Client;

$options = getopt('', [
    'path::', 'base-url::', 'login', 'email::', 'password::',
    'seed::', 'gremlins::', 'delay::', 'wait-timeout::', 'out::', 'help',
]);

if (isset($options['help'])) {
    fwrite(STDOUT, <<<TXT
        Monkey testing via gremlins.js, unleashed on the already-running dev
        app through Panther's Chromium. No PHPUnit, no npm, no new dependency:
        the horde is a single pinned dist file loaded from unpkg at run time.

        Usage: docker compose exec php php scripts/gremlins.php [options]

          --path=/activities/new-batch  Page to attack (default: /)
          --base-url=https://localhost  localhost only — see the warning below
          --login                       Perform the /login form flow first
          --email=...                   Required with --login unless PANTHER_LOGIN_EMAIL is set
          --password=...                Required with --login unless PANTHER_LOGIN_PASSWORD is set
          --seed=1                      Randomizer seed, so a crash is reproducible (default: 1)
          --gremlins=500                Number of attacks (default: 500)
          --delay=10                    Milliseconds between attacks (default: 10)
          --wait-timeout=60             Seconds to let the horde run (default: 60)
          --out=name.png                Screenshot filename under var/screenshots/

        THE HORDE CLICKS DELETE. It is pointed at fixture data on the dev
        container and refuses any other host outright. Reseed afterwards:
          docker compose exec php bin/console foundry:load-fixtures --no-interaction

        Turbo Drive is pinned during the run (turbo:before-visit is cancelled)
        so the horde stays on one page and its findings stay attributable.

        Exits non-zero when any JS error, console error or mogwai alarm was
        caught. See docs/brainstorm/05-exercising-the-app-and-the-panther-question.md
        and AGENTS.md's "Testing conventions" section.

        TXT);
    exit(0);
}

// getopt() silently turns a single-value option into an array if it's
// passed more than once — normalize to the last occurrence instead of
// letting that crash deep inside the Panther/WebDriver call stack.
$single = static function (array $options, string $key): ?string {
    if (!isset($options[$key])) {
        return null;
    }

    $value = $options[$key];

    return \is_array($value) ? (string) end($value) : (string) $value;
};

$path = $single($options, 'path') ?? '/';
$baseUrl = $single($options, 'base-url') ?? 'https://localhost';
$doLogin = \array_key_exists('login', $options);
$email = $single($options, 'email') ?? (getenv('PANTHER_LOGIN_EMAIL') ?: null);
$password = $single($options, 'password') ?? (getenv('PANTHER_LOGIN_PASSWORD') ?: null);
$seed = (int) ($single($options, 'seed') ?? 1);
$attacks = (int) ($single($options, 'gremlins') ?? 500);
$delay = (int) ($single($options, 'delay') ?? 10);
$waitTimeout = (int) ($single($options, 'wait-timeout') ?? 60);
$outName = $single($options, 'out') ?? 'gremlins-' . (preg_replace('/[^a-z0-9]+/i', '-', trim($path, '/')) ?: 'root') . '-' . date('Ymd-His') . '.png';

// The horde clicks Delete, so this must never reach the UAT or production
// box. No override flag: a run against real data is not a mistake worth
// making convenient.
$host = parse_url($baseUrl, PHP_URL_HOST);
if (!\in_array($host, ['localhost', '127.0.0.1', 'php'], true)) {
    fwrite(STDERR, "Error: refusing to unleash gremlins at '{$baseUrl}'.\n");
    fwrite(STDERR, "The horde clicks Delete. It runs against the local dev container only.\n");
    exit(1);
}

if ($doLogin && (!$email || !$password)) {
    fwrite(STDERR, "Error: --login requires --email/--password or PANTHER_LOGIN_EMAIL/PANTHER_LOGIN_PASSWORD env vars.\n");
    exit(1);
}

// https://localhost uses a self-signed cert (FrankenPHP/Caddy dev default);
// keep every other Panther default (headless, --no-sandbox from
// PANTHER_NO_SANDBOX, --disable-dev-shm-usage from PANTHER_CHROME_ARGUMENTS,
// both already set in the Dockerfile) by appending rather than replacing.
$_SERVER['PANTHER_CHROME_ARGUMENTS'] = trim(($_SERVER['PANTHER_CHROME_ARGUMENTS'] ?? '') . ' --ignore-certificate-errors');

$outDir = dirname(__DIR__) . '/var/screenshots';
if (!is_dir($outDir) && !mkdir($outDir, 0o775, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Error: could not create {$outDir}\n");
    exit(1);
}
$outPath = $outDir . '/' . $outName;

$client = Client::createChromeClient(null, null, [], $baseUrl);

try {
    if ($doLogin) {
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Sign in')->form([
            '_username' => $email,
            '_password' => $password,
        ]);
        $client->submit($form);
        $client->wait(10)->until(
            static fn($driver) => !str_contains($driver->getCurrentURL(), '/login'),
        );
    }

    $client->request('GET', $path);
    $client->manage()->timeouts()->setScriptTimeout(30);

    // Collect everything the page complains about, before the horde starts
    // making it complain. Chrome's own browser log needs a capability Panther
    // doesn't set, so hook the page instead.
    $client->executeScript(<<<'JS'
        window.__gremlinErrors = [];
        // A run that reports nothing is only reassuring if the horde landed
        // something; this is the difference between "clean" and "inert".
        window.__gremlinActions = 0;
        ['click', 'keypress', 'touchstart', 'scroll'].forEach(
            (type) => document.addEventListener(type, () => window.__gremlinActions++, true),
        );
        window.addEventListener('error', (e) => window.__gremlinErrors.push('error: ' + e.message));
        window.addEventListener('unhandledrejection', (e) => window.__gremlinErrors.push('rejection: ' + e.reason));
        const originalError = console.error.bind(console);
        console.error = (...args) => {
            window.__gremlinErrors.push('console: ' + args.map(String).join(' '));
            originalError(...args);
        };
        // Turbo Drive would swap the body out from under the horde on the
        // first link click, leaving its findings attributed to whichever page
        // happened to be loaded at the time.
        document.addEventListener('turbo:before-visit', (e) => e.preventDefault());
        JS);

    $loaded = $client->executeAsyncScript(<<<'JS'
        const done = arguments[arguments.length - 1];
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/gremlins.js@2.2.0/dist/gremlins.min.js';
        script.onload = () => done(true);
        script.onerror = () => done(false);
        document.head.appendChild(script);
        JS);

    if (true !== $loaded) {
        fwrite(STDERR, "Error: could not load gremlins.js from unpkg — is the container online?\n");
        exit(1);
    }

    fwrite(STDOUT, "Unleashing {$attacks} gremlins on {$baseUrl}{$path} (seed {$seed})…\n");

    $client->executeScript(\sprintf(<<<'JS'
        window.__gremlinsDone = false;
        const gremlins = window.gremlins;
        gremlins.createHorde({
            randomizer: new gremlins.Chance(%d),
            strategies: [gremlins.strategies.distribution({ nb: %d, delay: %d })],
            logger: {
                log() {},
                info() {},
                warn() {},
                // Mogwai alarms (dropped frame rate, an alert() left open)
                // arrive here, and they are the interesting half of a run.
                error: (...args) => window.__gremlinErrors.push('mogwai: ' + args.map(String).join(' ')),
            },
        }).unleash().then(() => { window.__gremlinsDone = true; });
        JS, $seed, $attacks, $delay));

    $client->wait($waitTimeout, 500)->until(
        static fn($driver) => true === $driver->executeScript('return window.__gremlinsDone === true'),
    );

    /** @var list<string> $errors */
    $errors = $client->executeScript('return window.__gremlinErrors') ?? [];
    $landed = (int) $client->executeScript('return window.__gremlinActions');
    $pageText = $client->getCrawler()->filter('body')->text('');

    foreach (['Internal Server Error', 'Whoops, looks like something went wrong'] as $needle) {
        if (str_contains($pageText, $needle)) {
            $errors[] = 'server: the page ended on a Symfony error page ("' . $needle . '")';
        }
    }

    $client->takeScreenshot($outPath);

    fwrite(STDOUT, "{$landed} event(s) landed on the page.\n");
    fwrite(STDOUT, "Saved (in-container): {$outPath}\n");
    fwrite(STDOUT, "Pull to host: docker compose cp php:/app/var/screenshots/{$outName} <host-destination>\n");
    fwrite(STDOUT, "Reseed fixtures: docker compose exec php bin/console foundry:load-fixtures --no-interaction\n");

    if ([] === $errors) {
        fwrite(STDOUT, "\nNothing caught.\n");
        exit(0);
    }

    fwrite(STDOUT, \sprintf("\n%d finding(s), reproduce with --seed=%d:\n", \count($errors), $seed));
    foreach (array_unique($errors) as $error) {
        fwrite(STDOUT, "  - {$error}\n");
    }

    exit(1);
} finally {
    $client->quit();
}
