<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_route_can_be_hit()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'organization_name' => 'Acme Inc',
        ]);

        $response->assertStatus(201);
    }
}
