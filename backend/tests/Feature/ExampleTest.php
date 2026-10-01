<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_serves_the_spa_shell(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="app"', false);
    }
}
