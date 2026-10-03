<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') === 'mysql' && config('database.connections.mysql.database') !== 'e_isms_test') {
            throw new \RuntimeException('MySQL tests must only use e_isms_test.');
        }
        $this->withoutVite();
    }
}
