<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude([
        __DIR__ . '/node_modules',
        __DIR__ . '/vendor',
    ])
    ->ignoreVCSIgnored(true)
    ->ignoreDotFiles(false)
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS' => true,
        '@PER-CS:risky' => true,
        '@PHP80Migration:risky' => true,
        '@PHP82Migration' => true,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
;
