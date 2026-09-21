<?php

namespace Tests\Feature;

use App\Filament\Widgets\StatsOverview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_mounts_all_manager_widgets(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();

        // Widgets are lazy-loaded Livewire components: verify they are mounted.
        $content = $response->getContent();
        foreach (['StatsOverview', 'PendingPayments', 'OpenComplaints', 'FollowUpInquiries', 'OccupancyChart'] as $widget) {
            $this->assertStringContainsString($widget, $content);
        }
    }

    public function test_stats_overview_returns_six_kpis(): void
    {
        $widget = new StatsOverview;

        $method = new \ReflectionMethod($widget, 'getStats');
        $method->setAccessible(true);

        $this->assertCount(6, $method->invoke($widget));
    }

    public function test_all_resource_index_pages_render(): void
    {
        $user = User::factory()->create();

        $pages = [
            '/admin/properties', '/admin/rooms', '/admin/beds', '/admin/tenants',
            '/admin/bookings', '/admin/payments', '/admin/expenses', '/admin/complaints',
        ];

        foreach ($pages as $page) {
            $this->actingAs($user)->get($page)->assertOk();
        }
    }
}
