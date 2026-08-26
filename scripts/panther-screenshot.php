#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Symfony\Component\Panther\Client;

$options = getopt('', [
    'path::', 'base-url::', 'login', 'email::', 'password::',
    'width::', 'height::', 'wait-selector::', 'wait-timeout::',
    'click::', 'out::', 'help',
]);

if (isset($options['help'])) {
    fwrite(STDOUT, <<<TXT
        Ad-hoc UI screenshot via Symfony Panther (Chromium), against the
        already-running dev app. No PHPUnit, no test webserver, no fixtures.

        Usage: docker compose exec php php scripts/panther-screenshot.php [options]

          --path=/reports            Path to navigate to (default: /)
          --base-url=https://localhost
          --login                    Perform the /login form flow first
          --email=...                Required with --login unless PANTHER_LOGIN_EMAIL is set
          --password=...             Required with --login unless PANTHER_LOGIN_PASSWORD is set
          --width=375 --height=812   Resize the viewport (e.g. for mobile-nav checks)
          --wait-selector=".foo"     Wait for visibility before screenshotting (Turbo Drive-safe)
          --wait-timeout=10          Seconds
          --click=".foo"             Click a selector before screenshotting; repeatable
          --out=name.png             Filename under var/screenshots/ (default: derived + timestamp)

        Screenshots are written inside the container under var/screenshots/.
        var/ is excluded from the dev bind-mount (compose.override.yaml), so
        pull the file to the host with:
          docker compose cp php:/app/var/screenshots/<name> <host-destination>

        See docs/adr/0007-adopt-panther-for-adhoc-visual-verification.md and
        AGENTS.md's "Testing conventions" section.

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
$width = null !== $single($options, 'width') ? (int) $single($options, 'width') : null;
$height = null !== $single($options, 'height') ? (int) $single($options, 'height') : null;
$waitSelector = $single($options, 'wait-selector');
$waitTimeout = null !== $single($options, 'wait-timeout') ? (int) $single($options, 'wait-timeout') : 10;
$clicks = (array) ($options['click'] ?? []);
$outName = $single($options, 'out') ?? preg_replace('/[^a-z0-9]+/i', '-', trim($path, '/') ?: 'root') . '-' . date('Ymd-His') . '.png';

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
        $client->wait($waitTimeout)->until(
            static fn($driver) => !str_contains($driver->getCurrentURL(), '/login'),
        );
    }

    if (null !== $width && null !== $height) {
        $client->manage()->window()->setSize(new WebDriverDimension($width, $height));
    }

    $client->request('GET', $path);

    if (null !== $waitSelector) {
        $client->waitForVisibility($waitSelector, $waitTimeout);
    }

    foreach ($clicks as $selector) {
        $client->findElement(WebDriverBy::cssSelector($selector))->click();
    }

    $client->takeScreenshot($outPath);
    fwrite(STDOUT, "Saved (in-container): {$outPath}\n");
    fwrite(STDOUT, "Pull to host: docker compose cp php:/app/var/screenshots/{$outName} <host-destination>\n");
} finally {
    $client->quit();
}
