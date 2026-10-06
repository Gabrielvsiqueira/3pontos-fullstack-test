<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The suite must not depend on a committed key, on built assets or on the network.
        if (blank(config('app.key'))) {
            config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        }

        $this->withoutVite();
        Http::preventStrayRequests();
    }
}
