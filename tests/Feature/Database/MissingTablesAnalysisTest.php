<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

describe('Missing Database Tables Analysis', function () {
    it('identifies missing critical tables', function () {
        $existingTables = Schema::getAllTables();
        $existingTableNames = array_map(function($table) {
            return array_values((array)$table)[0];
        }, $existingTables);
        
        // Tables we have migrations for
        $expectedTables = [
            'users', 'cache', 'jobs', 'failed_jobs', 'password_reset_tokens',
            'products', 'product_variants', 'barcodes', 'images', 
            'categories', 'sales_channels', 'pricing'
        ];
        
        // Critical tables used by models but missing migrations
        $criticalMissingTables = [
            // Marketplace Sync System (heavily used - 108+ files)
            'sync_accounts',
            'sync_logs', 
            'sync_statuses',
            
            // Attributes System (flexible product metadata)
            'attribute_definitions',
            'product_attributes',
            'variant_attributes',
            
            // Linking System (marketplace connections)
            'marketplace_links',
            'sku_links',
            
            // Tagging System
            'tags',
            'product_tag', // pivot table
            
            // Marketplace Integration
            'marketplace_taxonomies',
            'marketplace_product_attributes',
            
            // Channel Mapping System
            'channel_field_definitions',
            'channel_field_mappings', 
            'channel_value_lists',
            
            // Barcode Management
            'barcode_pools',
            
            // Templates System
            'templates',
            
            // Legacy Shopify (might be replaceable)
            'shopify_sync_statuses',
        ];
        
        dump("=== EXISTING TABLES ===");
        dump($existingTableNames);
        
        dump("=== CRITICAL MISSING TABLES ===");
        $actuallyMissing = [];
        foreach ($criticalMissingTables as $table) {
            if (!in_array($table, $existingTableNames)) {
                $actuallyMissing[] = $table;
            }
        }
        dump($actuallyMissing);
        
        dump("=== PRIORITY ANALYSIS ===");
        dump("HIGH PRIORITY (App Breaking):");
        dump("- sync_accounts, sync_logs, sync_statuses (marketplace sync core)");
        dump("- attribute_definitions, product_attributes (flexible metadata)");
        dump("- marketplace_links (product-marketplace linking)");
        
        dump("MEDIUM PRIORITY (Feature Limited):");
        dump("- tags, product_tag (organization)");
        dump("- channel_field_* (marketplace field mapping)");
        dump("- marketplace_taxonomies (category mapping)");
        
        dump("LOW PRIORITY (Nice to Have):");
        dump("- barcode_pools, templates (management features)");
        dump("- shopify_sync_statuses (legacy, maybe replaceable)");
        
        // This will show us exactly what's missing
        expect(count($actuallyMissing))->toBe(0); // This will fail and show the missing tables
    });
    
    it('analyzes model dependencies', function () {
        // Check which models have factories (indicating they should have tables)
        $factoryPath = database_path('factories');
        $factoryFiles = glob($factoryPath . '/*.php');
        $modelsWithFactories = [];
        
        foreach ($factoryFiles as $file) {
            $filename = basename($file, '.php');
            if ($filename !== 'DatabaseSeeder' && str_ends_with($filename, 'Factory')) {
                $modelName = str_replace('Factory', '', $filename);
                $modelsWithFactories[] = $modelName;
            }
        }
        
        dump("=== MODELS WITH FACTORIES (Should have tables) ===");
        dump($modelsWithFactories);
        
        // Check what seeders exist (indicating important data)
        $seederPath = database_path('seeders');
        $seederFiles = glob($seederPath . '/*.php');
        $seeders = [];
        
        foreach ($seederFiles as $file) {
            $filename = basename($file, '.php');
            if ($filename !== 'DatabaseSeeder') {
                $seeders[] = $filename;
            }
        }
        
        dump("=== EXISTING SEEDERS (Important data) ===");
        dump($seeders);
        
        expect(count($modelsWithFactories))->toBeGreaterThan(0);
    });
});