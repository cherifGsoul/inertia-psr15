<?php

declare(strict_types=1);


use Sirix\CsFixerConfig\ConfigBuilder;

return ConfigBuilder::create()
    ->inDir(__DIR__ . '/src')
    ->inDir(__DIR__ . '/test')
    ->setRules([
        '@PHP8x2Migration' => true,
        'PedroTroller/line_break_between_method_arguments' => [
            'max-args' => 4,
            'max-length' => 140,
            'automatic-argument-merge' => true,
            'inline-attributes' => true,
        ],
        'Gordinskiy/line_length_limit' => false,
        'php_unit_test_class_requires_covers' => false,
        'php_unit_internal_class' => false,
    ])
    ->getConfig();

