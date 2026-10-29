<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_endpoint_returns_expected_payload(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'RoadPriority API is running',
            ]);
    }

    public function test_health_endpoint_does_not_leak_internal_information(): void
    {
        $body = $this->getJson('/api/health')->getContent();

        $this->assertStringNotContainsString('APP_KEY', $body);
        $this->assertStringNotContainsString(config('database.connections.mysql.database'), $body);
        $this->assertStringNotContainsString(PHP_VERSION, $body);
    }
}

