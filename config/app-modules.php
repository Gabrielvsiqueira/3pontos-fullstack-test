<?php

declare(strict_types=1);

use Tests\TestCase;

return [
    'modules_namespace' => 'Passa',
    'modules_vendor' => 'passa',
    'modules_directory' => 'app-modules',
    'tests_base' => TestCase::class,
    'stubs' => null,
    'should_discover_events' => false,
];
