<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('renders confirmation modal component', function () {
    $response = $this->get('/dashboard');
    
    $response->assertSee('confirmation-dialog');
    $response->assertSee('x-data');
    $response->assertSee('@click="confirm()"');
    $response->assertSee('@click="close()"');
});

it('modal has correct Alpine.js data structure', function () {
    $response = $this->get('/dashboard');
    
    // Check for Alpine.js data properties
    $response->assertSee('show: false');
    $response->assertSee('title: \'Confirm Action\'');
    $response->assertSee('message: \'Are you sure?\'');
    $response->assertSee('confirmText: \'Confirm\'');
    $response->assertSee('cancelText: \'Cancel\'');
    $response->assertSee('variant: \'danger\'');
    $response->assertSee('wireAction: \'\'');
    $response->assertSee('wireComponent: null');
});

it('modal has correct methods', function () {
    $response = $this->get('/dashboard');
    
    // Check for Alpine.js methods
    $response->assertSee('open(options)');
    $response->assertSee('close()');
    $response->assertSee('confirm()');
});

it('modal has all three button variants', function () {
    $response = $this->get('/dashboard');
    
    // Check for all three confirm button variants (HTML encoded)
    $response->assertSee('x-show="variant === &#039;danger&#039;"', false);
    $response->assertSee('variant="danger"', false);
    $response->assertSee('x-show="variant === &#039;warning&#039;"', false);
    $response->assertSee('variant="warning"', false);
    $response->assertSee('variant="primary"', false);
});

it('modal has cancel button with ghost variant', function () {
    $response = $this->get('/dashboard');
    
    $response->assertSee('variant="ghost"');
    $response->assertSee('@click="close()"');
    $response->assertSee('x-text="cancelText"');
});

it('modal uses proper dialog element', function () {
    $response = $this->get('/dashboard');
    
    $response->assertSee('<dialog');
    $response->assertSee('id="confirmation-dialog"');
    $response->assertSee('@keydown.escape="close()"');
    $response->assertSee('@click.self="close()"');
});

it('modal has proper backdrop styling', function () {
    $response = $this->get('/dashboard');
    
    $response->assertSee('backdrop:bg-gray-500/75');
    $response->assertSee('backdrop:transition-opacity');
    $response->assertSee('backdrop:duration-300');
});

it('global confirmAction function is available', function () {
    $response = $this->get('/dashboard');
    
    $response->assertSee('window.confirmAction = function(options)');
    $response->assertSee('document.querySelector(\'#confirmation-dialog\')');
    $response->assertSee('modalData.open(options)');
});

it('modal handles different variants correctly in confirm method', function () {
    $response = $this->get('/dashboard');
    
    // Check parameter parsing logic
    $response->assertSee('this.wireAction.includes(\'(\')');
    $response->assertSee('this.wireComponent.call(methodName, ...params)');
    $response->assertSee('this.wireComponent.call(this.wireAction)');
});

it('modal sets body overflow hidden when opened', function () {
    $response = $this->get('/dashboard');
    
    $response->assertSee('document.body.style.overflow = \'hidden\'');
    $response->assertSee('document.body.style.overflow = \'\'');
});

it('modal has proper icon variants', function () {
    $response = $this->get('/dashboard');
    
    // Check for variant-based icons
    $response->assertSee('x-show="variant === \'danger\'"');
    $response->assertSee('text-red-600 dark:text-red-400');
    $response->assertSee('x-show="variant === \'warning\'"');
    $response->assertSee('text-amber-600 dark:text-amber-400');
    $response->assertSee('x-show="variant === \'info\'"');
    $response->assertSee('text-blue-600 dark:text-blue-400');
});

it('modal has proper background colors for icon containers', function () {
    $response = $this->get('/dashboard');
    
    $response->assertSee('bg-red-100 dark:bg-red-900/30');
    $response->assertSee('bg-amber-100 dark:bg-amber-900/30');
    $response->assertSee('bg-blue-100 dark:bg-blue-900/30');
});