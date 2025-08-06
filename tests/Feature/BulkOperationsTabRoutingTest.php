<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkOperationsTabRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create and authenticate a user
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    public function test_can_access_bulk_operations_index()
    {
        $response = $this->get('/operations/bulk');
        
        // Should redirect to overview tab
        $response->assertStatus(302);
        $response->assertRedirect('/operations/bulk/overview');
    }

    public function test_can_access_all_bulk_operations_tabs()
    {
        $routes = [
            '/operations/bulk/overview',
            '/operations/bulk/templates', 
            '/operations/bulk/attributes',
            '/operations/bulk/quality',
            '/operations/bulk/recommendations',
            '/operations/bulk/ai',
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            $response->assertStatus(200);
            $response->assertSee('Bulk Operations');
        }
    }

    public function test_tab_navigation_preserves_query_parameters()
    {
        $response = $this->get('/operations/bulk/overview?q=test&filter=variant_sku');
        
        $response->assertStatus(200);
        $response->assertSee('test'); // Should preserve search query
    }

    public function test_bulk_operations_routes_redirect_to_overview_by_default()
    {
        $response = $this->get('/operations/bulk');
        
        // Should redirect to overview by default
        $response->assertStatus(302);
        $response->assertRedirect('/operations/bulk/overview');
    }

    public function test_url_parameters_are_tracked_in_overview()
    {
        $response = $this->get('/operations/bulk/overview?q=curtain&filter=all');
        
        $response->assertStatus(200);
        // Should handle URL parameters without errors
        $response->assertDontSee('Fatal error');
        $response->assertDontSee('Exception');
    }
}