<?php

use App\Actions\Products\Wizard\SaveWizardDraftAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->action = new SaveWizardDraftAction;

    // Clear any existing session data
    session()->flush();
});

describe('SaveWizardDraftAction', function () {
    it('can save a draft to session', function () {
        $wizardData = [
            'product_info' => [
                'name' => 'Draft Product',
                'status' => 'draft',
            ],
            'variants' => [
                'generated_variants' => [
                    ['sku' => 'DRAFT-001', 'color' => 'Red'],
                ],
            ],
        ];

        $result = $this->action->execute($this->user->id, $wizardData);

        expect($result['success'])->toBeTrue();
        expect($result['message'])->toBe('Draft saved successfully');
        expect($result['data']['steps_saved'])->toBe(['product_info', 'variants']);

        // Verify data is in session
        $sessionKey = "wizard.product.draft.{$this->user->id}";
        expect(session()->has($sessionKey))->toBeTrue();

        $sessionData = session($sessionKey);
        expect($sessionData['data'])->toBe($wizardData);
        expect($sessionData['user_id'])->toBe($this->user->id);
    });

    it('can load a draft from session', function () {
        $wizardData = [
            'product_info' => [
                'name' => 'Loaded Draft Product',
                'status' => 'active',
            ],
        ];

        // First save a draft
        $this->action->execute($this->user->id, $wizardData);

        // Then load it
        $result = $this->action->loadDraft($this->user->id);

        expect($result['success'])->toBeTrue();
        expect($result['message'])->toBe('Draft loaded successfully');
        expect($result['data']['exists'])->toBeTrue();
        expect($result['data']['data'])->toBe($wizardData);
        expect($result['data']['steps'])->toBe(['product_info']);
    });

    it('returns empty data when no draft exists', function () {
        $result = $this->action->loadDraft($this->user->id);

        expect($result['success'])->toBeTrue();
        expect($result['message'])->toBe('No draft found');
        expect($result['data']['exists'])->toBeFalse();
        expect($result['data']['data'])->toBe([]);
    });

    it('can clear a draft', function () {
        $wizardData = [
            'product_info' => ['name' => 'To Be Cleared'],
        ];

        // Save a draft first
        $this->action->execute($this->user->id, $wizardData);

        // Verify it exists
        $sessionKey = "wizard.product.draft.{$this->user->id}";
        expect(session()->has($sessionKey))->toBeTrue();

        // Clear the draft
        $result = $this->action->clearDraft($this->user->id);

        expect($result['success'])->toBeTrue();
        expect($result['message'])->toBe('Draft cleared successfully');

        // Verify it's gone
        expect(session()->has($sessionKey))->toBeFalse();
    });

    it('can check if draft exists', function () {
        // Initially no draft exists
        $result = $this->action->draftExists($this->user->id);

        expect($result['success'])->toBeTrue();
        expect($result['data']['exists'])->toBeFalse();

        // Save a draft
        $wizardData = ['product_info' => ['name' => 'Existence Check']];
        $this->action->execute($this->user->id, $wizardData);

        // Check again
        $result = $this->action->draftExists($this->user->id);

        expect($result['data']['exists'])->toBeTrue();
        expect($result['data']['steps'])->toBe(['product_info']);
    });

    it('provides draft info with proper formatting', function () {
        // Test when no draft exists
        $info = $this->action->getDraftInfo($this->user->id);

        expect($info['exists'])->toBeFalse();
        expect($info['message'])->toBe('No draft available');
        expect($info['saved_at'])->toBeNull();
        expect($info['steps'])->toBe([]);

        // Save draft with multiple steps
        $wizardData = [
            'product_info' => ['name' => 'Multi Step Draft'],
            'variants' => ['generated_variants' => []],
            'images' => ['product_images' => []],
        ];

        $this->action->execute($this->user->id, $wizardData);

        // Check info
        $info = $this->action->getDraftInfo($this->user->id);

        expect($info['exists'])->toBeTrue();
        expect($info['message'])->toBe('3 steps');
        expect($info['steps'])->toBe(['product_info', 'variants', 'images']);
        expect($info['saved_at'])->not->toBeNull();
    });

    it('handles single step draft info message', function () {
        $wizardData = [
            'product_info' => ['name' => 'Single Step'],
        ];

        $this->action->execute($this->user->id, $wizardData);
        $info = $this->action->getDraftInfo($this->user->id);

        expect($info['message'])->toBe('1 step');
    });

    it('validates user ID is provided', function () {
        expect(fn () => $this->action->execute(null, []))
            ->toThrow(InvalidArgumentException::class, 'User ID is required');
    });

    it('generates correct session keys', function () {
        $wizardData = ['product_info' => ['name' => 'Session Key Test']];

        $result = $this->action->execute($this->user->id, $wizardData);

        $expectedKey = "wizard.product.draft.{$this->user->id}";
        expect($result['data']['session_key'])->toBe($expectedKey);
    });

    it('preserves data integrity across save/load cycles', function () {
        $originalData = [
            'product_info' => [
                'name' => 'Complex Product',
                'parent_sku' => 'COMPLEX-001',
                'description' => 'A complex product with nested arrays',
                'status' => 'draft',
            ],
            'variants' => [
                'colors' => ['Red', 'Blue', 'Green'],
                'widths' => [120, 150, 180],
                'generated_variants' => [
                    ['sku' => 'COMP-001', 'color' => 'Red', 'width' => 120],
                    ['sku' => 'COMP-002', 'color' => 'Blue', 'width' => 150],
                ],
                'total_variants' => 2,
            ],
        ];

        // Save
        $this->action->execute($this->user->id, $originalData);

        // Load
        $result = $this->action->loadDraft($this->user->id);

        // Verify complete data integrity
        expect($result['data']['data'])->toBe($originalData);
    });
});
