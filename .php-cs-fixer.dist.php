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
        'blank_line_before_statement' => true,
        'method_chaining_indentation' => true,
        'multiline_whitespace_before_semicolons' => [
            'strategy' => 'new_line_for_chained_calls',
        ],
        'native_function_invocation' => true,
        'native_constant_invocation' => true,
        'no_unused_imports' => true,
        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],
        'ordered_interfaces' => [
            'case_sensitive' => true,
        ],
        'ordered_types' => [
            'case_sensitive' => true,
            'null_adjustment' => 'always_last',
        ],
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
;
