<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function testAdminCanViewDashboardSummary(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'fyda_verified_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/dashboard/summary');

        $response->assertOk()
            ->assertJsonStructure([
                'bikes' => ['total'],
                'rides' => ['active'],
                'users' => ['total', 'verified'],
            ]);
    }

    public function testRiderCannotAccessDashboard(): void
    {
        $rider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);

        Sanctum::actingAs($rider);

        $response = $this->getJson('/api/v1/dashboard/summary');

        $response->assertForbidden();
    }
}
