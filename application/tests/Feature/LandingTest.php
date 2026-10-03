<?php

namespace Tests\Feature;

use Tests\TestCase;

class LandingTest extends TestCase
{
    public function test_public_portal_explains_pilot_without_showing_internal_records(): void
    {
        $this->get('/')->assertOk()->assertSee('Pengurusan keselamatan maklumat.')->assertSee('Log masuk')->assertDontSee('DEMO-DOC-001');
    }
}
