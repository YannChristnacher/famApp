<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tighten\Ziggy\BladeRouteGenerator;
use Tighten\Ziggy\Ziggy;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Ziggy caches the route list and prints it only once per PHP process; each test is a fresh page.
        BladeRouteGenerator::$generated = false;
        Ziggy::clearRoutes();
    }
}
