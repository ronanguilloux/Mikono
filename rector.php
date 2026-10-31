<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpSets(php85: true)
    ->withComposerBased(
        symfony: true,
        doctrine: true,
        phpunit: true,
    )
    ->withSkip([
        // It narrows VolunteerPhoto::$bytes to string, but DBAL hydrates a blob as a stream.
        ClassPropertyAssignToConstructorPromotionRector::class => [__DIR__ . '/src/Entity'],
    ])
    ->withCache(cacheDirectory: __DIR__ . '/var/cache/rector')
;
