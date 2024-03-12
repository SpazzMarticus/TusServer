<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/example',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withImportNames(importShortClasses: false)
    ->withPhpSets(php82: true)
    ->withPreparedSets(
        codingStyle: true,
        codeQuality: true,
        deadCode: true,
        earlyReturn: true,
        instanceOf: true,
        privatization: true,
        strictBooleans: true,
        typeDeclarations: true,
    )
;
