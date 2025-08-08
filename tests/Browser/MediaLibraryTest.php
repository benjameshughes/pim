<?php

namespace Tests\Browser;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class MediaLibraryTest extends DuskTestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user for authentication
        $this->user = User::factory()->create();
    }

    public function test_media_library_loads_without_console_errors()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/images')
                    ->waitForText('Media Library')
                    ->assertSee('Media Library')
                    ->assertSee('Manage images for products and variants')
                    ->assertNoConsoleErrors();
        });
    }

    public function test_page_template_structure_renders_correctly()
    {
        // Create some test data for stats
        ProductImage::factory()->count(5)->create();
        ProductImage::factory()->count(3)->create(['product_id' => Product::factory()->create()->id]);
        ProductImage::factory()->count(2)->create(['variant_id' => ProductVariant::factory()->create()->id]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/images')
                    ->waitForText('Media Library')
                    
                    // Test page template structure
                    ->assertSee('Media Library')
                    ->assertSee('Dashboard') // Breadcrumb
                    ->assertPresent('[data-testid="stats-grid"]')
                    
                    // Test stats cards
                    ->assertSee('Total Images')
                    ->assertSee('Unassigned')
                    ->assertSee('Products')
                    ->assertSee('Variants')
                    ->assertSee('Pending')
                    ->assertSee('Processing')
                    ->assertSee('Completed')
                    ->assertSee('Failed')
                    
                    // Test navigation tabs
                    ->assertSee('Image Library')
                    ->assertSee('Bulk Upload')
                    
                    ->assertNoConsoleErrors();
        });
    }

    public function test_assignment_mode_toggle_works()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/images')
                    ->waitForText('Media Library')
                    
                    // Test assignment mode toggle
                    ->assertSee('Assign Mode')
                    ->click('button:contains("Assign Mode")')
                    ->waitForText('Assignment Mode')
                    ->assertSee('Assignment Mode')
                    ->assertSee('Select Product')
                    ->assertSee('Select Product First')
                    
                    // Test exit assignment mode
                    ->click('button:contains("Exit Assignment")')
                    ->waitUntilMissing('[data-testid="assignment-panel"]', 2)
                    ->assertDontSee('Assignment Mode')
                    
                    ->assertNoConsoleErrors();
        });
    }

    public function test_tab_navigation_works_correctly()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/images')
                    ->waitForText('Media Library')
                    
                    // Test Library tab (default)
                    ->assertSeeIn('.border-blue-500', 'Image Library')
                    
                    // Test Upload tab
                    ->click('button:contains("Bulk Upload")')
                    ->waitForText('Upload multiple images')
                    ->assertSee('Upload multiple images')
                    ->assertSeeIn('.border-blue-500', 'Bulk Upload')
                    
                    // Go back to Library tab
                    ->click('button:contains("Image Library")')
                    ->waitForText('No images found', 3) // Should see empty state
                    ->assertSeeIn('.border-blue-500', 'Image Library')
                    
                    ->assertNoConsoleErrors();
        });
    }

    public function test_stats_display_correct_counts()
    {
        // Create test data
        ProductImage::factory()->count(10)->create(); // Unassigned
        ProductImage::factory()->count(5)->create([
            'product_id' => Product::factory()->create()->id
        ]);
        ProductImage::factory()->count(3)->create([
            'variant_id' => ProductVariant::factory()->create()->id
        ]);
        ProductImage::factory()->count(2)->create([
            'processing_status' => ProductImage::PROCESSING_FAILED
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/images')
                    ->waitForText('Media Library')
                    ->waitForText('20') // Total images (10+5+3+2)
                    
                    // Check stats are displayed correctly
                    ->assertSeeIn('[data-testid="stats-grid"]', '20') // Total
                    ->assertSeeIn('[data-testid="stats-grid"]', '12') // Unassigned (10+2)
                    ->assertSeeIn('[data-testid="stats-grid"]', '5')  // Products
                    ->assertSeeIn('[data-testid="stats-grid"]', '3')  // Variants
                    
                    ->assertNoConsoleErrors();
        });
    }

    public function test_failed_images_tab_appears_when_failures_exist()
    {
        // Create failed images
        ProductImage::factory()->count(3)->create([
            'processing_status' => ProductImage::PROCESSING_FAILED
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/images')
                    ->waitForText('Media Library')
                    
                    // Should see Failed tab when failures exist
                    ->assertSee('Failed (3)')
                    ->assertSee('Retry Failed (3)') // Action button
                    
                    // Test clicking Failed tab
                    ->click('button:contains("Failed")')
                    ->waitForText('Failed Processing (3)')
                    ->assertSee('Failed Processing (3)')
                    ->assertSee('Reprocess All Failed')
                    
                    ->assertNoConsoleErrors();
        });
    }

    public function test_unassigned_images_tab_appears_when_unassigned_exist()
    {
        // Create unassigned images
        ProductImage::factory()->count(5)->create([
            'product_id' => null,
            'variant_id' => null
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/images')
                    ->waitForText('Media Library')
                    
                    // Should see Unassigned tab when unassigned exist
                    ->assertSee('Unassigned (5)')
                    
                    // Test clicking Unassigned tab
                    ->click('button:contains("Unassigned")')
                    ->waitForText('Unassigned Images (5)')
                    ->assertSee('Unassigned Images (5)')
                    ->assertSee('Bulk Select')
                    
                    ->assertNoConsoleErrors();
        });
    }

    public function test_alpine_js_flash_messages_work()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/images')
                    ->waitForText('Media Library')
                    
                    // Simulate a flash message by adding it to session
                    ->visitRoute('images.index', [], [
                        'success' => 'Test success message!'
                    ])
                    ->waitForText('Test success message!')
                    ->assertSee('Test success message!')
                    
                    // Wait for Alpine.js auto-hide (should disappear after 5 seconds)
                    ->waitUntilMissing('[data-testid="flash-message"]', 6)
                    
                    ->assertNoConsoleErrors();
        });
    }

    public function test_sidebar_navigation_is_present()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/images')
                    ->waitForText('Media Library')
                    
                    // Test that we have the full app layout with sidebar
                    ->assertPresent('[data-testid="sidebar"]') 
                    ->assertSee('Dashboard') // Should see navigation items
                    ->assertSee('Products')   // Should see navigation items
                    
                    // Test clicking navigation still works
                    ->click('a[href*="dashboard"]')
                    ->waitForText('Dashboard') // Should navigate
                    ->assertPathIs('/dashboard')
                    
                    ->assertNoConsoleErrors();
        });
    }

    public function test_responsive_layout_works()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/images')
                    ->waitForText('Media Library')
                    
                    // Test desktop layout
                    ->resize(1200, 800)
                    ->assertPresent('[data-testid="stats-grid"]')
                    
                    // Test mobile layout
                    ->resize(375, 667)
                    ->waitFor('[data-testid="stats-grid"]') // Should still be present
                    ->assertPresent('[data-testid="stats-grid"]')
                    
                    // Test tablet layout
                    ->resize(768, 1024)
                    ->assertPresent('[data-testid="stats-grid"]')
                    
                    ->assertNoConsoleErrors();
        });
    }

    public function test_page_loads_under_acceptable_time()
    {
        $this->browse(function (Browser $browser) {
            $start = microtime(true);
            
            $browser->loginAs($this->user)
                    ->visit('/images')
                    ->waitForText('Media Library');
            
            $loadTime = microtime(true) - $start;
            
            // Assert page loads in under 3 seconds
            $this->assertLessThan(3.0, $loadTime, 'Page took too long to load: ' . $loadTime . 's');
            
            $browser->assertNoConsoleErrors();
        });
    }
}