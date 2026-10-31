<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    new Dotenv()->bootEnv(dirname(__DIR__) . '/.env');
}

// A keypair for this run only: a private key doesn't belong in a public repo,
// not even a test one, and CI's bare `docker run` has no .env.local to read.
$oauthKey = openssl_pkey_new(['private_key_bits' => 2048]);
assert($oauthKey instanceof OpenSSLAsymmetricKey);
openssl_pkey_export($oauthKey, $oauthPrivateKey);
assert(is_string($oauthPrivateKey));
$oauthPublicKey = openssl_pkey_get_details($oauthKey);
assert(is_array($oauthPublicKey) && is_string($oauthPublicKey['key']));
$_SERVER['OAUTH_PRIVATE_KEY'] = $_ENV['OAUTH_PRIVATE_KEY'] = base64_encode($oauthPrivateKey);
$_SERVER['OAUTH_PUBLIC_KEY'] = $_ENV['OAUTH_PUBLIC_KEY'] = base64_encode($oauthPublicKey['key']);

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
