<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    // config/reference.php is gitignored but NOT gone: FrameworkBundle
    // regenerates it on disk at every dev/test container compile. CI warms
    // the dev container (ci.yml, "Warm the dev container for PHPStan")
    // *before* `composer quality`, so a clean checkout has all 1,691
    // generated lines sitting there by the time cs-check runs. This
    // exclusion is what keeps them out of it — untracking the file did not
    // retire it.
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ])
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@PER-CS2.0' => true,
        'declare_strict_types' => true,
    ])
    ->setFinder($finder)
;
