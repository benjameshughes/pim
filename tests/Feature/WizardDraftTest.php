<?php

use App\Models\User;
use App\Services\WizardDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Wizard Draft Service', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->draftService = new WizardDraftService();
    });

    it('can save and retrieve draft data', function () {
        $draftData = [
            'step_1' => [
                'name' => 'Test Product',
                'parent_sku' => '123',
            ]
        ];

        $result = $this->draftService->save((string) $this->user->id, null, $draftData);
        expect($result)->toBeTrue();

        $retrieved = $this->draftService->get((string) $this->user->id, null);
        expect($retrieved)->not()->toBeNull();
        expect($retrieved['data'])->toBe($draftData);
        expect($retrieved['user_id'])->toBe((string) $this->user->id);
    });

    it('can update specific step data', function () {
        $stepData = [
            'name' => 'Updated Product',
            'parent_sku' => '456',
        ];

        $result = $this->draftService->updateStep((string) $this->user->id, null, 1, $stepData);
        expect($result)->toBeTrue();

        $retrieved = $this->draftService->get((string) $this->user->id, null);
        expect($retrieved['data']['step_1'])->toBe($stepData);
    });

    it('can delete draft data', function () {
        $draftData = ['test' => 'data'];
        
        $this->draftService->save((string) $this->user->id, null, $draftData);
        expect($this->draftService->exists((string) $this->user->id, null))->toBeTrue();

        $deleted = $this->draftService->delete((string) $this->user->id, null);
        expect($deleted)->toBeTrue();
        expect($this->draftService->exists((string) $this->user->id, null))->toBeFalse();
    });

    it('handles different products separately', function () {
        $data1 = ['product' => 'one'];
        $data2 = ['product' => 'two'];

        $this->draftService->save((string) $this->user->id, 1, $data1);
        $this->draftService->save((string) $this->user->id, 2, $data2);

        $draft1 = $this->draftService->get((string) $this->user->id, 1);
        $draft2 = $this->draftService->get((string) $this->user->id, 2);

        expect($draft1['data'])->toBe($data1);
        expect($draft2['data'])->toBe($data2);
        expect($draft1['product_id'])->toBe(1);
        expect($draft2['product_id'])->toBe(2);
    });
});