<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

// Files to fix
$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->exclude(['var', 'vendor', 'node_modules', 'public'])
    ->name('*.php')
;

// Config
$config = new Config();

// Allow risky rules (ex. strict_types)
$config->setRiskyAllowed(true);

// Set rules
$config->setRules([
    '@PSR12'                 => true,
    '@Symfony'               => true,
    '@Symfony:risky'         => true,
    'array_syntax'           => ['syntax' => 'short'],
    'binary_operator_spaces' => ['default' => 'align_single_space'],
    'declare_strict_types'   => true,
    'no_unused_imports'      => true,
    'ordered_imports'        => ['sort_algorithm' => 'alpha'],
    'phpdoc_order'           => true,
    'strict_comparison'      => true,
    'yoda_style'             => false,
]);

// Set finder
$config->setFinder($finder);

return $config;
