<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tighten\Ziggy\BladeRouteGenerator;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Ziggy prints the full route list only once per PHP process; each test is a fresh page.
        BladeRouteGenerator::$generated = false;
    }
}
