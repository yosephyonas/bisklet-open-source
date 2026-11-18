<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LockAlertsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_alerts_when_there_are_no_gateway_alarms(): void
    {
        Http::fake([
            '*/devices/live' => Http::response(['count' => 0, 'devices' => []]),
        ]);

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'fyda_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/control/locks/alerts')
            ->assertOk()
            ->assertSee('No gateway alarms');
    }
}
